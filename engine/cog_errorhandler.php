<?php

// ---------------------------------------------------------
//  Error handler module v0.3
// ---------------------------------------------------------

// ---------------------------------------------------------
// global error handling -checks for code monkey's sanity
// ---------------------------------------------------------
$g_errorlevel = 0;

function errorHandler($errno, $errstr='', $errfile='', $errline='')
{
	global $g_cfg;
	global $_GET,$_POST;
	global $g_errorlevel;
	global $pagequeries;
	global $g_codetrace; $g_codetrace []= "error";

	if(func_num_args() == 5) {
        // called by trigger_error()
        $exception = null;
        list($errno, $errstr, $errfile, $errline) = func_get_args();

        $backtrace = array_reverse(debug_backtrace());

    }else {
        // caught exception
        $exc = func_get_arg(0);
        $errno = $exc->getCode();
        $errstr = $exc->getMessage();
        $errfile = $exc->getFile();
        $errline = $exc->getLine();

        $backtrace = $exc->getTrace();
    }
	if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting
        return;
    }

    $errorType = array (
               E_ERROR            => 'ERROR',
               E_WARNING        => 'WARNING',
               E_PARSE          => 'PARSING ERROR',
               E_NOTICE         => 'NOTICE',
               E_CORE_ERROR     => 'CORE ERROR',
               E_CORE_WARNING   => 'CORE WARNING',
               E_COMPILE_ERROR  => 'COMPILE ERROR',
               E_COMPILE_WARNING => 'COMPILE WARNING',
               E_USER_ERROR     => 'USER ERROR',
               E_USER_WARNING   => 'USER WARNING',
               E_USER_NOTICE    => 'USER NOTICE',
               // E_STRICT         => 'STRICT NOTICE',
               // E_RECOVERABLE_ERROR  => 'RECOVERABLE ERROR'
               );
    // create error message
    if (array_key_exists($errno, $errorType)) {
        $err = $errorType[$errno];
    } else {
        $err = 'CAUGHT EXCEPTION';
    }

    $errMsg = "$err: $errstr in $errfile on line $errline";
    $tracelines = array();

	// print_r($backtrace); exit;
    // start backtrace
    foreach ($backtrace as $v) {
 		$trace = "";
        if (isset($v['class'])) {

            $trace = 'in class '.$v['class'].'::'.$v['function'].'(';

            if (isset($v['args'])) {
                $separator = '';

                foreach($v['args'] as $arg ) {
                    $trace .= "$separator".getArgument($arg);
                    $separator = ', ';
                }
            }
            $trace .= ')';
        } elseif (isset($v['function']) && empty($trace)) {
            $trace = 'in function '.$v['function'].'(';
            if (!empty($v['args'])) {

                $separator = '';

                foreach($v['args'] as $arg ) {
                    $trace .= "$separator".getArgument($arg);
                    $separator = ', ';
                }
            }
            $trace .= ')';

        }
        if (!isset($v["file"])) $v["file"] = "[undef]";
        if (!isset($v["line"])) $v["line"] = "[undef]";
        if ( (isset($v["function"])) && ($v["function"] == "errorhandler")) {

        } else {
        	$tracelines []= $trace." called by ".$v["file"].", line ".$v["line"];
        }
    }

	$g_errorlevel++;

	if (isset($g_cfg["console_mode"]) && ($g_cfg["console_mode"] == true))
		$errortext = 'Console request at '.date("Y M j G:i:s ")."\n";
	else
		$errortext = 'requested '.$_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"].' at '.date("Y M j G:i:s ")."\n";
	$errortext .= 'Debug Msg: '.$errMsg."\n\nTrace:\n".implode("\n",$tracelines)."\n"."\n\nQueries:".implode("\n", $pagequeries)."\n------\n" ;
    if (count(array_keys($_POST)) > 0)
        $errortext .= "with post: ".json_encode($_POST)."\n";
    if ($g_cfg["console_mode"] == false)
    	file_put_contents($g_cfg["errorlogs"],$errortext, FILE_APPEND);

	global $pagequeries;

	if (isset($g_cfg["console_mode"]) && ($g_cfg["console_mode"] == true)) {
		echo "\nDebug Msg: ".$errMsg."\nTrace: ".implode("\n",$tracelines)."\n".
				"Queries:\n ".implode("\n",$pagequeries)."\n" ;
	} else if ( (!isset($g_cfg)) || ($g_cfg["debug"] == true)) {
		// display error msg, if debug is enabled
			header("HTTP/1.1 500 Internal Server Error");
			header("Content-Type: text/html; charset=UTF-8");

			echo '<h2>Debug Msg</h2>'.nl2br($errMsg).'<br /><hr size="1" />
				Trace:<br /> '.implode("<br />",$tracelines).'<br /><br/>
				Queries:<br /> '.implode("<br />",$pagequeries).'<br />
				';
    } else {
		// send email to admin
		if(!empty($g_cfg["adminmail"] )) {
			$ok = @mail($g_cfg["adminmail"],'critical error on '.$_SERVER['HTTP_HOST'].' by '.$_SERVER["REMOTE_ADDR"], $errortext, 'From: '.$g_cfg["supportmail"]);
		}
		// silence error
		if ( ($errno == E_WARNING)  || ($errno == E_NOTICE) ) {
			return;
		}
		// end and display error msg
        header("HTTP/1.1 500 Internal Server Error");
		header("Content-Type: text/html; charset=UTF-8");
		exit('Internal error -sorry for the inconvenience; the admin has been notified, and will fix this momentarily. In the meantime, please <a href="/">restart from the main page</a>');
    }
} // end of errorHandler()

