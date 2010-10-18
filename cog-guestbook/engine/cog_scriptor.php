<?php
// -------------------------------------------------------
//  Scriptor module v3.1, zero-dependencies
// -------------------------------------------------------

// A very simple scripting engine
class Scriptor {

	var $template = "";
	var $templatefilename = "";
	var $vals = array();
	var $ctrls = array();
	
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
// update() inserts new data, and reset the resulting template 

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
		return $this->_validatePassed;
	}
	
	// finds the ending index for the given tag from the given position
	function XML_Get_Endindex($script, $from,$tag) {
		$cdeepness = 1;
		$cpos = $from;
		while (true) {
			$res = strpos($script,"</".$tag.">",$cpos);
			if ($res === false) {
				internal_error("no closing for tag: ".$tag." while processing ".$this->fn);
			}
			$innerTagIndex = strpos($script,"<".$tag,$cpos);
			if (($innerTagIndex === false) || ($innerTagIndex > $res)) {
				// no nested tags
				return $res;
			}
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
			if (!isset($this->ctrls[$ctrl]))
				internal_error("Control ".$ctrl." not implemented");
			// create an array from the parameters
			$tagparams = array();
			foreach ($prs as $i) {
				$j = explode("=", $i, 2);
				$j[1] = trim($j[1],'"'); 
				$tagparams[$j[0]] = $j[1];
			}
			$endindex = $this->xml_get_endindex($script, $nindex, "cog:".$ctrl);
			$subscript = substr($script, $nindex + 1, ($endindex - ($nindex + 1)));
			$ccontrol = $this->ctrls[$ctrl];
			$page .= $this->$ccontrol($tagparams, $subscript, $vals);
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
	
	// statement evaluation
	function ctrl_ifeval($param, $script, $vals) {
		if (isset($vals[$param["name"]]) && ($vals[$param["name"]] == $param["value"])) {
			return $this->ev($script, $vals);
		}
		return "";
	}
	
	// true, if param has vals["name"], and it's not None
	function ctrl_ifset($param, $script, $vals) {
		$valname = $param["name"];
		if (isset($vals[$valname]))
			if ($vals[$valname] != null)
				return $this->ev($script, $vals);
		return "";
	}
	
	// iterater through a datasource
	function ctrl_iterator($param, $script, $vals) {
		$citer = $vals[$param["datasource"]];
		$res = "";
		$cnt = 0;
		foreach ($citer as $crow) {
			// $tpl = clone $vals;
			$res .= $this->ev($script, $crow);
		}
		return $res;	
	}
	
	// form handling: Creates a cookie-secure form
	function ctrl_form($param, $script, $vals) {
		$res = "<form ";
		foreach ($param as $k=>$v) 
			$res .= $k.'="'.$v.'" ';
		$res .= ">\n";
		if (isset($param["name"])) {
			$res .= '<input type="hidden" name="submitedForm" value="'.$param["name"].'" />'."\n";
		}
		$res .= '<input type="hidden" name="cogvalidation" value="'.session_id().'" />'."\n"; 
		// validation registering
		if ((isset($param["name"])) && ($this->_validateForm != null) && 
			($this->_validateForm == $param["name"])) {
				$this->_validatePassed = true;
				$this->_isvalidating = true;
				if ($this->_validateParams != null) {
					foreach ($this->_validateParams as $k=>$v) {
						$v = str_replace('"', "&quot;", $v); 
						$v = str_replace("<", "&lt;", $v);
						$v = str_replace(">", "&gt;", $v);
						$this->_validateParams[$k] = $v;
						$vals["old".$k] = $this->_validateParams[$k];
					}
				}
		}
		// evaluate children controls
		$res .= $this->ev($script, $vals); 
		$this->_isvalidating = false; 
		$res .= "</form>";
		return $res;
	}
	
	// validates a form input
	function ctrl_validator($param, $script, &$vals) {
		if (!$this->_isvalidating) {
			if (!isset($vals["old".$param["target"]]))
				$vals["old".$param["target"]] = "";  
			return "";
		}
		if (!isset($this->_validateParams[$param["target"]])) {
			$this->_validatePassed = false;
			return $this->ev($script, $vals);
		}
		$vf = "validate_".$param["func"];
		if ($vf($this->_validateParams[$param["target"]]))
			return "";
		$this->_validatePassed = false;
		return $this->ev($script, $vals);
	}
	
	// initalizes a new scriptor with given file, and values
	function Scriptor($fn, $vals = array() ) {
		$this->vals = $vals;
		$this->ctrls = array(
			"if" => "ctrl_ifeval",
			"ifset" => "ctrl_ifset",
			"iterator" => "ctrl_iterator",
			"form" => "ctrl_form",
			"validator" => "ctrl_validator"
		);
		if ($fn != null) {
			$this->template = file_get_contents($fn); 
		}
	}

}

// common validators
function validate_nonemptystring($arg) {
	return (trim($arg) != "");
}

function validate_alphanumerical($arg) {
	return (ereg("^([A-Za-z0-9]*)$",$arg));
}


function validate_numberonly($str) {
	return is_numeric($str);
}

function validate_positive_number_only($str) {
	return ((is_numeric($str)) && (((int)$str) > 0));
}


// validate email-addresses 
function validate_emailaddress($email) {
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