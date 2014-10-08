<?php


// cog core modules
include_once("./engine/_config.php");
include_once("./engine/cog_common.php");
include_once("./engine/cog_errorhandler.php");
include_once("./engine/cog_database.php");
include_once("./engine/cog_logger.php");
include_once("./engine/cog_scriptor.php");
include_once("./engine/cog_variants.php");
include_once("./engine/cog_webhandler.php");
include_once("./engine/cog_session.php");

initsql();	     // initalize database
session_start(); // start session
log_start();	 // log request
variants_start(); // initalizes variants

// create a new webhandler for all requests
$w = new WebHandler([	// URL handlers
		// main pages
			"/" => "site_guestbook/guestbook.php",
			"/purchase" => "site_guestbook/purchase.php",
			"/thanks" => "site_guestbook/thanks.html",

			// admin interface
			"/admin" => "site_admin/multivariants.php",
			"/user_api" => "site_admin/user_api.php",
			"(.*)" => "site_guestbook/notfound.php"
	],
	// frame parameters
	["title" => "Cog - guestbook example",
	 "frame" => "site_guestbook/frame.html"
	] );
// print_r($w); exit;
echo $w->result();

?>