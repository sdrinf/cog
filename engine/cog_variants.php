<?php

// -------------------------------------------------------
//  Multi-armed bandit implementation, v4.2, based on
//  http://stevehanov.ca/blog/index.php?id=132
//  dependencies: cog_database.php, cog_scriptor
// -------------------------------------------------------
// dependencies
include_once dirname(__FILE__).'/cog_logger.php';
include_once dirname(__FILE__).'/cog_common.php';
include_once dirname(__FILE__).'/cog_database.php';
include_once dirname(__FILE__).'/cog_scriptor.php';


$g_mv_tests = [];


function variants_start() {
	global $g_mv_tests;
	$g_mv_tests = gettable("select * from sys_variations");
	return true;
}

class Variant {
	var $test = null;
	var $goal = null;

	function get() {
		global $g_mv_tests, $g_log_id;
		if (isset($_SESSION["_mv_".$this->test])) {
			return $_SESSION["_mv_".$this->test]["var"];
		}
		if (beginsWith($this->test, "man"))
			return 0;
		$r = array_filter($g_mv_tests, function($c) {
				return ( ($c["test"] ==  $this->test) && ($c["goal"] == $this->goal));
		});
		// reindex array
		$r = array_values($r);
		if (count($r) == 0)
			internal_error("No variants found for test: ".$this->test." ,goal: ".$this->goal);
		if ($r[0]["completed"] != null) {
			$res = $r[0];
		} else {
			// assign the user randomly
			shuffle($r);
			$res = $r[0];
		}
		$_SESSION["_mv_".$this->test] =
			["var" => $res["variation"], "start_log_id" => $g_log_id];
		return $res["variation"];
	}

	// forcibly re-sets user's variant enrollment
	function set($id) {
		global $g_log_id;
		if (isset($_SESSION["_mv_".$this->test])) {
			$_SESSION["_mv_".$this->test]["var"] = $id;
		} else {
			$_SESSION["_mv_".$this->test] =
				["var" => $id, "start_log_id" => $g_log_id];
		}
		return true;
	}

	// returns the first row for this variant
	function get_row() {
		global $g_mv_tests;
		$cvar = $this->get();
		$r = array_filter($g_mv_tests, function($c) use ($cvar)  {
				return ( ($c["test"] ==  $this->test) && ($c["goal"] == $this->goal) &&
						($c["variation"] == $cvar));
		});
		if (count($r) != 1)
			internal_error("Can't find row for test: ".$this->test." goal:".$this->goal." variation:".$cvar." in ".print_r($g_mv_tests, true));
		$r = array_values($r);
		return $r[0];
	}

	// returns the content of the current variation
	function get_content() {
		return $this->get_row()["content"];
	}

	// returns the url at which this test is relevant
	function get_url() {
		return $this->get_row()["url"];
	}

	function Variant($testname, $vars = [], $goal = null) {
		global $g_mv_tests, $g_mv_goals, $g_log_id;
		$reread = false;

		foreach ($vars as $v => $content) {
			if (count(array_filter($g_mv_tests, function($c) use ($testname, $v) {
				return (($c["test"] == $testname) && ($c["variation"] == $v));
			})) != count($g_mv_goals)) {
				foreach ($g_mv_goals as $cgoal) {
					dbupinsert("sys_variations",
						["test" => $testname, "variation" => $v, "goal" => $cgoal,
							"url" => $_SERVER["REQUEST_URI"], "content" => $content,
							"start_log_id" => $g_log_id ],
						["url", "content"]);
					$reread = true;
				}
			}
		}
		if ($reread)
			variants_start();
		$this->test = $testname;
		$this->goal = ($goal == null)?($g_mv_goals[0]):($goal);
	}

};

function variant_hitgoal($goalname) {
	global $g_log_id;
	if (isset($_SESSION["_mg_".$goalname]))
		return true;
	$_SESSION["_mg_".$goalname] = $g_log_id;
	return true;
}

