<?php
/**
 * Created by PhpStorm.
 * User: dmytro
 * Date: 1/25/19
 * Time: 16:57
 */

namespace TransAgentBot;


use Telegram\Bot\Api;
use Telegram\Bot\Keyboard\Keyboard;

class BotCore {

	CONST ENV = 'prod'; // dev or prod

	public $telegram;
	public $result;
	public $db;
	public $static;

	public $text;
	public $chat_id;
	public $first_name;
	public $last_name;
	public $username;
	public $phone_number;

	public function __construct( $token ) {

		$this->static = require '../static.php';


		$this->telegram = new Api( $token );

		$this->result = $this->telegram->getWebhookUpdate();

		$message          = $this->result->getMessage();
		$this->chat_id    = $message->chat->id;
		$this->first_name = $message->from->firstName;
		$this->last_name  = $message->from->lastName;
		$this->username   = $message->from->username;
		$this->text       = $message->text;
		//		$this->phone_number = $message->contact->phoneNumber;

		$this->db = new Database( $this->static->db->host,
			$this->static->db->user,
			$this->static->db->pass, $this->static->db->db_name );

		// If we have any non numeric message, force close all 'wait' statuses
		if ( ! is_numeric( $this->text ) ) {
			$this->close_wait_statuses();
		}

	}

	public function close_wait_statuses() {
		$this->db->update_meta_status( 'close', $this->chat_id,
			'calculate_status' );

		if ( $this->db->is_admin( $this->chat_id ) ) {
			// Change 'wait' status of currency
			$currency = $this->db->get_currency( 'wait' );

			if ( $currency ) {
				$this->db->update_status( 'close', $currency );
			}
		}
	}

	public function prepare_start_keyboard() {
		$keyboard       = [
			[
				$this->static->cmd->btn1,
				$this->static->cmd->btn4,
			],
			[
				$this->static->cmd->btn5,
				$this->static->cmd->btn6,
			],
		];
		$keyboard_admin = [
			[
				$this->static->cmd->btn1,
				$this->static->cmd->btn4,
			],
			[
				$this->static->cmd->btn5,
				$this->static->cmd->btn6,
			],
			[
				$this->static->cmd->btn2,
				$this->static->cmd->btn3,
			],
		];

		$params = [
			'keyboard'          => $keyboard,
			'resize_keyboard'   => TRUE,
			'one_time_keyboard' => FALSE,
		];


		if ( $this->db->is_admin( $this->chat_id ) ) {
			$params['keyboard'] = $keyboard_admin;
		}

		$reply_markup = Keyboard::make( $params );

		return $reply_markup;
	}

	public function start_message() {

		$reply = "Привіт) " . "<strong>" . $this->first_name . "</strong>";

		$this->telegram->sendMessage( [
			'chat_id'      => $this->chat_id,
			'text'         => $reply,
			'parse_mode'   => 'HTML',
			'reply_markup' => $this->prepare_start_keyboard(),
		] );

		$chats_id = $this->db->get_chats_id();

		if ( ! in_array( $this->chat_id, $chats_id ) ) {
			$this->db->insert_new_user( $this->first_name, $this->last_name,
				$this->username,
				$this->chat_id );
		}
	}

	public function show_currencies_message() {

		$rate_cny        = $this->db->get_rate( 'USD/CNY' );
		$last_update_cny = $this->db->get_last_update( 'USD/CNY' );

		$rate_uah        = $this->db->get_rate( 'USD/UAH' );
		$last_update_uah = $this->db->get_last_update( 'USD/UAH' );

		$reply = "Курс $/¥: <strong>" . $rate_cny . "</strong>"
		         . " станом на $last_update_cny\r\n";
		$reply .= "Курс $/₴: <strong>" . $rate_uah . "</strong>"
		          . " станом на $last_update_uah";

		$this->telegram->sendMessage( [
			'chat_id'      => $this->chat_id,
			'parse_mode'   => 'HTML',
			'text'         => $reply,
			'reply_markup' => $this->prepare_start_keyboard(),
		] );

		$this->db->add_log( $this->chat_id, 'курс', '' );
	}

	public function calculator_message() {

		$this->db->insert_meta_row( $this->chat_id, 'calculate_status',
			'wait' );

		$this->db->update_meta_status( 'wait', $this->chat_id,
			'calculate_status' );

		$this->telegram->sendMessage( [
			'chat_id'      => $this->chat_id,
			'text'         => $this->static->message->calculate,
			'parse_mode'   => 'HTML',
			'reply_markup' => $this->prepare_start_keyboard(),
		] );

	}

	public function set_currency_message( $curr ) {

		$this->db->update_status( 'wait', $curr );

		$this->telegram->sendMessage( [
			'chat_id'      => $this->chat_id,
			'text'         => "Введіть новий курс $curr в форматі (хх.ххх) 👇",
			'reply_markup' => $this->prepare_start_keyboard(),
		] );
	}

