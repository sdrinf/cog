<?php
// called at the beginning of each request
// inserts a new record based on visitor
function log_start() {
	global  $g_log_id;
	$g_log_id = dbinsert("sys_logs", [
		"ip" => $_SERVER["REMOTE_ADDR"],
		"agent" => isset($_SERVER["HTTP_USER_AGENT"])?($_SERVER["HTTP_USER_AGENT"]):(""),
		"referer" => isset($_SERVER["HTTP_REFERER"])?($_SERVER["HTTP_REFERER"]):(""),
		"url" => $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"],
		"permsid" => session_id()
	]);
	return true;
}

?>