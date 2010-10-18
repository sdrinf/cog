<?php
// configuration
class config
 {
var $sql_serv   = "localhost";
var $sql_user   = "";
var $sql_pwd    = ""; 
var $sql_db		= ""; 

var $debug		= true;
var $adminmail		= "admin@mail.com";
var $demonmail		= "errorhandler@mail.com";  
var $errorlogs		= "./logs/cog_error.log"; 
}

$g_cfg = new config();

 // sessions, and cookies
ini_set('session.name',"mainsession"); 
ini_set('session.cookie_domain',$_SERVER["SERVER_NAME"] );
ini_set('session.cookie_lifetime', 60*60*24*365*5);
setlocale(LC_ALL, 'UTF-8');


?>