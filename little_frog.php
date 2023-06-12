<?php
/**
 * Plugin Name: Little Frog
 * Plugin URI: https://muguayuan.com/2023/21649.html
 * Description: 在 WordPress 中实现 DML 事务
 * Version: 1.0.1
 * Author URI:  https://muguayuan.com
 * License:     GPLv2
 */

include_once('inc/conn.php');

// register demo API 
add_action('rest_api_init', 'reg_frog_router');

function reg_frog_router() {
	register_rest_route(
		'little_frog/v1',
		'/demo',
		array(
			'methods'  => 'POST',
			'callback' => 'demo',
			'permission_callback' => '__return_true',
		)
	);
}

global $conn;
$conn = new Conn_frog();

/**
 * Sets the default exception handler if an exception is not caught within a try/catch block.
 *  Execution will stop after the callback is called.
 */
set_exception_handler('frog_global_exception_cb');
function frog_global_exception_cb(Throwable $exception) {
	global $conn;
	$conn->rollback();
	$err = "Uncaught exceptiod: " . $exception->getMessage();
	echo $err;
}

/**
 * demo
 */
function demo($request) {
	global $conn;
	$conn->$show_errors = true;
	try {
		$request = $request->get_json_params();
		$dataArr = wp_unslash($request);
		
		// 获取当前用户
		$userID = get_current_user_id();
		
		$res_data = ['total'=> $row, 'msg'=> '', 'status' => 200];
		$response = new WP_REST_Response($res_data);
		print('conn_id(the current database connection ID(thread ID)):' . $conn->conn_id . "\n\n");
		print($conn->is_auto_commit);
		$conn->query("drop table if exists fm_demo");
		echo 'drop table if exists fm_demo' . "\n";
		$conn->query("create table if not exists fm_demo (id int not null AUTO_INCREMENT, name varchar(20) null, PRIMARY KEY (id))");
		echo 'create table if not exists fm_demo (id int not null AUTO_INCREMENT, name varchar(20) null, PRIMARY KEY (id))' . "\n";
		$conn->query("insert into fm_demo (name) values ('Jim')");
		echo "insert into fm_demo (name) values ('Jim')" . "\n";
		print('影响行数:' . $conn->rows_affected . "\n");
		$conn->commit();
		echo 'commit' . "\n";
		$conn->query("update fm_demo set name ='Dave' where name = 'Jim'");
		echo "update fm_demo set name ='Dave' where name = 'Jim'" . "\n";
		echo "throw Exception: 下一步将回滚";
		throw new ErrorException('test');
	} catch (Exception $err) {
		return $err;
	}
}

/**
 * Handle transaction immediately after executing any REST API
 * @see https://developer.wordpress.org/reference/hooks/rest_request_after_callbacks/
 */
function frog_handle_transaction( $response, $handler, $request ) {
	global $conn;
	$total = null;
	if (is_wp_error( $response ) || $response instanceof Exception ) {
		// rollback
		$conn->rollback();
		$conn->close();
		if ($response instanceof Exception) {
			$response = new WP_REST_Response(
				array(
					'code'    => -1,
					'message' => $response->getMessage(),
					'total'   => $total,
					'data'    => null,
					'request' => $request->get_params(),
					'method'  => $request->get_method(),
					'request_id' => $request->get_header( 'request_id' ),
				)
			);
		}
	} else {
		// commit
		$conn->commit();
		// Closes the current database connection 
		$conn->close();
		if ( empty($response) ) {
			$response = new WP_REST_Response(
				array(
					'code'    => 0,
					'message' => null,
					'total'   => 0,
					'data'    => [],
					'request' => $request->get_params(),
					'method'  => $request->get_method(),
					'request_id' => $request->get_header( 'request_id' ),
				)
			);
		} else {
			$response = new WP_REST_Response(
				array(
					'code'    => 0,
					'message' => is_array($response) ? $response['message'] : null,
					'total'   => is_array($response) ? $response['total'] : count($response),
					'data'    => $response,
					'request' => $request->get_params(),
					'method'  => $request->get_method(),
					'request_id' => $request->get_header( 'request_id' ),
				)
			);
		}
	}
	return $response;
}

add_filter('rest_request_after_callbacks', 'frog_handle_transaction', 9, 3);

/**
 * Start transaction immediately before executing any REST API
 * @see https://developer.wordpress.org/reference/hooks/rest_request_before_callbacks/
 */
function frog_start_transaction( $response, $handler, $request ) {
	global $conn;
	
	/**
	* generate request ID for current request
	*/
	function _get_request_id($conn) {
		static $cache_time, $seq;
		$current_time = $conn->get_current_date('mdHis');
		if ($cache_time === $current_time) {
			$seq++;
		} else {
			$cache_time = $current_time;
			$seq = 1;
		}
		// 当前时间的 16 进制表示拼接
		return dechex($current_time) . '_' . $seq . '_' . get_brave_hash(rand(8, 8));
	}
	
	$request_id = _get_request_id($conn);
	$request->add_header( 'request_id', $request_id );
	$conn_info = array('request_id' => $request_id);
	$conn->start($conn_info);
	// print_r($conn->session);
	return $response;
}

add_filter('rest_request_before_callbacks', 'frog_start_transaction', 9, 3);

?>