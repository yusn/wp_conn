## 插件简单
这是一个 WordPress 插件，我给它暂时命名为 wp_conn_transaction，它能让 WordPress 在 REST API 中实现事务。

## 安装插件
请插件使用，下载安装包解压到 WordPress 插件目录，并到 WordPress 后台激活插件。

## 使用方法

### 使用demo
demo 地址如下：http://example.com/?rest_route=/little_frog/v1/demo
#### 请求参数
无需参数
#### 调用说明
通过 postman 直接调用 demo 地址即可看到 demo 方法的执行日志
![image](https://github.com/yusn/little_frog/assets/11848830/3bc3eddf-66ba-413c-9758-344ae3b0a408)
## $conn 的使用方法
$conn 是一个全局变量，你可以在任何地方通过`global $conn;`来引用并使用它，默认情况下 $conn 是禁用自动提交的。
### 对事务的操作
#### 查看当前是否处于自动提交状态
```$conn->is_autocommit;```
#### 手动提交
```$conn->commit();```
#### 手动回滚
```$conn->rollback();```
#### 释放链接
```$conn->close();```
#### 开启自动提交
```$conn->set_autocommit();```

### 查看链接信息
#### 获取数据库链接id
```$conn->conn_id;```
### 获取当前用户id（WordPress登陆用户）
```$conn->get_user_id();```
### 获取当前时间
```$conn->get_current_date();```

### 操作数据库
$wpdb 无法一次性插入多行，$conn 加入了对一次性插入多行的支持。
#### 一次插入多行
```
$effected_total = $conn->insert_rows(
			'table_name', // 需要写入的表名称
			array('table_column'), // 表字段名称
			array('string'), // 数据类行
			array(
				array('aa'),
				array('bb'),
				array('cc'),
			), // 写入的数据,这里有三行数据
		);
```
