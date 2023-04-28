<?php
/**
 * Plugin Name: Frog CRM
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

// 激活时初始化表
register_activation_hook( __FILE__, 'create_table' );

// 加载表初始化模板
include_once('inc/init_crm_table.php');
include_once('inc/tool.php');
include_once('inc/router.php');
include_once('inc/conn.php');

global $conn;
$conn = new Conn_fm();

/*
// 注册全局异常处理函数
set_exception_handler('fm_exception_cb');
function global_exception_cb(Throwable $exception) {
	global $conn;
	// 有事务时先回滚,再报错
	$conn->rollback();
	echo "Uncaught exception: " , $exception->getMessage(), "\n";
}
*/

// select
function cust_select($request) {
	global $wpdb;
	// 获取参数, 解析成数组
	$re = $request->get_json_params();
	$ret = fm_check_null($re, ['where']);
	if ($ret) {
		return $ret;
	}
    $table_name = 'cust';
    $results = $wpdb->get_results( "SELECT cust_id FROM cust limit 1" );
    return $results;
}

// insert
function cust_insert($request) {
	global $conn;
	// $conn->start();
	try {
		$request = $request->get_json_params();
		$dataArr = wp_unslash($request);
		
		// 获取用户
		$userID = get_current_user_id();
		
		// 注册操作人ID
		$dataArr['reg_opr_id'] = $userID;
		// 顾客状态
		$dataArr['cust_status'] = 'A';
		// 顾客级别
		$dataArr['cust_grd'] = 'A';
		
		$table = 'cust';
		//$row = $wpdb->insert(
		//    $table,
		//    $dataArr
		//);
		$res_data = ['total'=> $row, 'msg'=> '', 'status' => 200];
		$response = new WP_REST_Response($res_data);
		$result = $conn->get_results("select * from test where name = '5555555'");
		
		$res = $conn->insert_rows(
			'test',
			array('name'),
			array('string'),
			array(
				array('aa', 'ddd'),
				array('bb'),
				array('cc'),
			),
		);
		return $result;
		// 抛出异常
		// throw new ErrorException($conn->last_error);
		// $conn->rollback();		
	} catch (Exception $err) {
		echo $err->getMessage();
		$conn->rollback();
	} finally {
		$conn->commit();
	}
}

// demo
function demo($request) {
	global $conn;
	$conn->$show_errors = true;
	$conn->start();
	try {
		$request = $request->get_json_params();
		$dataArr = wp_unslash($request);
		
		// 获取用户
		$userID = get_current_user_id();
		
		// 注册操作人ID
		$dataArr['reg_opr_id'] = $userID;
		// 顾客状态
		$dataArr['cust_status'] = 'A';
		// 顾客级别
		$dataArr['cust_grd'] = 'A';
		
		$table = 'cust';
		//$row = $wpdb->insert(
		//    $table,
		//    $dataArr
		//);
		$res_data = ['total'=> $row, 'msg'=> '', 'status' => 200];
		$response = new WP_REST_Response($res_data);
		$result = $conn->get_results("select * from test where name = '5555555'");
		print_r($result);
		/*
		$result = $conn->insert(
			'test',
			array(
				'name' => '5555555'
			),
			array(
				'%s',
			),
		);
		*/
		// 抛出异常
		// throw new ErrorException($conn->last_error);
		// $conn->rollback();		
	} catch (Exception $err) {
		echo $err->getMessage();
		$conn->rollback();
	} finally {
		$conn->commit();
	}
}

/**
 * 响应返回之前进行事务处理
 * wp-includes\rest-api\class-wp-rest-server.php
 */
function fm_do_pre_response($response) {
	print_r($request);
	global $conn;
	if (is_wp_error( $response )) {
		$conn->rollback();
	} else {
		$conn->commit();
	}
	return $response;
}

add_filter('rest_request_after_callbacks', 'fm_do_pre_response', 10, 2);

?>