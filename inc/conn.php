<?php

/** 
 * wpdb 实现事务 transaction
 */

class Conn_frog extends wpdb {
	public function __construct() {
		$dbuser     = defined( 'DB_USER' ) ? DB_USER : '';
		$dbpassword = defined( 'DB_PASSWORD' ) ? DB_PASSWORD : '';
		$dbname     = defined( 'DB_NAME' ) ? DB_NAME : '';
		$dbhost     = defined( 'DB_HOST' ) ? DB_HOST : '';
		
		// Initialize
		parent::__construct($dbuser, $dbpassword, $dbname, $dbhost);
		
		// start transaction after execute db_connect().
		// $this->start();
	}
	
	/**
	 * Show SQL/DB errors.
	 */
	public $show_errors = true;
	
	/**
	 * The current connection ID (thread ID)
	 */
	public $conn_id = null;
	
	/**
	 * Whether current connection is in autocommit mode.
	 */
	public $is_auto_commit = false;
	
	/**
	 * Get the connection ID (thread ID) for current connection
	 * https://dev.mysql.com/doc/refman/8.0/en/information-functions.html#function_connection-id
	 */
	public function get_conn_id() {
		if ( $this->use_mysqli ) {
			$res = mysqli_query( $this->dbh, 'SELECT CONNECTION_ID()' );
			$modes_array = mysqli_fetch_array( $res );
			if ( empty( $modes_array[0] ) ) {
				return;
			}
			$conn_id = $modes_array[0];
		} else {
			// https://www.php.net/manual/zh/function.mysql-query.php
			$res = mysql_query( 'SELECT CONNECTION_ID()', $this->dbh );
			$conn_id = mysql_result( $res, 0 );
		}
		return $conn_id;
	}
	
	/**
	 * START A NEW TRANSACTION
	 * 
	 * By default, MySQL runs with autocommit mode enabled.
	 * START TRANSACTION statement will disable autocommit mode implicitly for a single series of statements,
	 * until you end the transaction with COMMIT or ROLLBACK.
	 */
	public function start() {
		if ( $this->use_mysqli ) {
			$res = mysqli_query( $this->dbh, 'START TRANSACTION' );
			if (!$res) {
				$err = 'failed to start transaction: ' . mysqli_error( $this->dbh );
				$this->print_error($err);
			}
		} else {
			$res = mysql_query( 'START TRANSACTION', $this->dbh );
			if (!$res) {
				$err = 'failed to start transaction: ' . mysql_error( $this->dbh );
				$this->print_error($err);
			}
		}
		$this->conn_id = $this->get_conn_id();
 	}
	
	/**
	 * COMMIT TRANSACTION
	 *
	 * Commits the current transaction, making its changes permanent
	 */
	public function commit() {
		if ($this->is_auto_commit) {
			echo 'current session is running in AUTOCOMMIT mode, Don\'t need to commit!';
			return;
		}
		
		if ( $this->use_mysqli ) {
			$res = mysqli_query( $this->dbh, 'COMMIT' );
			if (!$res) {
				$err = 'failed to commit transaction: ' . mysqli_error( $this->dbh );
				$this->print_error($err);
			}
		} else {
			$res = mysql_query( 'COMMIT', $this->dbh );
			if (!$res) {
				$err = 'failed to commit transaction: ' . mysql_error( $this->dbh );
				$this->print_error($err);
			}
		}
		echo "\nCOMMIT\n";
 	}
	
	/**
	 * ROLLBACK TRANSACTION
	 *
	 * rolls back the current transaction, canceling its changes.
	 */
	public function rollback() {
		if ($this->is_auto_commit) {
			echo 'current session is running in AUTOCOMMIT mode, failed to rollback!';
			return;
		}
		if ( $this->use_mysqli ) {
			$res = mysqli_query( $this->dbh, 'ROLLBACK' );
			if (!$res) {
				$err = 'failed to rollback transaction: ' . mysqli_error( $this->dbh );
				$this->print_error($err);
			}
		} else {
			$res = mysql_query( 'ROLLBACK', $this->dbh );
			if (!$res) {
				$err = 'failed to rollback transaction: ' . mysql_error( $this->dbh );
				$this->print_error($err);
			}
		}
		echo "\nROLLBACK\n";
 	}
	
	/**
	 * SET AUTO-COMMIT
	 *
	 * AUTOCOMMIT statement:
	 * AUTOCOMMIT is a session variable and must be set for each session.
	 * To disable autocommit mode explicitly, use the following statement:
	 *     SET autocommit=0;
	 * After disabling autocommit mode by setting the autocommit variable to zero,
	 * changes to transaction-safe tables (such as those for InnoDB or NDB) are not made permanent immediately.
	 * You must use COMMIT to store your changes to disk or ROLLBACK to ignore the changes.
	 * 
	 * However, we have executed START TRANSACTION statement before executing any REST API,
	 * therefore, current session is running in autocommit disabled mode,
	 * we should execute a COMMIT statement to revert to autocommit mode.
	 * 
	 **/
	public function auto_commit() {
		if ( $this->use_mysqli ) {
			$res = mysqli_query( $this->dbh, 'COMMIT' );
			if (!$res) {
				$err = 'failed to commit: ' . mysqli_error( $this->dbh );
				$this->print_error($err);
			}
			
			// get autocommit variable
			$res = mysqli_query( $this->dbh, 'SELECT @@autocommit' );
			$modes_array = mysqli_fetch_array( $res );
			
			// Set autocommit mode if current running in autocommit disabled mode
			if ( !$modes_array[0] ) {
				$res = mysqli_query( $this->dbh, 'SET autocommit = 1' );
				if (!$res) {
					$err = 'failed to set autocommit mode: ' . mysqli_error( $this->dbh );
					$this->print_error($err);
				}
			}
		} else {
			$res = mysql_query( 'COMMIT', $this->dbh );
			if (!$res) {
				$err = 'failed to set commit mode: ' . mysql_error( $this->dbh );
				$this->print_error($err);
			}
			
			// get autocommit variable
			$res = mysql_query( 'SELECT @@autocommit', $this->dbh );
			$mode_var = mysql_result( $res, 0 );
			
			// Set autocommit mode if current running in autocommit disabled mode
			if ( !$mode_var ) {
				$res = mysql_query( 'SET autocommit = 1', $this->dbh );
				if (!$res) {
					$err = 'failed to set autocommit mode: ' . mysql_error( $this->dbh );
					$this->print_error($err);
				}
			}
		}
		
		$this->is_auto_commit = true;
		echo "\nAUTO COMMIT\n";
 	}
	
