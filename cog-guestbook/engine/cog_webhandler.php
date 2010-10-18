<?php
// ---------------------------------------------------------
//  WebHandler v1.2
//  A simple&stupid framed page generator
// ---------------------------------------------------------
// Call with array(URL => target)
//

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
	
	// evaluates the page, and returns the complete HTML, or JSON response
	function result() {
		global $_SERVER, $_GET, $_POST, $g_page, $g_frame, $g_pageforms;
		$g_page = $this->defaults; 
		$g_frame = $this->frame; 
		// GET-less URI
		$uri = explode("?",$_SERVER["REQUEST_URI"]); 
		$uri = $uri[0];

		$cm = $this->map($uri);  
		if ($cm == null)
			internal_error("Non-matched URL: ".$_SERVER["REQUEST_URI"]);
		$incfn = null;
		$childfunc = null;
		if (is_array($cm)) {
			$incfn = $cm[0];
			$childfunc = $cm[1];
		} else {
			$incfn = $cm; 
		}
		// Rule 1: functions are called directly
		if (is_callable($incfn)) {
			$childfunc = $incfn;
		}
		// Rule 2: plain html files are provided by scriptor, and framed up
		else if (endsWith($incfn,".html")) {
			$scr = new Scriptor($incfn, array() ); 
			// format HTML results into the frame
			$g_page["child"] = $scr->result();
			header("Content-Type: text/html; charset=UTF-8"); 
			$scr = new Scriptor($g_frame, $g_page); 
			return $scr->result();
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
		// include optional handler file; this gets merged in the global function space
		if (($incfn != null) && (!is_callable($incfn) && (file_exists($incfn))))  
			include_once $incfn;
		// default child functions
		$childfunc = (($childfunc == null) && (function_exists("child_render"))) ? ("child_render"):$childfunc;
		$childfunc = (($childfunc == null) && (function_exists("api_endpoint"))) ? ("api_endpoint"):$childfunc;
		
		if ($childfunc == null)
			internal_error("No default renderer function for URL: ".$uri); 
		// handle forms
		if ((isset($_POST["submitedForm"])) && (isset($g_pageforms[$_POST["submitedForm"]])))
			$res = $g_pageforms[$_POST["submitedForm"]]($this->regparams, $_POST);
		else 
			$res = $childfunc($this->regparams);
		// results are either:
		// - null for redirection,
		// - an array for ajax endpoints; or 
		// - an UTF-8 string containing HTML output
		if ($res == null)
			return "";
		if (is_array($res)) {
			// JSONP extension
			if (isset($_GET["callback"])) {
				return $_GET["callback"].'('.json_encode($res).')';
			} else { 
				return json_encode($res);
			}
		} 
		// format HTML results into the frame
		$g_page["child"] = $res;
		header("Content-Type: text/html; charset=UTF-8"); 
		$scr = new Scriptor($g_frame, $g_page); 
		return $scr->result();
	}
	
	function WebHandler($maps, $defaults, $frame) {
		global $g_page;
		$this->defaults = $defaults; // child pages are allowed to manipulate this
		$this->urlmaps = $maps;
		$this->frame = $frame; 
	}
}

?>