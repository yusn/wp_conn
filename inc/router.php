<?php
/** 
 * 注册路由
 */

add_action('rest_api_init', 'reg_fm_router');
function reg_fm_router() {
	// 查询
	register_rest_route(
		'cust/v1', '/select',
		get_fm_router_arg('select'),
	);
	// 写入
	register_rest_route(
		'cust/v1', '/insert',
		get_fm_router_arg('insert'),
	);
	// 更新
	register_rest_route(
		'cust/v1', '/update',
		get_fm_router_arg('update'),
	);
}

// 获取路由参数选项
function get_fm_router_arg($route) {
	$router_config = array(
		'select' => array(
			'methods'  => 'POST',
            'callback' => 'cust_select',
			'permission_callback' => '__return_true',
		),
		'insert' => array(
			'methods'  => 'POST',
			'callback' => 'cust_insert',
			'permission_callback' => '__return_true',
		),
		'update' => array(
			'methods'  => 'POST',
			'callback' => 'cust_insert',
			'permission_callback' => '__return_true',
		),
	);
	
	return $router_config[$route];
}
?>