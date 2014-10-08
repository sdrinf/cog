<?php

function child_render($param) {
	// Main API trunk
	$user_api = [
		"variant_set" => function($name, $var) {
			// enrolls user in given variant
			if (!http_user_auth("admin", "admin"))
				return redirect("/");
			$cv = new Variant($name);
			if (!ctype_alnum($var))
				hack_sign("Setting variant to non-numeric value ".$var);
			$cv->set($var);
			return redirect($cv->get_url());
		}
	];
	if (isset($_GET["func"])) {
		return api_demux_call($user_api);
	}
	return ["res" => "0", "err" => "No function to call."];
}


?>