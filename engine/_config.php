<?php
// -------------------------------------------------------
// Cog Configuration, v0.2
// -------------------------------------------------------

$g_cfg = [
	// SQL bindings
	"sql_serv"  => "localhost",
	"sql_user"  => "",
	"sql_pwd"	=> "",
	"sql_db"	=> "guestbook",

	// Error reporting
	"debug"		=> true,
	"adminmail"	=> "webmaster@example.com",
	"supportmail"=> "Hello <hello@example.cmo>",
	"errorlogs" => "/webdata/logs/cog_guestbook_error.log",
	"hacklogs" => "/webdata/logs/cog_guestbook_hacks.log",

	// console run
	"console_mode"		=> false,
];

// multivariant goals
$g_mv_goals = ["purchasing"];

 // sessions, and cookies
ini_set('session.name',"mainsession");
ini_set('session.cookie_domain', (isset($_SERVER["SERVER_NAME"])?($_SERVER["SERVER_NAME"]):("") ));
ini_set('session.cookie_lifetime', 60*60*24*365*5  );
ini_set('session.use_only_cookies', true);
ini_set('session.cookie_httponly', true);
setlocale(LC_ALL, 'UTF-8');

date_default_timezone_set("Europe/London");

?>