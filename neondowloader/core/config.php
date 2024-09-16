<?php
error_reporting(0);
set_time_limit(60);

define("PROXIES", dirname(__FILE__).'/proxies.txt');
define("YT_API_KEY", "youtube_access_key");

require_once($_SERVER['DOCUMENT_ROOT'] . "/core/orm.php");

$hostname = "localhost";
$database = "database_name";
$username = "username";
$password = "password";

$db = new ORM($hostname, $username, $password, $database);

$config = $db->table("config")->select()->execute()[0];
$bot_token = $config['bot_token'];
$developer_tid = $config['developer_tid'];
$maintance = $config['maintance'];
$bot_username = $config['bot_username'];
$super_user_tids = is_null($config['super_user_tid']) ? $config['super_user_tid'] : explode(",", $config['super_user_tid']);
$now = date("Y-m-d H:i:s");
