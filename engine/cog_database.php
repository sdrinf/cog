<?php
// ---------------------------------------------------------
//  Database handling, v4.0
//  4.0: mysqli support
// ---------------------------------------------------------
// dependencies
include_once dirname(__FILE__).'/_config.php';
include_once dirname(__FILE__).'/cog_common.php';
include_once dirname(__FILE__).'/cog_errorhandler.php';

// global variables
$msq = NULL;
$pagequeries = [];

// Initalizes the SQL connection based on global config variables
function initsql() {
	global $g_cfg,$msq;
	if ($msq != NULL) return;
	if (!($msq = mysqli_connect("p:".$g_cfg["sql_serv"],$g_cfg["sql_user"],
					urldecode($g_cfg["sql_pwd"]), $g_cfg["sql_db"]  ))) {
	 	internal_error("cannot connect to mysql.");
	}
	mysqli_set_charset($msq,  "utf8");
	return true;
}

// resolve the parameters from the query
function resolvequery($query,$vals="") {
	global $msq;
	if ($vals == "")
			return $query;
	$query = preg_replace_callback('/@(\w+)/', function($match) use ($vals, $msq) {
			if (!in_array($match[1], array_keys($vals))) {
					return $match[0];
			}
			return '"'.mysqli_real_escape_string($msq, $vals[$match[1]]).'"';
		}, $query);
	return $query;
}

// returns an array of hashtable for given query; optionally fill values
function gettable($query, $vals = null) {
	global $msq, $pagequeries;
	$query = resolvequery($query, $vals);
	$perflog = array_sum(explode(' ', microtime()));
	$qres=mysqli_query($msq, $query);
	if (!$qres) internal_error("can not execute: ".$query." \n<br />MySQL error: ".mysqli_error($msq) );
	$res = array();
	while ($qrow = mysqli_fetch_array($qres, MYSQLI_ASSOC)) {
		$res []= $qrow;
	}
	$pagequeries []= '['.(round( (array_sum(explode(' ', microtime())) - $perflog),4)).'] '.$query;
	return $res;
}


// commits given query to the db
function dbcommit($query, $vals = null) {
	global $msq, $pagequeries;
	$query = resolvequery($query, $vals);
	$perflog = array_sum(explode(' ', microtime()));
	$qres=mysqli_query($msq, $query);
	$pagequeries []= '['.round( (array_sum(explode(' ', microtime())) - $perflog),4).'] '.$query;
	if (!$qres) internal_error("can not execute: ".$query." \n<br />MySQL error: ".mysqli_error($msq) );
	return $qres;
}

// returns a list of mysql-escaped keys-values
function db_fields_to_keys_vals($fields, $funcs) {
	global $msq;
	$keys = [];
	$vals = [];
	foreach ($fields as $k => $v) {
		$keys []= $k;
		$vals []= '"'.mysqli_real_escape_string($msq, $v).'"';
	}
	if ($funcs != null) {
		foreach ($funcs as $k => $v) {
			$keys []= $k;
			$vals []= $v;
		}
	}
	return [$keys, $vals];
}

// inserts a new row into given table, with given fields
function dbinsert($table, $fields, $funcs = null) {
	global $msq;
	list($keys, $vals) = db_fields_to_keys_vals($fields, $funcs);
	$q = "insert into ".$table." (".implode(", ",$keys).") ".
			" values (".implode(", ",$vals)."); ";
	if (dbcommit($q) === false)
		return false;
	return mysqli_insert_id($msq);
}

// updates the given table with fields for $where given rows
function dbupdate($table, $fields, $where = [1=>0], $funcs = null ) {
	global $msq;
	$parts = [];
	foreach ($fields as $k => $v)
		$parts []= $k.' = "'.mysqli_real_escape_string($msq, $v).'"';
	if ($funcs != null) {
		foreach ($funcs as $k => $v) {
			$parts []= $k.' = '.$v;
		}
	}
	$whparts = [];
	foreach ($where as $k => $v)
		$whparts []= $k.' = "'.mysqli_real_escape_string($msq, $v).'"';
	$q = "update ".$table." set ".implode(", ",$parts)." where ".implode(" and ",$whparts);
	return dbcommit($q);
}


