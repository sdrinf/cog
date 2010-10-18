<?php
// -------------------------------------------------------
//  Commonly used functions v3.0, code cleanup
// -------------------------------------------------------


// -------------------------------------------------------
// this function returns a random string with k length
// -------------------------------------------------------
function random_string($k) {
	$str = "";
	for ($i=0;$i<$k;$i++) {
		$randnum = mt_rand(0,61);
			if ($randnum < 10)
				$str .= $randnum;
			else if ($randnum < 36)
				$str .= chr($randnum+55);
			else
				$str .= chr($randnum+61);
	}
	return $str;
}


// -------------------------------------------------------
// string prefix, and suffix check
// -------------------------------------------------------

function beginsWith( $str, $sub ) {
   return ( substr( $str, 0, strlen( $sub ) ) === $sub );
}

function endsWith( $str, $sub ) {
   return ( substr( $str, strlen( $str ) - strlen( $sub ) ) === $sub );
}

// updates the variables in r from src, similiar to python's update function
function update($r, $src) {
	foreach ($src as $k=>$v) {
		$r[$k] = $v;
	}
	return $r;
}

// -------------------------------------------------------
// page redirection
// -------------------------------------------------------
function redirect($url) {
	header("Location: ".$url);
}

?>