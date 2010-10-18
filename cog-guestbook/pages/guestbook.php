<?php

$g_pageforms = array("entry" => "entry_onsubmit");


function entry_onsubmit($param, $post) {
	$data = array("msglist" => gettable("select * from guestbook"));
	$scr = new Splittest("pages/guestbook.html",  $data);
	if (!$scr->validate("entry", $post)) {  
		return $scr->result();
	}
	dbinsert("guestbook", array("name" => $post["name"], "content" => $post["content"]));
	return child_render($param); 
}

function child_render($param) {
	$data = array("msglist" => gettable("select * from guestbook"));
	$scr = new Splittest("pages/guestbook.html", $data );   
	return $scr->result(); 
}


?>