// inserts a new row, or updates an existing row on constraint duplicate,
// into given table, with given fields
function dbupinsert($table, $fields, $onduplicate, $funcs = null) {
	global $msq;
	list($keys, $vals) = db_fields_to_keys_vals($fields, $funcs);
	$upds = [];
	foreach ($onduplicate as $o) {
		$upds []= $o." = values(".$o.") ";
	}
	$q = "insert into ".$table." (".implode(", ",$keys).") ".
			" values (".implode(", ",$vals).") ".
			"ON DUPLICATE KEY UPDATE ".implode(", ",$upds).";";
	// echo $q; exit;
	if (dbcommit($q) === false)
		return false;
	return mysqli_insert_id($msq);
}

// inserts a new row, if given $where constrains aren't satisfied
function dbuniqueinsert($table, $fields, $where, $funcs = null) {
	global $msq;
	list($keys, $vals) = db_fields_to_keys_vals($fields, $funcs);
	$whparts = [];
	foreach ($where as $k => $v)
		$whparts []= $k.' = "'.mysqli_real_escape_string($msq, $v).'"';

	$q = "insert into ".$table." (".implode(", ",$keys).") ".
			"select * from (select ".implode(", ",$vals).") as tmp ".
			"where not exists (".
				" select ".implode(", ",$keys)." from ".$table." where ".implode(" and ",$whparts).
			") limit 1";
	// echo $q; exit;
	if (dbcommit($q) === false)
		return false;
	return mysqli_insert_id($msq);

}

function dbdelete($table, $where = [1=>0] ) {
	$whparts = [];
	foreach ($where as $k => $v)
		$whparts []= $k.' = "'.mysqli_real_escape_string($msq, $v).'"';
	$q = "delete from ".$table." where ".implode(" and ",$whparts);
	return dbcommit($q);
}

// returns the number of rows affected by last query
function dbrows() {
	global $msq;
	return mysqli_affected_rows($msq);
}

// returns an escaped string
function dbescape($str) {
	global $msq;
	return mysqli_real_escape_string($msq, $str);
}

// ---------------------------------------------------------
// Unit tests
// ---------------------------------------------------------
if ( (isset($_SERVER["argv"])) && (isset($_SERVER["SCRIPT_FILENAME"])) &&
	( basename($_SERVER["SCRIPT_FILENAME"]) == basename(__FILE__)) ) {
	echo "cog_database: Running self-tests...\n";
	// set up asserts
	assert_options(ASSERT_ACTIVE, 1);
	assert_options(ASSERT_WARNING, 0);
	assert_options(ASSERT_QUIET_EVAL, 0 );
	// Set up the callback
	assert_options(ASSERT_CALLBACK, function ($file, $line, $code) {
		echo "Assertion Failed:\n File '$file' \n  Line '$line'  Code '$code'\n\n";
	});
	assert(initsql());
	assert( resolvequery("VALUES (@email, @example)", array("email" => "test@example.com", "example" => 123)) == 'VALUES ("test@example.com", "123")' );
	// table-based testing
	dbcommit("drop table if exists cog_testing");
	dbcommit("create table if not exists cog_testing (id int primary key auto_increment, username varchar(64), status enum('live', 'deleted') default 'live', created datetime )");
	dbinsert("cog_testing", ["username" => random_string(16) ], ["created" => "now()"] );
	$q = gettable("select * from cog_testing where status = 'live' ");
	assert( count($q) == 1);
	// unique insert testing
	$res = dbuniqueinsert("cog_testing", ["username" => "unique" ], ["username" => "unique", "status" => "live"], ["created" => "now()"] );
	assert( $res !== false);  // this should return with new ID
	$q = gettable("select * from cog_testing where status = 'live' ");
	assert( count($q) == 2);
	$res = dbuniqueinsert("cog_testing", ["username" => "unique" ], ["username" => "unique", "status" => "live"], ["created" => "now()"] );
	assert( $res == 0);  // this should return 0
	dbcommit("drop table if exists cog_testing"); // clean up
	echo "Self-tests finished\n";
}

?>