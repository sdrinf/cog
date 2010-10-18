<?php
// ---------------------------------------------------------
//  Logging module, v2.1
// ---------------------------------------------------------
// dependencies: sql
// call at the beginning, and the end of each request; also measures performance

$g_logstart = 0;
// starts the global timer
function log_start() {
	global $g_logstart;
	$g_logstart = array_sum(explode(' ', microtime()));
	return true;
}

function log_end() {
	global $_SERVER, $g_logstart;
	$total = array_sum(explode(' ', microtime())) - $g_logstart;
	dbinsert("sys_logs", array(
		"ip" => $_SERVER["REMOTE_ADDR"],
		"agent" => isset($_SERVER["HTTP_USER_AGENT"])?($_SERVER["HTTP_USER_AGENT"]):(""),
		"referer" => isset($_SERVER["HTTP_REFERER"])?($_SERVER["HTTP_REFERER"]):(""),
		"url" => $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"],
		"runtime" => $total,
		"permsid" => session_id()
	));
	return true; 
}




?>