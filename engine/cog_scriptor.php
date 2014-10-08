<?php
// -------------------------------------------------------
//  Scriptor module v3.3
// Changelog:
//  3.6 @2013.08.26: added ifeq
//	3.5 @2011.12.11: dynamic controls, similar to validator handling
//  3.4 @2011.12.09: variable inheritance for iterators
//  3.3 @2011.08.18: dynamic function include
//  3.2 @2011.06.26: cross-site scripting logging
//  3.1 @2011.03.14
// -------------------------------------------------------
// A very simple scripting engine

class Scriptor {

	var $template = "";
	var $templatefilename = "";
	var $vals = array();

	var $_result = null;

	// validation private variables
	var $_isvalidating = false;
	var $_validateForm = null;
	var $_validateParams = null;
	var $_validatePassed = true;


// scriptor basically has three main functions:
// the constructor gets a template file, and a list of data points
// validate() validates a given form, and return true / false
// result() returns the fullfilled template
// update() inserts new data, and updates the resulting template

	function result() {
		if ($this->_result == null)
			$this->_result = $this->ev($this->template, $this->vals);
		return $this->_result;
	}

	// validates the given script
	function validate($formname, $params) {
		$this->_validateForm = $formname;
		$this->_validateParams = $params;
		$this->_result =  $this->ev($this->template, $this->vals);
		return ($this->_validatePassed ? ($this->_validateParams):(false));
	}

	// inserts new data, and updates the template
	function update($vals) {
		foreach ($vals as $v => $k)
			$this->vals[$v] = $k;
		if ($this->_result != null)
			$this->_result = $this->ev($this->_result, $this->vals);
		return true;
	}

	// finds the ending index for the given tag from the given position
	function XML_Get_Endindex($script, $from,$tag) {
		$cdeepness = 1;
		$cpos = $innertagpos = $from;
		while (true) {
			$res = strpos($script,"</".$tag.">",$cpos);
			if ($res === false) {
				internal_error("no closing for tag: ".$tag." while processing ".$this->templatefilename);
			}
			$innerTagIndex = strpos($script,"<".$tag." ",$innertagpos);
			if (($innerTagIndex === false) || ($innerTagIndex > $res)) {
				// no nested tags
				return $res;
			}
			$innertagpos = $innerTagIndex + 1;
			// we have a nested tag, so we go find the next ending
			$cpos = $res + 1;
		}
		return $res;
	}

	// evaluates the result of template + array
	function ev($script, $vals) {
		$page = "";
		$cpos = 0;
		while (!(strpos($script,"<cog:",$cpos) === false)) {
			$index = strpos($script,"<cog:",$cpos);
			$page .= substr($script,$cpos,$index - $cpos);
			$nindex = strpos($script,">",$index);
			$cmd = substr($script,$index+5,($nindex-$index-5));

			$param = explode(" ",$cmd,2);
			$ctrl = $param[0];
			$prs = array_filter(explode(" ",$param[1]));

			// create an array from the parameters
			$tagparams = array();
			foreach ($prs as $i) {
				$j = explode("=", $i, 2);
				$j[1] = trim($j[1],'"');
				$tagparams[$j[0]] = $j[1];
			}
			$endindex = $this->xml_get_endindex($script, $nindex, "cog:".$ctrl);
			$subscript = substr($script, $nindex + 1, ($endindex - ($nindex + 1)));

			$ccontrol = "ctrl_".$ctrl;
			$page .= $ccontrol($this, $tagparams, $subscript, $vals);
			$cpos = $endindex + strlen($ctrl) + 7;
		}
		$page .= substr($script, $cpos, strlen($script) - $cpos);
		if ($vals != null) {
			foreach ($vals as $key => $value) {
				if (is_array($value)) continue;
				$page = str_replace("<%".$key."%>",$value,$page);
			}
		}
		return $page;
	}

	// initalizes a new scriptor with given file, and values
	function Scriptor($fn, $vals = array() ) {
		// resolve closure values
		foreach ($vals as $key => $value)
			if ( $value instanceof Closure)
				$vals[$key] = $value();
		$this->vals = $vals;
		if ($fn != null) {
			$this->templatefilename = $fn;
			$this->template = file_get_contents($fn);
		}
	}
}

// common controls

// statement evaluation
function ctrl_if($scr, $param, $script, $vals) {
	if (isset($vals[$param["name"]]) && ($vals[$param["name"]] == $param["value"])) {
		return $scr->ev($script, $vals);
	}
	return "";
}

// true, if param has vals["name"], and it's not None
function ctrl_ifset($scr, $param, $script, $vals) {
	$valname = $param["name"];
	if (isset($vals[$valname]))
		if (($vals[$valname] !== null) && ($vals[$valname] != ""))
			return $scr->ev($script, $vals);
	return "";
}

// true, if param vals["src"] equals to vals["trg"]
function ctrl_ifeq($scr, $param, $script, $vals) {
	if (!isset($vals[$param["src"]]) || !isset($vals[$param["trg"]]))
		return "";
	if ($vals[$param["src"]] == $vals[$param["trg"]])
		return $scr->ev($script, $vals);
	return "";
}

