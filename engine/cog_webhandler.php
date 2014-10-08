<?php
// ---------------------------------------------------------
//  WebHandler v1.5
//  A simple&stupid framed page generator
// ---------------------------------------------------------
// Call with array(URL => target)

class WebHandler {
	var $regparams = null;
	var $urlmaps = array();
	var $defaults = array();
	var $frame = "";

	// Maps the URL to target handler
	function map($url) {
		foreach ($this->urlmaps as $k=>$v) {
			// add regex prefix, and suffix
			if (!beginsWith($k, "^")) $k = "^".$k;
			if (!endsWith($k, "$")) $k = $k."$";
			if (ereg($k, $url, $this->regparams)) {
				return $v;
			}
		}
		return null;
	}

	// returns true, if there's a match for requested URL
	function hasmatch() {
		return ($this->map(explode("?",$_SERVER["REQUEST_URI"])[0]) != null);
	}

	// evaluates the page, and returns the complete HTML, or JSON response
	function result() {
		global $g_cfg, $g_page, $g_pageforms;
		$g_page = $this->defaults;

		// GET-less URI
		$uri = explode("?",$_SERVER["REQUEST_URI"]);
		$uri = $uri[0];

		$cm = $this->map($uri);
		list($incfn, $childfunc) = ( (is_array($cm))?($cm):(array($cm, null) ) );
		// Rule 1: functions are called directly
		if ( $incfn instanceof Closure)
			$g_page["child"] = $incfn;
		// Rule 2: plain html files are provided by scriptor, and framed up
		else if (endsWith($incfn,".html")) {
			$scr = new Scriptor($incfn, array() );
			$g_page["child"] = $scr->result();
			$incfn = null;
		}
		// if it's not a php file, just return it's contents
		else if ((!endsWith($incfn,".php")) && (file_exists($incfn))) {

			// determine content-type via file extension
			$mimetype = "text/plain; charset=utf-8";
			if (endsWith($incfn,".ico")) $mimetype = "image/x-icon";
			if (endsWith($incfn,".xml")) $mimetype = "application/xhtml+xml; charset=UTF-8";

			header("Content-Type: ".$mimetype);
			return file_get_contents($incfn);
		}
		// everything from hereon assumed to be UTF-8 string
		header("Content-Type: text/html; charset=UTF-8");
		// include optional handler file; this gets merged in the global function space
		if (($incfn != null) && (!is_callable($incfn) && (file_exists($incfn))))
			include_once $incfn;
		// merge child_render into frame
		if (!isset($g_page["child"]) && (is_callable("child_render")))
			$g_page["child"] = function($params) { return child_render($params); };
		if (!isset($g_page["child"]))
			internal_error("No renderer for URL: ".$uri);
		// handle forms
		if ((isset($_POST["submitedForm"])) && (isset($g_pageforms[$_POST["submitedForm"]] )))
			$g_page["child"] = $g_pageforms[$_POST["submitedForm"]]($this->regparams, $_POST);
		if ($g_page["child"] instanceof Closure)
			$g_page["child"] = $g_page["child"]($this->regparams);
		// results are either:
		// - null for redirection,
		// - an array for ajax endpoints; or
		// - an UTF-8 string containing HTML output
		if ($g_page["child"] == null)
			return "";
		if (is_array($g_page["child"])) {
			// JSONP extension
			// IE file uploading requires text/html
			header("Content-Type: application/json; charset=UTF-8");
			if ((isset($_GET["perfstats"])) && ($g_cfg["debug"] == true )) {
				global $g_logstart, $pagequeries;
				$g_logend = array_sum(explode(' ', microtime()));
				$pagegen = $g_logend - $g_logstart;
				$g_page["child"]["__perfstats"] = ["total" => $pagegen, "breakdown" => $pagequeries];
			}
			if (isset($_GET["callback"])) {
				return $_GET["callback"].'('.json_encode($g_page["child"]).')';
			} else {
				return json_encode($g_page["child"]);
			}
		}
		// format HTML results into the frame
		$scr = new Scriptor($g_page["frame"] , $g_page);
		return $scr->result();
	}

	function WebHandler($maps, $defaults, $frame = null) {
		$this->defaults = $defaults; // child pages are allowed to manipulate this
		$this->urlmaps = $maps;
		if ($frame != null)
			$this->defaults["frame"] = $frame;
	}
}


// API demuxer for dynamic function calling
function api_demux_call($export_func, $externally_accessible = false) {
	$params = $_GET;
	if (($externally_accessible == false) && (isset($_SERVER["HTTP_REFERER"]))) {
		if (parse_url($_SERVER["HTTP_REFERER"], PHP_URL_HOST) != $_SERVER["SERVER_NAME"]) {
			hack_sign("Cross-site forgery");
		}
	}
	if (!isset($params["func"])) {
		return array("error" => "Invalid call: no function specified");
	}
	if (!array_key_exists($params["func"], $export_func)) {
		return array("error" => "Invalid call: no such function defined", "notfound" => $params["func"] );
	}
	// build up argument list using the function's parameter list via reflection
	$args = array();
	$reflect = new ReflectionFunction($export_func[$params["func"]]);
	foreach ($reflect->getParameters() as $ps) {
		if (!isset($params[$ps->name])) {
			header("HTTP/1.1 501 Not Implemented");
			return array("error" => "Invalid call: parameter ".$ps->name." undefined");
		}
		$args []= $params[$ps->name];
	}

	// function is already checked for being exported; call it with given parameter list
	return call_user_func_array($export_func[$params["func"]], $args);
}

// Simple http user authentication
function http_user_auth($username, $pwd) {
	if (!isset($_SERVER['PHP_AUTH_USER'])) {
		header('WWW-Authenticate: Basic realm="private area"');
		header('HTTP/1.0 401 Unauthorized');
		echo 'Authentication required';
		exit;
	}
	if (($_SERVER["PHP_AUTH_USER"] != $username) ||
			($_SERVER["PHP_AUTH_PW"] != $pwd)) {
		header('WWW-Authenticate: Basic realm="private area"');
		header('HTTP/1.0 401 Unauthorized');
		echo 'Authentication required';
		exit;
	}
	return true;
}


?>