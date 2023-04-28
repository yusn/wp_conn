<?php
	/** 
	 * 日期格式检查
	 * https://www.php.net/manual/zh/function.checkdate.php
	 */

	function fm_check_date($date, $format = 'Y-m-d H:i:s') {
		$d = DateTime::createFromFormat($format, $date);
		return $d && $d->format($format) == $date;
	}
	
	/** 
	 * 数组空值检查
	 * $array 要检查的目标数组
	 * $key_array 由期望目标数组中不能为空的键组成的数组
	 */
	function fm_check_null($array, $key_array) {
		if (!count($key_array)) {
			return;
		}
		$result = [];
		foreach ($key_array as $key => $val) {
			$check = $array[$val];
			// [] === $check 也不运行空数组或对象
			if (null === $check || '' === $check) {
				$result[] = $val . '不允许为空';
			}
		}
		if (count($result)) {
			return fm_die(-1, implode(',', $result));
		}
	}
	
	/** 
	 * 返回错误
	 * $code 错误代码
	 * $message 错误信息
	 */
	function fm_die($code = -1, $message = NULL) {
		// https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/#permissions-callback
		return new WP_Error($code , $message);
	}
?>