<?php
/** 
 * wpdb 实现事务 transaction
 * https://core.trac.wordpress.org/ticket/9422
 * https://core.trac.wordpress.org/attachment/ticket/9422/9422-new-wpdb-methods.diff
 */

class Conn_fm extends wpdb {
	public function __construct() {
		$dbuser     = defined( 'DB_USER' ) ? DB_USER : '';
		$dbpassword = defined( 'DB_PASSWORD' ) ? DB_PASSWORD : '';
		$dbname     = defined( 'DB_NAME' ) ? DB_NAME : '';
		$dbhost     = defined( 'DB_HOST' ) ? DB_HOST : '';
		
		// 在父类初始化, 获取到链接
		parent::__construct($dbuser, $dbpassword, $dbname, $dbhost);
		
		// 初始化之后即启动事务
		$this->start();
	}
	
	/**
	 * check if current session has transaction id
	 */
	protected $has_tx_id = false;
	
	/**
	 * Show SQL/DB errors.
	 */
	public $show_errors = true;
	
	// 启动事务
	public function start() {
		if ( $this->use_mysqli ) {
			$res = mysqli_query( $this->dbh, 'START TRANSACTION' );
		} else {
			$res = mysql_query( 'START TRANSACTION', $this->dbh );
		}
		$this->has_tx_id = true;
 	}
	
	// 提交
	public function commit() {
		echo "\nCOMMIT\n";
		if ( $this->use_mysqli ) {
			$res = mysqli_query( $this->dbh, 'COMMIT' );
		} else {
			$res = mysql_query( 'COMMIT', $this->dbh );
		}
 	}
	
	// 回滚
	public function rollback() {
		echo "\nROLLBACK\n";
		if ( $this->use_mysqli ) {
			$res = mysqli_query( $this->dbh, 'ROLLBACK' );
		} else {
			$res = mysql_query( 'ROLLBACK', $this->dbh );
		}
 	}
	
	// 自动提交
	public function auto_commit() {
		echo "\nAUTO COMMIT\n";
		if ( $this->use_mysqli ) {
			$res = mysqli_query( $this->dbh, 'COMMIT' );
		} else {
			$res = mysql_query( 'COMMIT', $this->dbh );
		}
 	}
	
	/**
	 * Insert multiple rows
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
	 * 获取日期/日期时间
	 * $timezone timezone 需要获取哪个时区的时间, 缺省取系统配置
	 * $date_format string 获取的时间格式
	 */
	public function current_date($date_format = 'Y-m-d H:i:s', $timezone = NULL) {
		$timezone = $timezone ? $timezone : wp_timezone();
		return date_format(date_create('now', $timezone), $date_format);
	}
	
	// Copy from the parent class, The difference here is that an exception is thrown
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
	
	// Copy from the parent class, The difference here is that an exception is thrown
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
		
		// rollback
		$this->rollback();
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