// iterater through a datasource
function ctrl_iterator($scr, $param, $script, $vals) {
	if (!array_key_exists($param["datasource"], $vals)) {
		internal_error("Datasource: ".$param["datasource"]." aren't within given values: ".print_r(array_keys($vals), true) );
	}
	$citer = $vals[$param["datasource"]];
	if (!is_array($citer))
		internal_error("Datasource: ".$param["datasource"]." - content: ".print_r($citer, true)." isn't iterable ");

	$res = "";
	$cnt = 0;
	$alter = 0;
	foreach ($citer as $crow) {
		// $tpl = clone $vals;
		$crow["alternate"] = $alter;
		$tpl = update( update( array(), $vals), $crow);
		$res .= $scr->ev($script, $tpl);
		$alter = (($alter == 0)?(1):(0));
	}
	return $res;
}

// form handling: Creates a cookie-secure form
function ctrl_form($scr, $param, $script, $vals) {
	$res = "<form ";
	foreach ($param as $k=>$v)
		$res .= $k.'="'.$v.'" ';
	$res .= ">\n";
	if (isset($param["name"])) {
		$res .= '<input type="hidden" name="submitedForm" value="'.$param["name"].'" />'."\n";
	}
	$res .= '<input type="hidden" name="cogvalidation" value="'.session_id().'" />'."\n";
	// validation registering
	if ((isset($param["name"])) && ($scr->_validateForm != null) &&
		($scr->_validateForm == $param["name"])) {
			$scr->_validatePassed = true;
			$scr->_isvalidating = true;
			// validate for cross-site scripting
			if (!array_key_exists("cogvalidation", $scr->_validateParams)) {
				internal_error("no cogvalidation in post: ".print_r($scr->_validateParams, true) );
			}
			if ($scr->_validateParams["cogvalidation"] != session_id() ) {
				internal_error("cogvalidation failed: ".print_r($scr->_validateParams, true) );
			}
			if ($scr->_validateParams != null) {
				foreach ($scr->_validateParams as $k=>$v) {
					$v = str_replace('"', "&quot;", $v);
					$v = str_replace("<", "&lt;", $v);
					$v = str_replace(">", "&gt;", $v);
					$scr->_validateParams[$k] = $v;
					$vals["old".$k] = $scr->_validateParams[$k];
				}
			}
	}
	// evaluate children controls
	$res .= $scr->ev($script, $vals);
	$scr->_isvalidating = false;
	$res .= "</form>";
	return $res;
}

// validates a form input
function ctrl_validator($scr, $param, $script, &$vals) {
	if (!$scr->_isvalidating) {
		if (!isset($vals["old".$param["target"]]))
			$vals["old".$param["target"]] = "";
		return "";
	}
	if (!isset($scr->_validateParams[$param["target"]])) {
		$scr->_validatePassed = false;
		return $scr->ev($script, $vals);
	}
	$vf = "validate_".$param["func"];
	if ($vf($scr->_validateParams[$param["target"]]))
		return "";
	$scr->_validatePassed = false;
	return $scr->ev($script, $vals);
}

// includes a whole file, and evaluates its contents
function ctrl_file($scr, $param, $script, $vals) {
	return $scr->ev(file_get_contents($param["name"]), $vals);
}

// common validators
function validate_nonemptystring($arg) {
	return (trim($arg) != "");
}

// returns true, if string is alphanumerical
function validate_alphanumerical($arg) {
	return (ereg("^([A-Za-z0-9]*)$",$arg));
}

// returns true, if string is numerical
function validate_numberonly($str) {
	return is_numeric($str);
}

// returns true, if string is numerical and positive
function validate_positive_number_only($str) {
	return ((is_numeric($str)) && (((int)$str) > 0));
}

// dummy validation: always returns true
function validate_zerovalidation($str) {
	return true;
}


// validate email-addresses
function validate_email($email) {
	// First, we check that there's one @ symbol, and that the lengths are right
	if (!ereg("^[^@]{1,64}@[^@]{1,255}$", $email)) {
		// Email invalid because wrong number of characters in one section, or wrong number of @ symbols.
		return false;
	}
	// Split it into sections to make life easier
	$email_array = explode("@", $email);
	$local_array = explode(".", $email_array[0]);
	for ($i = 0; $i < sizeof($local_array); $i++) {
		if (!ereg("^(([A-Za-z0-9!#$%&'*+/=?^_`{|}~-][A-Za-z0-9!#$%&'*+/=?^_`{|}~\.-]{0,63})|(\"[^(\\|\")]{0,62}\"))$", $local_array[$i])) {
			return false;
		}
	}
	if (!ereg("^\[?[0-9\.]+\]?$", $email_array[1])) { // Check if domain is IP. If not, it should be valid domain name
		$domain_array = explode(".", $email_array[1]);
		if (sizeof($domain_array) < 2) {
			return false; // Not enough parts to domain
		}
		for ($i = 0; $i < sizeof($domain_array); $i++) {
			if (!ereg("^(([A-Za-z0-9][A-Za-z0-9-]{0,61}[A-Za-z0-9])|([A-Za-z0-9]+))$", $domain_array[$i])) {
				return false;
			}
		}
	}
	return true;
}

// validate URLs
function validate_URL($str) {
	return preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $str);
}

?>