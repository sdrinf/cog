<?php
// ---------------------------------------------------------
//  Cog session handling, v2.1
// ---------------------------------------------------------
// literature:
// http://www.codinghorror.com/blog/2008/08/protecting-your-cookies-httponly.html
// http://stackoverflow.com/questions/549/the-definitive-guide-to-forms-based-website-authentication

// dependencies
include_once dirname(__FILE__).'/_config.php';

$g_session_id = 0;
$g_session_new = false;   // true for newly created sessions

class Session
{

    // Open the session
    public static function open($save_path, $session_name) {
        // echo $save_path." | ".$session_name."\n"; exit;
		return true;
    }

    // Close the session
    public static function close() {
        return true;
    }

    // Read the session the specified session
    public static function read($id) {
        global $g_session_id, $g_session_new;
		$q = gettable("select * from sys_sessions where session_cookie=@cookie and domain = @domain",["cookie" => $id, "domain" => $_SERVER["SERVER_NAME"] ]);
		if (count($q) > 0) {
            $g_session_id = $q[0]["id"];
			return $q[0]["session_data"];
        }
        // insert minimum viable session id
        $g_session_id = dbupinsert("sys_sessions",
            ["session_cookie" => $id, "domain" => $_SERVER["SERVER_NAME"]], ["lastupdate"],
            ["lastupdate" => "now()", "cohort_date" => "now()"] );
        $g_session_new = true; // mark it as new
        return '';
    }

    // write the specified session
    public static function write($id, $data) {
		if (!isset($_SERVER["SERVER_NAME"]))
			return;
		$res = dbupinsert("sys_sessions",
			["session_cookie" => $id, "session_data" => $data, "domain" => $_SERVER["SERVER_NAME"],
				"ishuman" => ((isset($_SESSION["_ishuman"]) && ($_SESSION["_ishuman"] == 1))?(1):(0) ),
                "status" => (isset($_SESSION["_status"]) ? ($_SESSION["_status"]):("unregistered"))
            ],
			["session_data", "lastupdate", "ishuman","status"],
            ["lastupdate" => "now()", "cohort_date" => "now()"] );
    }

    /**
     * Destoroy the session
     * @param int session id
     * @return bool
     */
    public static function destroy($id) {
		dbcommit("update sys_sessions set session_data = '' where session_cookie = @id
                and domain = @domain", ["id" => $id, "domain" => $_SERVER["SERVER_NAME"]] );
		return true;
        //$sql = sprintf("DELETE FROM `sessions` WHERE `session` = '%s'", $id);
        // return mysql_query($sql, self::$_sess_db);
    }

    /**
     * Garbage Collector
     * @param int life time (sec.)
     * @return bool
     * @see session.gc_divisor      100
     * @see session.gc_maxlifetime 1440
     * @see session.gc_probability    1
     * @usage execution rate 1/100
     *        (session.gc_probability/session.gc_divisor)
     */

    public static function gc($max) {
		dbcommit("delete from sys_sessions where unix_timestamp(lastupdate) + ".$max." < unix_timestamp(now())");
		return true;

        $sql = sprintf("DELETE FROM `sessions` WHERE `session_expires` < '%s'",
                       mysql_real_escape_string(time() - $max));
        return mysql_query($sql, self::$_sess_db);
    }
}

ini_set('session.gc_probability', 0);
ini_set('session.gc_divisor', 100);  // garbage collect with 20% probability
ini_set('session.gc_maxlifetime', ini_get("session.cookie_lifetime") );


if ($g_cfg["console_mode"] == false) {
	// set save handlers
	ini_set('session.save_handler', 'user');
	session_set_save_handler(array('Session', 'open'),
							 array('Session', 'close'),
							 array('Session', 'read'),
							 array('Session', 'write'),
							 array('Session', 'destroy'),
							 array('Session', 'gc')
							 );

	// PHP garbage collects & destroys objects *before* session write;
	// this function ensures session commit to happen before GC
	register_shutdown_function('session_write_close');
} else {
    // Console to not leave session files behind
    ini_set('session.save_handler', 'user');
    $nullfunc = function() { };
    session_set_save_handler($nullfunc, $nullfunc, $nullfunc, $nullfunc, $nullfunc, $nullfunc);
}


?>