function getArgument($arg, $clevel = 0)
{
    switch (strtolower(gettype($arg))) {

        case 'string':
            return( '"'.str_replace( array("\n"), array(''), $arg ).'"' );

        case 'boolean':
            return (bool)$arg;

        case 'object':
            return 'object('.get_class($arg).')';

        case 'array':
            $ret = 'array(';
            $separtor = '';
			if ($clevel > 2) {
				$ret .= "max recursive depth reached";
			} else {
				foreach ($arg as $k => $v) {
					$ret .= $separtor.getArgument($k, $clevel+1).' => '.getArgument($v,$clevel+1 );
					$separtor = ', ';
				}
			}
            $ret .= ')';

            return $ret;

        case 'resource':
            return 'resource('.get_resource_type($arg).')';

        default:
            return var_export($arg, true);
    }
}

// ---------------------------------------------------------
// internal error handling -checks for internal consistency
// ---------------------------------------------------------

function internal_error($err) {
	global $g_cfg;
    if ($g_cfg["console_mode"] == false) {
    	if (!file_exists($g_cfg["errorlogs"])) { fclose(fopen($g_cfg["errorlogs"],"w")); }
    	$errortext = date("Y M j G:i:s ").$err."\n\n---------\n";
    	file_put_contents($g_cfg["errorlogs"],$errortext, FILE_APPEND);
    }
	errorHandler(1,"internal error: ".$err,"errorhandler",0,0);
	exit;
}

// ---------------------------------------------------------
// internal hack detector -checks for user's sanity
// ---------------------------------------------------------
function hack_sign($err) {
    global $g_cfg;
    if (!file_exists($g_cfg["hacklogs"])) { fclose(fopen($g_cfg["hacklogs"],"w")); }
    $errortext = date("Y M j G:i:s ").$err."\n";
    file_put_contents($g_cfg["hacklogs"],$errortext, FILE_APPEND);
    errorHandler(1,"Hack Sign: ".$err,"errorhandler",0,0);
    exit;
}

// Notify the admin, but do not halt execution
function internal_notify($err) {
    global $g_cfg;
    $ok = @mail($g_cfg["adminmail"],'Internal notify on '.$_SERVER['HTTP_HOST'].' by '.$_SERVER["REMOTE_ADDR"], $err, 'From: '.$g_cfg["supportmail"]);
    return true;
}
// ---------------------------------------------------------
// set the global error settings
// ---------------------------------------------------------

error_reporting(E_ALL & ~E_DEPRECATED );
ini_set('error_reporting', E_ALL & ~E_DEPRECATED );
ini_set('allow_call_time_pass_reference', 'On');
ini_set('display_errors','On');
set_error_handler("errorHandler");

?>