// recalculates a single set of identity values
function variant_recalculate_identity($res, $row) {
	global $g_mv_goals, $g_mv_tests;
	foreach ($row as $k => $v) {
		if (!beginswith($k, "_mv_"))
			continue;
		$ctest = substr($k, 4);
		// print_r($row); echo $ctest; exit;
		foreach ($g_mv_goals as $cg) {
			// are we measuring this?
			if (!isset($res[$ctest."|".$v["var"]."|".$cg."|start_log_id"]))
				continue;
			// have the user entered this after we started measuring it?
			if ($v["start_log_id"] < $res[$ctest."|".$v["var"]."|".$cg."|start_log_id"])
				continue;
			if ((isset($row["_mg_".$cg])) && (is_numeric($row["_mg_".$cg]))) {
				// only count in, if participated before conversion
				if ($row["_mg_".$cg] > $v["start_log_id"]) {
					$res[$ctest."|".$v["var"]."|".$cg."|sample"] []= $v["start_log_id"];
					$res[$ctest."|".$v["var"]."|".$cg."|conversion"] []= $v["start_log_id"];
				}
			} else {
				// participating, but haven't converted yet
				$res[$ctest."|".$v["var"]."|".$cg."|sample"] []= $v["start_log_id"];
			}
		}
	}
	return $res;
}

// recalculates all participations & conversion
// The primary key for grouping things together is identity; we calculate this via start_log_id (which is unique even if user logged out / logs in later from other device).
function variant_recalculate() {
	global $g_mv_goals, $g_mv_tests;
	$res = [];
	foreach ($g_mv_tests as $row) {
		$res[$row["test"]."|".$row["variation"]."|".$row["goal"]."|sample"] = [];
		$res[$row["test"]."|".$row["variation"]."|".$row["goal"]."|conversion"] = [];
		$res[$row["test"]."|".$row["variation"]."|".$row["goal"]."|start_log_id"] = $row["start_log_id"];
	}
	// print_r($res); exit;

	// add sessions
	$q = gettable("select * from sys_sessions where ishuman = 1");
	session_start();
	foreach ($q as $row) {
		foreach (array_keys($_SESSION) as $ckeys)
			unset($_SESSION[$ckeys]);
		if (!session_decode($row["session_data"]))
			internal_error("Unable to unserialize: ".$row["session_data"]);
		$res = variant_recalculate_identity($res, $_SESSION);
	}
	// add users
	$q = gettable("select * from usr_users");
	foreach ($q as $row) {
		$srow = json_decode($row["session_data"], true);
		if ($srow == null)
			continue;
		$nrow = json_decode($row["usr_notes"], true);
		if ($nrow == null)
			continue;
		if (isset($nrow["_mg_cancellation"]))
			$srow["_mg_cancellation"] = intval($nrow["_mg_cancellation"]);
		$res = variant_recalculate_identity($res, $srow);
	}
	// print_r($g_mv_tests ); exit;
	$ckeys = array_keys($res);
	foreach ($g_mv_tests as $row) {
		$sample = count(array_unique($res[$row["test"]."|".$row["variation"]."|".$row["goal"]."|sample"]));
		$conv = count(array_unique($res[$row["test"]."|".$row["variation"]."|".$row["goal"]."|conversion"]));
		$cwhere = update([], $row, ["test", "variation", "goal"]);
		dbupdate("sys_variations", ["sample" => $sample, "conversion" => $conv], $cwhere);
	}
}

// refresh variants cache
$g_con["cron.hourly"]["variants"] = function($param) {
	variants_start();
	return variant_recalculate();
};

// ---------------------------------------------------------
// Scriptor extensions
// ---------------------------------------------------------
$g_scr_tests = [];

function ctrl_variant($scr, $param, $script, $vals) {
	global $g_scr_tests;
	if (!isset($g_scr_tests[$param["name"]]))
		$g_scr_tests[$param["name"]] = [];
	if (!in_array($param["value"], $g_scr_tests[$param["name"]] ))
		$g_scr_tests[$param["name"]] []= $param["value"];
	$cv = new Variant("scr:".$param["name"], $g_scr_tests[$param["name"]] );
	if ($cv->get_content() == $param["value"])
		return $scr->ev($script, $vals);
	return "";
}

// ---------------------------------------------------------
// Unit tests
// ---------------------------------------------------------
if ( (isset($_SERVER["argv"])) && (isset($_SERVER["SCRIPT_FILENAME"])) &&
	( basename($_SERVER["SCRIPT_FILENAME"]) == basename(__FILE__)) ) {
	echo "Variants: Running self-tests...\n";
	assert(initsql());
	variants_start();
	$v = new Variant("test", ["v1" => "testv1", "v2" => "testv2", "v3" => "testv3"]);
	echo $v->get();
}


?>