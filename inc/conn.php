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
	public $is_autocommit = false;
	
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
		// 禁用自动提交
		$res = $this->dbh->autocommit(FALSE);
		if ( ! $res ) {
			if ( $this->use_mysqli ) {
				$err = 'Failed to set autocommit: ' . mysqli_error( $this->dbh );
				
			} else {
				$err = 'Failed to set autocommit: ' . mysql_error( $this->dbh );
			}
			$this->print_error($err);

		}
		
		$this->conn_id = $this->dbh->thread_id;
 	}
	
	/**
	 * COMMIT TRANSACTION
	 *
	 * Commits the current transaction, making its changes permanent
	 *
	 * https://www.php.net/manual/zh/mysqli.autocommit.php
	 */
	public function commit() {
		if ( $this->is_autocommit ) {
			echo 'Don\'t need to commit in AUTOCOMMIT mode!';
			return;
		}
		$res = $this->dbh->commit();
		if ( ! $res ) {
			if ( $this->use_mysqli ) {
				$err = 'Failed to commit: ' . mysqli_error( $this->dbh );
			} else {
				$err = 'Failed to commit: ' . mysql_error( $this->dbh );
			}
			$this->print_error($err);
		}
		echo "\nCOMMIT\n";
 	}
	
	/**
	 * ROLLBACK TRANSACTION
	 *
	 * rolls back the current transaction, canceling its changes.
	 * 
	 * Some statements cannot be rolled back.
	 * In general, these include data definition language (DDL) statements, such as those that create or drop databases,
	 * those that create, drop, or alter tables or stored routines.
	 *
	 * You should design your transactions not to include DDL statements.
	 * 
	 * @see https://dev.mysql.com/doc/refman/8.0/en/cannot-roll-back.html
	 * https://www.php.net/manual/zh/mysqli.rollback.php
	 */
	public function rollback() {
		if ( $this->is_autocommit ) {
			echo 'Failed to rollback in AUTOCOMMIT mode!';
			return;
		}
		$res = $this->dbh->rollback();
		if ( ! $res ) {
			if ( $this->use_mysqli ) {
				$err = 'Failed to rollback: ' . mysqli_error( $this->dbh );
			} else {
				$err = 'Failed to rollback: ' . mysql_error( $this->dbh );
			}
			$this->print_error($err);
		}
		echo "\nROLLBACK\n";
 	}
	
	/**
	 * SET AUTOCOMMIT
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
	 * 
	 */
	public function autocommit() {
		if ( $this->use_mysqli ) {
			// get autocommit variable
			$res = mysqli_query( $this->dbh, 'SELECT @@autocommit' );
			$modes_array = mysqli_fetch_array( $res );
			
			// Set autocommit mode if current running in autocommit disabled mode
			if ( ! $modes_array[0] ) {
				$res = $this->dbh->autocommit(TRUE);
				if ( ! $res ) {
					$err = 'failed to set autocommit: ' . mysqli_error( $this->dbh );
					$this->print_error($err);
				}
				$this->is_autocommit = true;
				echo "\nAUTO COMMIT\n";
			} else {
				echo 'Current session is already running in autocommit mode';
			}
		} else {
			// get autocommit variable
			$res = mysql_query( 'SELECT @@autocommit', $this->dbh );
			$mode_var = mysql_result( $res, 0 );
			
			// Set autocommit mode if current running in autocommit disabled mode
			if ( ! $mode_var ) {
				$res = $this->dbh->autocommit(TRUE);
				if ( ! $res ) {
					$err = 'failed to set autocommit: ' . mysql_error( $this->dbh );
					$this->print_error($err);
				}
				$this->is_autocommit = true;
				echo "\nAUTO COMMIT\n";
			} else {
				echo 'Current session is already running in autocommit mode';
			}
		}
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
				if ( $type[$k] === 'string' ) {
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