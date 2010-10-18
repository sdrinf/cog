<?php
$g_pageforms = array("emailsubscribe" => "subscribe_onsubmit");

function subscribe_onsubmit($param, $post) {
	global $_SERVER;
	$scr = new Splittest("pages/purchase.html"); 
	if (!$scr->validate("emailsubscribe", $post)) { 
		return $scr->result();
	}
	dbinsert("beta_emails", array("email" => $post["email"], "landing" => $_SERVER["SERVER_NAME"]));
	redirect("/cog-guestbook/thanks"); 
	return null;
}

function child_render($param) {
	$scr = new Splittest("pages/purchase.html");  
	return $scr->result(); 
}

?>