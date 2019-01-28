<?php
/**
 * Created by PhpStorm.
 * User: dmytro
 * Date: 1/20/19
 * Time: 12:01
 */

require '../vendor/autoload.php';
require '../static.php';

use TransAgentBot\BotCore;

try {

	$token = BotCore::ENV == 'dev' ? $static->token_test : $static->token;

	$bot = new BotCore( $token );

	if ( $bot->db->check_connection() !== TRUE ) {
		// send message that bot not working
	}

	$result = $bot->result;

	if ( $result->isType( 'message' ) ) {

		$text = $bot->text;

		if ( $text == $static->cmd->start ) {

			$bot->start_message();

		} elseif ( $text == $static->cmd->btn1 ) {

			$bot->show_currencies_message();

		} elseif ( $text == $static->cmd->btn4 ) {

			$bot->calculator_message();

		} elseif ( $text == $static->cmd->btn2 ) {

			$bot->set_currency_message( 'USD/CNY' );

		} elseif ( $text == $static->cmd->btn3 ) {

			$bot->set_currency_message( 'USD/UAH' );

		} elseif ( $text == $static->cmd->btn5 ) {

			$bot->wishes_message();

		} elseif ( $text == $static->cmd->btn6 ) {

			$bot->info_message();

		} elseif ( is_numeric( $text ) ) {

			$bot->set_currency_rate_message();

		} else {

			$bot->not_found_message();

		}
	} // Callback type data
	elseif ( $result->isType( 'callback_query' ) ) {
		echo 'callback type';
	}
} catch ( Exception $e ) {
	echo $e;
}