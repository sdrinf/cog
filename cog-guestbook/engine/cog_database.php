<?php
// ---------------------------------------------------------
//  Database handling, v3.2
// ---------------------------------------------------------

// -------------------------------------------------------
//  inits an sql connection to $msq
// -------------------------------------------------------

$msq = NULL;
$pagequeries = array();

function initsql() {
	global $g_cfg,$msq;
	if ($msq != NULL) return; 
	if (!($msq = mysql_connect($g_cfg->sql_serv,$g_cfg->sql_user,urldecode($g_cfg->sql_pwd) ))) {
	 	internal_error("cannot connect to mysql.");
	}
	if (!mysql_select_db($g_cfg->sql_db,$msq)) {
		internal_error("cannot select db: ".$g_cfg->sql_db."\n");
	}
	mysql_query("SET NAMES 'utf8'");			// we're using UTF-8
}

// resolve the parameters from the query
function resolvequery($query, $vals = null) {
	if ($vals != null) {
		foreach ($vals as $k=>$v) {
			$rv = '"'.mysql_real_escape_string($v).'"';
			$query = str_replace("@".$k, $rv, $query);
		}
	}
	return $query;
}

// returns an array of hashtable for given query; optionally fill values
function gettable($query, $vals = null) {
	global $msq, $pagequeries;
	$query = resolvequery($query, $vals);
	$pagequeries []= $query;
	$qres=mysql_query($query,$msq);
	if (!$qres) internal_error("can not execute: ".$query);
	$res = array();
	while ($qrow = mysql_fetch_array($qres)) {
		$res []= $qrow;
	}
	return $res;
}


// commits given query to the db
function dbcommit($query, $vals = null) { 
	global $msq, $pagequeries;
	$query = resolvequery($query, $vals);
	$pagequeries []= $query;
	$qres=mysql_query($query,$msq);
	if (!$qres) internal_error("can not execute: ".$query);
	return $qres;
}

// inserts a new row into given table, with given fields
function dbinsert($table, $fields, $funcs = null) {
	$keys = array();
	$vals = array();
	foreach ($fields as $k => $v) {
		$keys []= $k;
		$vals []= '"'.mysql_real_escape_string($v).'"';
	}
	if ($funcs != null) {
		foreach ($funcs as $k => $v) {
			$keys []= $k;
			$vals []= $v;
		}
	}
	$q = "insert into ".$table." (".implode(", ",$keys).") ".
			" values (".implode(", ",$vals)."); ";
	// echo $q; exit;
	return dbcommit($q);
}

// updates the given table with fields for $where given rows
function dbupdate($table, $fields, $where = array(1=>1) ) {
	$parts = array();
	foreach ($fields as $k => $v)
		$parts []= $k.' = "'.mysql_real_escape_string($v).'"';

	$whparts = array();
	foreach ($where as $k => $v)
		$whparts []= $k.' = "'.mysql_real_escape_string($v).'"';
	$q = "update ".$table." set ".implode(", ",$parts)." where ".implode("and ",$whparts); 
	return dbcommit($q);
}

?>