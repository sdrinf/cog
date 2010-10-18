<?php


// cog core modules
include_once("./engine/_config.php"); 
include_once("./engine/cog_common.php");   
include_once("./engine/cog_errorhandler.php");   
include_once("./engine/cog_database.php");  
include_once("./engine/cog_logger.php");   
include_once("./engine/cog_scriptor.php"); 
include_once("./engine/cog_splittest.php"); 
include_once("./engine/cog_webhandler.php");  

session_start();
initsql();  
log_start(); 

// create a new webhandler for all requests
$w = new WebHandler(
	array(		// URL handlers
		// ab-test pages
			"/cog-guestbook/ab_ishuman" => array("", "splittest_sethuman"),
			"/cog-guestbook/ab_set" => array("", "splittest_setab"),
			"/cog-guestbook/stats" => array("", "splittest_admin"),	

		// main pages
			"/cog-guestbook/" => "pages/guestbook.php",
			"/cog-guestbook/purchase" => "pages/purchase.php", 
			"/cog-guestbook/thanks" => "pages/thanks.html", 
			
			"(.*)" => "pages/notfound.php"  
	), 
	// frame parameters
	array("title" => "Cog - guestbook example"),
		"pages/frame.html");
// print_r($w); exit; 
echo $w->result();
log_end();

?>