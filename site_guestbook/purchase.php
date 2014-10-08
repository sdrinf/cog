<?php
$g_pageforms = array("emailsubscribe" => "subscribe_onsubmit");

function subscribe_onsubmit($param, $post) {
	global $_SERVER;
	$scr = new Scriptor("site_guestbook/purchase.html");
	if ( ($post = $scr->validate("emailsubscribe", $post)) === false) {
		return $scr->result();
	}
	dbinsert("beta_emails", ["email" => $post["email"], "landing" => $_SERVER["SERVER_NAME"]]);
	return redirect("/thanks");
}

function child_render($param) {
	$scr = new Scriptor("site_guestbook/purchase.html");
	return $scr->result();
}

?>