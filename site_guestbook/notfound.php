<?php

function child_render($param) {
	header("HTTP/1.0 404 Not Found");
	$scr = new Scriptor("site_guestbook/notfound.html");
	return $scr->result();
}

?>