<?php

function child_render($param) {
	variant_hitgoal("scr:purchasetest");
	$scr = new Scriptor("site_guestbook/thanks.html");
	return $scr->result();
}

?>