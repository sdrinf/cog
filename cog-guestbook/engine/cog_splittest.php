<?php
// -------------------------------------------------------
//  A/B-test extended scriptor module v2.1
//  dependencies: cog_sql, cog_scriptor
// -------------------------------------------------------

// challenge to be displayed only once per page
$splittest_challenge_displayed = false;


// utility functions for split testing


// sets participation in a given A/B test: increase sample 
// optional parameters only required from first participator
function splittest_setparticipation($name, $type, $domain = null, $url = null) {
	$cfield = "sample_control";
	if ($type == "experiment")
		$cfield = "sample_exp";
	$t = dbcommit("update sys_abtests set ".$cfield." = ".$cfield." + 1 where name = @name",
			array("name" => $name));
	if (mysql_affected_rows() == 0) {
		// new a/b test! create it automagically
		dbinsert("sys_abtests",  array("name" => $name, $cfield => 1,
							"domain" => $domain, "url" => $url));
	}
	return true; 
}

// sets the current user's is_human bit to true, and triggers all pending A/B participation
function splittest_sethuman() {
	global $_SESSION, $_SERVER;
	if ($_SESSION["_abcog_ishuman"] == 1)
		return array("set" => "ok");  
	$_SESSION["_abcog_ishuman"] = 1;
	foreach ($_SESSION as $k=>$v) {
		if (beginswith($k, "_abtest_"))
			splittest_setparticipation(substr($k, 8), $_SESSION[$k], $_SERVER["SERVER_NAME"],
				isset($_SERVER["HTTP_REFERER"])?($_SERVER["HTTP_REFERER"]):("")); 
	}
	return array("set" => "ok"); 
}

// re-set the user's A/B test participation, for testing multiple variants
function splittest_setab($param) {
	global $_GET, $_SESSION;
	if ((!isset($_GET["name"])) || (!isset($_GET["value"])))
		return null;
	$name = $_GET["name"];
	$q = gettable("select * from sys_abtests where name=@name", array("name" => $name)) ;
	if (count($q) != 1)
		return null;
	$_SESSION["_abtest_".$name] = $_GET["value"]; 
	redirect("http://".$q[0]["domain"].$q[0]["url"]);
	return null; 
}

// triggers a goal hit
function splittest_hitgoal($name) {
	global $_SESSION;
	if (($_SESSION["_abcog_ishuman"] != 1) || (!isset($_SESSION["_abhit_".$name])) ||  
			($_SESSION["_abhit_".$name] == 1))
		return false;
	$_SESSION["_abhit_".$name] = 1; 
	// if the source isn't set, do not register, and deny later participation
	if (!isset($_SESSION["_abtest_".$name]))
		return false; 
	$cfield = "cr_control";
	// increment field
	if ($_SESSION["_abtest_".$name] == "experiment")
		$cfield = "cr_exp";
	$t = dbcommit("update sys_abtests set ".$cfield." = ".$cfield." + 1 where name = @name",
			array("name" => $name));
	// no auto-creation here. call the test page itself, if needed. 
	return true;
}

// admin display

