<?php
/**
 * Created by PhpStorm.
 * User: dmytro
 * Date: 1/20/19
 * Time: 16:36
 */

namespace TransAgentBot;

class Database {

	protected $connection;

	private $user_table
		= BotCore::ENV == 'dev' ? 'bot_users_info_test' : 'bot_users_info';
	private $currency_table
		= BotCore::ENV == 'dev' ? 'currency_info_test' : 'currency_info';
	private $meta_table
		= BotCore::ENV == 'dev' ? 'users_meta_test' : 'users_meta';
	private $logs_table
		= BotCore::ENV == 'dev' ? 'logs_test' : 'logs';

	public function __construct( $servername, $username, $password, $dbname ) {
		$this->connection = new \mysqli( $servername, $username, $password,
			$dbname );
		$this->connection->set_charset( 'utf8' );
	}

	public function check_connection() {
		// Check connection
		if ( $this->connection->connect_error ) {
			die( "Connection failed: " . mysqli_connect_error() );
		}

		return TRUE;
	}

	public function get_chats_id() {
		$sql      = "SELECT chat_id FROM {$this->user_table}";
		$result   = $this->connection->query( $sql );
		$chats_id = [];

		if ( $result->num_rows > 0 ) {
			// output data of each row
			while ( $row = $result->fetch_assoc() ) {
				$chats_id[] = $row["chat_id"];
			}
		}

		return $chats_id;
	}

	public function insert_new_user( $firstname, $lastname, $username, $chat_id
	) {
		$sql
			= "INSERT INTO {$this->user_table} (firstname, lastname, username, chat_id, role)
				VALUES ('$firstname', '$lastname', '$username', '$chat_id', 'user')";

		if ( $this->connection->query( $sql ) === TRUE ) {
			return "New record created successfully";
		} else {
			return "Error: " . $sql . "<br>" . $this->connection->error;
		}
		//		$this->connection->close();
	}

	public function get_rate( $currency = 'USD' ) {
		$sql
			    = "SELECT rate 
					 FROM {$this->currency_table}
					 WHERE currency = '$currency'";
		$result = $this->connection->query( $sql );
		$rate   = mysqli_fetch_object( $result )->rate;

		return $rate;
	}

	public function get_last_update( $currency = 'USD' ) {
		$sql
			         = "SELECT last_update 
					 FROM {$this->currency_table}
					 WHERE currency = '$currency'";
		$result      = $this->connection->query( $sql );
		$last_update = mysqli_fetch_object( $result )->last_update;

		return $last_update;
	}

	public function update_rate( $rate, $currency = 'USD' ) {
		$sql
			= "UPDATE {$this->currency_table} 
				SET rate = $rate
				WHERE currency = '$currency'";

		if ( $this->connection->query( $sql ) === TRUE ) {
			return $this->get_rate( $currency );
		} else {
			return "Error: " . $sql . "<br>" . $this->connection->error;
		}
	}

	public function update_status( $status, $currency = 'USD' ) {
		$sql
			= "UPDATE {$this->currency_table} 
				SET status = '$status'
				WHERE currency = '$currency'";

		if ( $this->connection->query( $sql ) === TRUE ) {
			return $this->get_rate();
		} else {
			return "Error: " . $sql . "<br>" . $this->connection->error;
		}
	}

	public function update_meta_status( $status, $chat_id, $meta_key ) {

		$user_id = $this->get_user_id( $chat_id );

		$sql
			= "UPDATE {$this->meta_table} 
				SET meta_value = '$status'
				WHERE user_id = {$user_id} AND meta_key = '$meta_key'";

		if ( $this->connection->query( $sql ) === TRUE ) {
			return TRUE;
		} else {
			return "Error: " . $sql . "<br>" . $this->connection->error;
		}
	}

	public function get_currency( $status = 'wait' ) {
		$sql
			      = "SELECT currency 
					 FROM {$this->currency_table}
					 WHERE status = '$status'";
		$result   = $this->connection->query( $sql );
		$currency = mysqli_fetch_object( $result )->currency;

		return $currency;

	}

	public function get_user_id( $chat_id ) {
		$sql
			     = "SELECT id 
					 FROM {$this->user_table}
					 WHERE chat_id = '$chat_id'";
		$result  = $this->connection->query( $sql );
		$user_id = mysqli_fetch_object( $result )->id;

		return $user_id;
	}

	public function is_meta_row_exists( $user_id, $meta_key ) {

		$sql
			= "SELECT id 
					 FROM {$this->meta_table}
					 WHERE user_id = '$user_id' AND meta_key = '$meta_key'";

		$result = $this->connection->query( $sql );
		$id     = mysqli_fetch_object( $result )->id;

		return $id;

	}

	public function insert_meta_row( $chat_id, $meta_key, $meta_value ) {

		$user_id = $this->get_user_id( $chat_id );

		if ( ! $this->is_meta_row_exists( $user_id, $meta_key )
		     || $meta_key == 'wish_message'
		) {
			$sql
				= "INSERT INTO {$this->meta_table} (user_id, meta_key, meta_value)
				VALUES ('$user_id', '$meta_key', '$meta_value')";

			if ( $this->connection->query( $sql ) === TRUE ) {
				return "New record created/updated successfully";
			} else {
				return "Error: " . $sql . "<br>" . $this->connection->error;
			}
		}
	}

	public function get_meta_status( $chat_id, $meta_key, $status = 'wait' ) {

		$user_id = $this->get_user_id( $chat_id );

		$sql
			    = "SELECT meta_value 
					 FROM {$this->meta_table}
					 WHERE user_id = {$user_id} AND meta_key = '$meta_key' AND meta_value = '$status'
					 LIMIT 1";
		$result = $this->connection->query( $sql );
		$status = mysqli_fetch_object( $result )->meta_value;

		return $status;

	}

	public function is_admin( $chat_id ) {
		$sql
			    = "SELECT role 
					 FROM {$this->user_table}
					 WHERE chat_id = $chat_id";
		$result = $this->connection->query( $sql );
		$role   = mysqli_fetch_object( $result )->role;

		if ( $role == 'admin' ) {
			return TRUE;
		}

		return FALSE;
	}

	public function add_log( $chat_id, $action, $value ) {

		$user_id = $this->get_user_id( $chat_id );
		$sql
		         = "INSERT INTO {$this->logs_table} (user_id, user_action, val)
					VALUES ('$user_id', '$action', '$value')";

		if ( $this->connection->query( $sql ) === TRUE ) {
			return "New record created successfully";
		} else {
			return "Error: " . $sql . "<br>" . $this->connection->error;
		}
	}
}