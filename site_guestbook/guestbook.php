<?php

$g_pageforms = ["entry" => "entry_onsubmit"];


function entry_onsubmit($param, $post) {
	$data = ["msglist" => gettable("select * from site_guestbook")];
	$scr = new Scriptor("site_guestbook/guestbook.html",  $data);
	if (($post = $scr->validate("entry", $post)) === false) {
		return $scr->result();
	}
	dbinsert("site_guestbook", ["name" => $post["name"], "content" => $post["content"] ]);
	return child_render($param);
}

function child_render($param) {
	$data = ["msglist" => gettable("select * from site_guestbook")];
	$scr = new Scriptor("site_guestbook/guestbook.html", $data );
	return $scr->result();
}


?>