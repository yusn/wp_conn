<?php
/**
 * Plugin Name: Little Frog
 * Plugin URI: https://muguayuan.com/2018/12186.html
 * Description: CRM 系统
 * Version: 1.0
 * Author URI:  https://muguayuan.com
 * License:     GPLv2
 */
add_filter('rest_url_prefix', 'rename_frog_crm_url_prefix'); 
function rename_frog_crm_url_prefix() {
	return 'api';
}

// 
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
	// rollback
	$conn->rollback();
	echo "Uncaught exception: " , $exception->getMessage(), "\n";
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
		print($conn->conn_id . "\n");
		print($conn->is_auto_commit);
		$conn->query("drop table if exists stu");
		$conn->query("create table if not exists stu (id int not null AUTO_INCREMENT, name varchar(20) null, PRIMARY KEY (id))");
		$conn->query("insert into stu (name) values ('Jim')");
		print($conn->rows_affected);
		$conn->commit();
		print($conn->conn_id);
		$conn->auto_commit();
		$conn->query("update stu set name ='Dave' where name = 'Jim'");
		// throw new ErrorException('test');
	} catch (Exception $err) {
		return $err;
	}
}

/**
 * Handle transaction immediately after executing any REST API
 * @see https://developer.wordpress.org/reference/hooks/rest_request_after_callbacks/
 */
function frog_handle_transaction($response) {
	global $conn;
	if (is_wp_error( $response ) || $response instanceof Exception ) {
		// rollback
		$conn->rollback();
		if ($response instanceof Exception) {
			$response = new WP_REST_Response(
				array(
					'code'    => -1,
					'message' => $response->getMessage(),
					'data'    => null,
				)
			);
		}
	} else {
		// commit
		$conn->commit();
		// Closes the current database connection 
		$conn->close();
	}
	return $response;
}

add_filter('rest_request_after_callbacks', 'frog_handle_transaction', 9, 1);

/**
 * Start transaction immediately before executing any REST API
 * @see https://developer.wordpress.org/reference/hooks/rest_request_before_callbacks/
 */
function frog_start_transaction($response) {
	global $conn;
	// Start transaction
	$conn->start();
	return $response;
}

add_filter('rest_request_before_callbacks', 'frog_start_transaction', 9, 1);

?>