	public function set_currency_rate_message() {

		if ( $currency = $this->db->get_currency( 'wait' ) ) {

			$result = $this->db->update_rate( floatval( $this->text ),
				$currency );

			$this->telegram->sendMessage( [
				'chat_id'      => $this->chat_id,
				'text'         => "Курс $currency заданий: $result",
				'parse_mode'   => 'HTML',
				'reply_markup' => $this->prepare_start_keyboard(),
			] );

			$this->db->update_status( 'close', $currency );

		} elseif ( $this->db->get_meta_status( $this->chat_id,
			'calculate_status', 'wait' )
		) {

			//			Сумма в юанях / курс доллара + 1%  (але менше 5$ ) коміссія * курс грн

			$rate_cny = round( $this->db->get_rate( 'USD/CNY' ), 2 );
			$rate_uah = round( $this->db->get_rate( 'USD/UAH' ), 2 );

			$net_sum_usd = round( $this->text / $rate_cny, 2 );

			$commission = 5; // 5$ if sum < 500$

			if ( $net_sum_usd >= 500 ) {
				// 1% if sum >= 500$
				$commission = round( $net_sum_usd * 0.01, 2 );
			}

			$sum_usd = round( $this->text / $rate_cny + $commission, 2 );
			$sum_uah = round( $sum_usd * $rate_uah, 2 );

			//			Ваша сума: 490 ¥
			//			Курс: 6,72 ¥,  28,2 ₴
			//			Комісія: 0,0% -5 $
			//			До сплати: 77,9 $ або 2197 ₴

			$reply = "Ваша сума: <b>$this->text ¥</b>\r\n";
			$reply .= "Курс: <b>$rate_cny ¥</b>, <b>$rate_uah ₴</b>\r\n";
			$reply .= "Комісія: <b>+$commission $</b>\r\n";
			$reply .= "До сплати: <b>$sum_usd $</b> або <b>$sum_uah ₴</b>";

			$this->telegram->sendMessage( [
				'chat_id'      => $this->chat_id,
				'text'         => $reply,
				'parse_mode'   => 'HTML',
				'reply_markup' => $this->prepare_start_keyboard(),
			] );

			$this->db->update_meta_status( 'close', $this->chat_id,
				'calculate_status' );

			$this->db->add_log( $this->chat_id, 'калькулятор', $this->text );

		} else {

			$this->not_found_message_text();
		}
	}

	public function wishes_message() {

		$this->telegram->sendMessage( [
			'chat_id'      => $this->chat_id,
			'parse_mode'   => 'HTML',
			'text'         => $this->static->message->wishes,
			'reply_markup' => $this->prepare_start_keyboard(),
		] );

		$this->db->insert_meta_row( $this->chat_id, 'wishes_status', 'wait' );
		$this->db->update_meta_status( 'wait', $this->chat_id,
			'wishes_status' );

	}

	public function info_message() {

		$this->telegram->sendMessage( [
			'chat_id'      => $this->chat_id,
			'parse_mode'   => 'HTML',
			'text'         => $this->static->message->info,
			'reply_markup' => $this->prepare_start_keyboard(),
		] );

	}

	public function not_found_message() {

		if ( $this->db->get_meta_status( $this->chat_id, 'wishes_status',
			'wait' )
		) {

			//save wish in meta table
			// send message that wish saved
			// update wish_status = close

			$this->db->insert_meta_row( $this->chat_id, 'wish_message',
				$this->text );

			$admins_id = [ 370554598, 76852895 ]; // Dima S, Dima V

			foreach ( $admins_id as $admin_id ) {

				$this->telegram->sendMessage( [
					'chat_id'      => $admin_id,
					'parse_mode'   => 'HTML',
					'text'         => "Нове побажання від <b>$this->username ($this->chat_id)</b>\r\n"
					                  . "\"<i>" . $this->text . "\"</i>",
					'reply_markup' => $this->prepare_start_keyboard(),
				] );
			}

			$this->telegram->sendMessage( [
				'chat_id'      => $this->chat_id,
				'parse_mode'   => 'HTML',
				'text'         => $this->static->message->wish_saved,
				'reply_markup' => $this->prepare_start_keyboard(),
			] );

			$this->db->update_meta_status( 'close', $this->chat_id,
				'wishes_status' );


		} else {
			$this->not_found_message_text();
		}

	}

	private function not_found_message_text() {
		$reply = "Команда невідома: " . "<strong>\"" . $this->text
		         . "\"</strong>\r\nПовторіть команду👇";
		$this->telegram->sendMessage( [
			'chat_id'      => $this->chat_id,
			'parse_mode'   => 'HTML',
			'text'         => $reply,
			'reply_markup' => $this->prepare_start_keyboard(),
		] );
	}
}