// returns the Z-score confidence description under given parameters
function splittest_admin_description($n1,$n2,$crc,$cre) {
	$HANDY_Z_SCORE_CHEATSHEET = array(
			array(0.10, 1.29),
			array(0.05, 1.65),
			array(0.01, 2.33),
			array(0.001, 3.08)
		);  
	$percentages = array(0.10 => "90%", 0.05 => "95%", 0.01 => "99%", 0.001 => "99.9%"); 
	$descriptions = array(0.10 => "fairly confident", 0.05 => "confident", 
					0.01 => "very confident", 0.001 => "extremely confident"); 
	// stolen from http://www.kalzumeus.com/2010/06/07/detecting-bots-in-javascrip/
	if (($n1 < 10) || ($n2 < 10)) {
		return "Not enough data";
	}
	$zscore = ($crc - $cre) / sqrt(  (($crc * (1-$crc)) / $n1) + (($cre * (1-$cre)) / $n2) );
	$zscore = abs($zscore);
	$found_p = null;
	for ($i = 0; $i<count($HANDY_Z_SCORE_CHEATSHEET);$i++) {
		if ($zscore > $HANDY_Z_SCORE_CHEATSHEET[$i][1])
			$found_p = $HANDY_Z_SCORE_CHEATSHEET[$i][0];
	}
	if ($found_p == null)
		return "This difference is not statistically significant. "; 
	$res = "This difference is ".$percentages[$found_p]." likely to be statistically significant, ";
	$res .=	"which means you can be ".$descriptions[$found_p]."  that it is the result of your ";
	$res .= "alternatives actually mattering, rather than ";
	$res .= "being due to random chance.  However, this statistical test can't measure how likely the currently ";
	$res .= "observed magnitude of the difference is to be accurate or not.  ";
	$res .= "It only says \"better\", not \"better by so much\".";
	return $res; 
}
// included page for reviewing current splittest results
function splittest_admin($param) {
	$t = gettable("select * from sys_abtests");
	// calculate clickthrough rates, and z-score
	for ($i=0;$i<count($t);$i++) {
		$n1 = $t[$i]["sample_control"];
		$n2 = $t[$i]["sample_exp"];
		$t[$i]["dcount"] = $n1 + $n2;
		$t[$i]["conversion_total"] = $t[$i]["cr_control"] + $t[$i]["cr_exp"];
		$t[$i]["conversion_ctr"] = round(($t[$i]["conversion_total"]*100 / $t[$i]["dcount"]),2); 
		$crc = ($n1 > 0) ? ($t[$i]["cr_control"] / $n1 ):0;
		$t[$i]["ctrc"] = round($crc*100,2);
		$cre = ($n2 > 0) ? ($t[$i]["cr_exp"] / $n2) :0; 
		$t[$i]["ctre"] = round($cre*100,2); 
		$t[$i]["descr"] = splittest_admin_description($n1,$n2,$crc,$cre);
	}
	$scr = new Scriptor("engine/cog_splittest_admin.html", array("tests" => $t));
	return $scr->result();
}


// Splittest control extensions for little scriptor 
class Splittest extends Scriptor {
	function ctrl_abtest($param, $script, $vals) {
		global $_SESSION, $_SERVER, $splittest_challenge_displayed;
		// sanity check
		if (($param["type"] != "control") && ($param["type"] != "experiment")) {
			internal_error("invalid abtest type: ".$param["type"]);
		}
		$res = "";
		if (!isset($_SESSION["_abcog_ishuman"])) {
			$_SESSION["_abcog_ishuman"] = 0;
		}
		if ((!$splittest_challenge_displayed) && ($_SESSION["_abcog_ishuman"] == 0)) {
			// send human challenge
			$res .= file_get_contents("./engine/cog_splittest_ishuman.js");
			$splittest_challenge_displayed = true;
		}
		if (!isset($_SESSION["_abtest_".$param["name"]])) {
			$_SESSION["_abtest_".$param["name"]] = rand(0,1)?("control"):("experiment");
			$_SESSION["_abhit_".$param["name"]] = 0; 
			if ($_SESSION["_abcog_ishuman"] == 1) {
				// set participation immediately for already proven humans
				splittest_setparticipation($param["name"], $_SESSION["_abtest_".$param["name"]],
							$_SERVER["SERVER_NAME"], $_SERVER["REQUEST_URI"]);
			}
		}
		// display the right test
		if ($_SESSION["_abtest_".$param["name"]] == $param["type"]) {
			return $res.$this->ev($script, $vals);
		}
		return $res;
	}
	
	// hit a goal from designer files
	function ctrl_abgoal($param, $script, $vals) {
		splittest_hitgoal($param["name"]);
		return "";
	}
	
	function Splittest($fn, $vals = array() ) {
		$this->Scriptor($fn, $vals);  
		// register a/b testing controls
		$this->ctrls["abtest"] = "ctrl_abtest";  
		$this->ctrls["abgoal"] = "ctrl_abgoal";  
	}
}


?>