	/**
	 * INSERT MULTIPLE ROWS AT ONCE
	 *
	 * $table string 写入的表
	 * $field [] 需要写入的字段组成的数组
	 * $type  [] 需要写入字段的格式组成的数组 string, number
	 * $data  [[],[]] 写入的数据
	 * return 返回插入操作所生效的行数
	 * https://dev.mysql.com/doc/refman/8.0/en/insert.html
	 * https://developer.wordpress.org/reference/classes/wpdb/insert/
	 */
	public function insert_rows($table, $field, $type, $data, $format = null) {
		$field = wp_unslash($field);
		$data  = wp_unslash($data);
		$field = implode(',', $field);
		$values = array();
		foreach ($data as $val) {
			foreach($val as $k => $v) {
				if ($type[$k] === 'string') {
					$val[$k] = "'$v'";
				}
			}
			$values[] = '(' . implode(',', $val) . ')';
		}
		$values = implode(',', $values);
		$sql   = "INSERT INTO $table ($field) VALUES $values";
		
		$this->query($sql);
		
		if ( $this->use_mysqli ) {
			$rows_affected = mysqli_affected_rows( $this->dbh );
		} else {
			$rows_affected = mysql_affected_rows( $this->dbh );
		}
		return $this->$rows_affected = $rows_affected;
	}
	
	/**
	 * get current date or datetime
	 *
	 * $timezone timezone 需要获取哪个时区的时间, 缺省取系统配置
	 * $date_format string 获取的时间格式
	 */
	public function current_date($date_format = 'Y-m-d H:i:s', $timezone = NULL) {
		$timezone = $timezone ? $timezone : wp_timezone();
		return date_format(date_create('now', $timezone), $date_format);
	}
	
	// Copy from the parent class, the different here is throwing exception instead of printing error.
	public function bail( $message, $error_code = '500' ) {
		if ( $this->show_errors ) {
			$error = '';

			if ( $this->use_mysqli ) {
				if ( $this->dbh instanceof mysqli ) {
					$error = mysqli_error( $this->dbh );
				} elseif ( mysqli_connect_errno() ) {
					$error = mysqli_connect_error();
				}
			} else {
				if ( is_resource( $this->dbh ) ) {
					$error = mysql_error( $this->dbh );
				} else {
					$error = mysql_error();
				}
			}

			if ( $error ) {
				$message = $message;
			}

			throw new ErrorException($message);
		} else {
			if ( class_exists( 'WP_Error', false ) ) {
				$this->error = new WP_Error( $error_code, $message );
			} else {
				$this->error = $message;
			}

			return false;
		}
	}
	
	// Copy from the parent class, the different here is throwing exception instead of printing error.
	public function print_error( $str = '' ) {
		global $EZSQL_ERROR;

		if ( ! $str ) {
			if ( $this->use_mysqli ) {
				$str = mysqli_error( $this->dbh );
			} else {
				$str = mysql_error( $this->dbh );
			}
		}
		$EZSQL_ERROR[] = array(
			'query'     => $this->last_query,
			'error_str' => $str,
		);

		if ( $this->suppress_errors ) {
			return false;
		}

		$caller = $this->get_caller();
		if ( $caller ) {
			// Not translated, as this will only appear in the error log.
			$error_str = sprintf( 'WordPress database error %1$s for query %2$s made by %3$s', $str, $this->last_query, $caller );
		} else {
			$error_str = sprintf( 'WordPress database error %1$s for query %2$s', $str, $this->last_query );
		}

		error_log( $error_str );

		// Are we showing errors?
		if ( ! $this->show_errors ) {
			return false;
		}

		wp_load_translations_early();
		
		// If there is an error then take note of it.
		if ( is_multisite() ) {
			$msg = __( 'WordPress database error:' ) . $str . $this->last_query;
			throw new ErrorException($msg);

			if ( defined( 'ERRORLOGFILE' ) ) {
				error_log( $msg, 3, ERRORLOGFILE );
			}
			if ( defined( 'DIEONDBERROR' ) ) {
				wp_die( $msg );
			}
		} else {
			$str   = htmlspecialchars( $str, ENT_QUOTES );
			$query = htmlspecialchars( $this->last_query, ENT_QUOTES );
			
			$msg = __( 'WordPress database error:' ) . $str . $query;
			throw new ErrorException($msg);
		}
	}
}

?>