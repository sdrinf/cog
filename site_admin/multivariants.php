<?php

function child_render($param) {
	global $g_page, $g_cfg;
	$g_page["frame"] = "site_admin/frame.html";
	if (!http_user_auth("admin", "admin"))
		return redirect("/");
	if (isset($param[1]) && ($param[1] == "switch_status")) {
		list($cname, $p) = [$_GET["name"], $_GET["p"]];
		if ($p == "close")
			dbupdate("sys_variations", [], ["test" => $cname], ["completed" => "now()"]);
		return redirect("/stats/multivariants");
	}
	$q = gettable("select * from sys_variations where completed is null");
	$t = [];
	foreach ($q as $row) {
		if (!isset($t[$row["test"]]))
			$t[$row["test"]] = [];
		if (!isset($t[$row["test"]][$row["variation"]]))
			$t[$row["test"]][$row["variation"]] = ["goals" => []];
		$t[$row["test"]][$row["variation"]]["content"] = substr($row["content"], 0, 256);
		$cm = new Variant($row["test"]);
		$t[$row["test"]][$row["variation"]]["enrolled"] =
						($cm->get() == $row["variation"])?(1):(0);
		$t[$row["test"]][$row["variation"]]["variation"] = $row["variation"];
		$cv = [];

		$cgoal = $row;
		$cgoal["pc"] = ($row["sample"] == 0)?("N/A"):(round(($row["conversion"] / $row["sample"])*100,2));
		$t[$row["test"]][$row["variation"]]["goals"] []= $cgoal;
		$t[$row["test"]][$row["variation"]] = update($t[$row["test"]][$row["variation"]], $cv);
	}
	$res = [];
	foreach ($t as $name => $row) {
		$cm = [];
		foreach ($t[$name] as $varname => $cvar) {
			uasort($cvar["goals"], function($a, $b) {
				global $g_mv_goals;
				if (($i = array_search($a["goal"], $g_mv_goals)) === false)
					return -1;
				if (($j = array_search($b["goal"], $g_mv_goals)) === false)
					return -1;
				if ($i == $j)
					return 0;
				return ($i > $j)?(1):(-1);
			});
			$cm []= $cvar;
		}
		$res []= ["test" => $name, "var" => $cm];
	}
	// print_r($res); exit;
	$scr = new Scriptor("site_admin/multivariants.html", ["tests" => $res]);
	return $scr->result();
}

?>