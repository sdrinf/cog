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
// optionally updates specific fields only
function update($r, $src, $fields = null) {
	if ($fields == null) {
		$fields = array_keys($src);
	}
	foreach ($fields as $k) {
		$r[$k] = $src[$k];
	}
	return $r;
}


// creates an array indexed by $str
function indexby($str, $arr, $isunique = false) {
	$res = [];
	foreach ($arr as $k) {
		if ($isunique)
			$res[$k[$str]] = $k;
		else if (!isset($res[$k[$str]]))
			$res[$k[$str]] = [$k];
		else
			$res[$k[$str]] []= $k;
	}
	return $res;
}

// -------------------------------------------------------
// page redirection
// -------------------------------------------------------
function redirect($url) {
	header("Location: ".$url);
	return null;
}

?>