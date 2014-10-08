<h1>Cog: A micro framework for rapid PHP development</h1>

Cog is a PHP-based, minimalistic, and modular web framework, targeted for rapid site development. Cog is based on the idea, that you must understand the tools you're working with, in order to avoid the learning curve, scaling and maintenance pitfalls.

<h3>No good substitute for understanding.</h3>
The documentation below is provided as a starting point to jump into existing codebases; however, <b>when in doubt, read the framework's code</b>. The framework's cognitive load is deliberately kept as small as possible to allow you to know exactly what's going on with each, and every call.

Disclaimer: Cog is a living system, that is being developed continuously. List of benefits might be subject to change, by a function of projects it's being sharpened upon.

 Cog is modular: you can decide to use whichever module you'd like to use in your project -dependencies are minimized.

<b>Requirements</b>
Cog works best with PHP 5.4, and above, MySQL 5.1 and above
Supported web-servers are: Apache 2.0++, Nginx 0.7++, and IIS 7 with PHP bindings
jQuery is not necessary, but strongly recommended for rapid front-end development.


<h3>Installation, and unit tests:</h3>
To install with the sample application, create a new database, and execute import.sql on it; then update the main configuration file (engine/_config.php ) with the relevant access codes, and email addresses.

Unit tests are included on a per-module basis; to run them, just execute the module as-is from command line.

<h3>Idioms:</h3>

<b>Structure of a Cog project</b>
/engine: contains reusable modules:
 -_config.php contains site-wide configuration
 -cog_*  contains core functions used at least within 3 projects
 -mod_*  contains reused functions, that are being called at least from 2 distinct URLs
/site: contains the URL handlers, and main frame
/static: all static site assets
Root directory contains a single .php file that handles all incoming dynamic requests.

<b>URL demuxing, and page handling</b>
Every request done to the application is being handled by a single central handler (the sole .php in the root directory).
This script sets up the <a href="#332">webhandler</a>, by giving a list of URL-pattern - handler pairs:

```php
$wapp = new WebHandler(
	array(				// URL handlers
		// main pages
		"/" => "site_app/splash.php",
		"/signout" => "site_app/signout.php",
		"/dashboard" => "site_app/dashboard.php"
		"/(.*)" => "site_app/notfound.php",
		),
	array(				// frame parameters
		"title" => "My e-mail app",
		"description" => "This is an e-mail application",
		"keywords" => "e-mail",
		),
		"site_app/frame.html" // frame page
	);
echo $wapp->result();
exit;
```

The webhandler checks the requested URL, calls the appropriate handler, then frames the resulting child page into the app's shared main frame, and returns the fully rendered .html ready to be displayed.
(Note this is the only time the app echoes; unlike most php frameworks, Cog constructs the whole page within variables. This method has a number of advantages, such as post-processing the resulting HTML, adding compression, or sanity checks, or discarding the entire output at any given time during execution).


<b>URL handling back&amp;front-ends</b>
A URL handler can either be a lambda function directly in the main.php, or an includable file with the function <i>child_render</i> declared in it:


```php
function child_render($param) {
	$scr = new Scriptor("site_app/dashboard.html");
	return $scr->result();
}
```

Child_render takes an optional list of URL request parameters (if the URL was matched via preg_match -as opposed to full match- this parameter will contain the rest of the matched strings).
It can return one of the following (see <a href="#332">cog_webhandler.php</a> for implementation details):
- null for redirection (no frame will be attached, webhandler will return empty string),
- an array for ajax endpoints (webhandler will return a json_encoded string of the array); or
- an UTF-8 string containing HTML output (webhandler with frame it up, and return the full HTML)

Typically, each URL handler has a corresponding .html file containing the page to be generated; the URL handler builds up the data required for rendering, then handles that, and the .html for the templating engine (<a href="#330">Scriptor</a>).

<b>Templating engine: Scriptor</b>
<a href="#330">Scriptor </a>takes a template file's name, and an array of data to fill it with, and returns an HTML output for display.
The template files are regular .html snippets, with two extensions:

-<%<i>varname</i>%> tags are substituted with the corresponding variable in the data parameter
-Scriptor implements a number of controls for data iteration, validation, forms, and conditional display; these are  embedded via the <cog:<i>controlname</i>> tags.


<b>Database reading:</b>
All select operation are handled through the gettable function of <a href="#327">cog's database module</a>; which allows simple parametrized SQL-safe queries. For example:
```php
$q = gettable("<i>select * from users where password = md5(@pwd) and username = @username</i>",
array("pwd" => $_POST["pwd"], "username" => $_POST["username"] );
```

Which returns an array of hashtables, like this:
```Array
(
    [0] => Array
        (
            [id] => 1
            [username] => admin
            [password] => 0192023a7bbd73250516f069df18b500
        )
)
```

No need for escaping SQL-queries, or injecting variables into the query itself -these are resolved automatically via the query parameters.



<b>Putting it together: a simple DB-backed page:</b>
The handler:

```php
function child_render($param) {
	$q = gettable("select * from users");
	$scr = new Scriptor("site_app/dashboard.html", array("users" => $q));
	return $scr->result();
}
```
And the front-end:
```html
<b>Admin dashboard</b><br />
<table border="1">
<thead><tr><td>Username</td><td>Is admin</td></tr></thead>
<tbody>
<cog:iterator datasource="users">
<tr><td><%username%></td><td><%isadmin%></td></tr>
</cog:iterator>
</tbody>
</table>
```

The iterator control (cog:iterator) goes through every single line in the returned DB row, and updates the variable hashtable with the row's contents.
Variables with the same name will be overwritten within the iterator, but will be preserved outside it.

<b>User input, and Post requests</b>
Post requests are always made to the same URL handler as get ones, but instead of calling child_render, it's passed through the corresponding event handler function:

```php
$g_pageforms = array("test" => "test_onsubmit" );

function test_onsubmit($param, $post) {
	$scr = new Scriptor("site/test.html", array() );
	if (!$scr->validate("test", $post))  {
		return $scr->result();
	}
	return "validation passed!";
}
```

Post handler takes a list of URL request parameters, and a list of post parameters.

On the front-end, forms are using the <cog:form> control, which generates a cross-site scripting (XSS) safe form. The name parameter is used for demuxing between mulitple forms on the same page via the $g_pageforms variable:

```html
<cog:form name="test" method="post">
Year of birth: <input type="text" name="dob" /><Br />
<cog:validator func="numberonly" target="dob" >
	Please enter the year you were born.<br />
</cog:validator>
<input type="submit" name="send" />
</cog:form>
```

<b>Validation</b>
Cog works on a "verify on submit / format on display" principle: Data passing validation, and entered into the database is assumed to be internally consistent at all times.

User post validation is a code iceberg: it scales exponentially with the number of user inputs. Analysing other codebases, a disproportional amount of effort is usually made to testing user input; hence, the idiom here is to eliminate as much as possible.

Cog achieves this via validation controls, custom validation functions, and a simple validate() call made to Scriptor, as shown above. During form validation, each validator function ("func" parameter) is called; if any fails, the DOM node within the validator tag is added to the resulting HTML snippet, and validate returns false.

Scriptor implements a number of validation functions out-of-the-box (alphanumerical, emailaddress, URL); new validators can be added dynamically by declaring a function with validate_ prefix (eg. validate_companyname).

<b>DB updates</b>
At the very basic level, dbcommit allows for arbitrary, parameterized SQL execution; practically, a number of helper functions simplifies the insert, and update operations.

Eg. a very simple request logging module:
```php
$g_log_id = dbinsert("sys_logs", array(
	"ip" => $_SERVER["REMOTE_ADDR"],
	"url" => $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"],
), 
	array("request_time" => "now()" ) );
```

<b>AJAX requests</b>
Ajax requests can be handled by exporting function endpoints via the api_demux_call function on the URL handler.

Illustrative example:

```php
function child_render($param) {
	if (!isset($_SESSION["adminid"])) {
		return redirect("/");
	}

	$export_func = array(
		"delete_user" => array(
				array("uid"), // list of required parameters
				function($uid) {
					dbcommit("delete from ops_users where id = @uid", array("uid" => $uid));
					return array("ok" => "ok");
				}
		),
	);
	if (isset($_GET["func"])) {
		return api_demux_call($export_func);
	}
      ... // render the page
```

Front-end:

```html
<script type="text/javascript">
function delete_user(id) {
	var ck = confirm("Delete user: Are you sure?");
	if (ck != true)
		return false;
	$.ajax({ url: '?func=delete_user',  type: 'GET',  dataType: 'jsonp',
			data: {'uid' : id},
				success: function(data) {
					$("#uid_"+id).hide(10);
				}
	});
	return false;
}
</script>
```

(Notice, that authentication takes place before AJAX handling; in this case, access authentication is specific to the whole URL, so no need to duplicate that functionality)

Api_demux_call takes a hashtable of function name - function descriptor pairs; where a function descriptor is a list of parameters, and an anonymous function, implementing the handler itself. Parameters are checked for existence, then passed on to the function in raw form.
AJAX handlers usually return an array, which is converted to a JSON(P) string by the webhandler, and returned to the user.



<hr size="1">
<h2>Module Reference</h2>

<h3>_config.php:</h3>

Core configuration file; sets up the $g_cfg variable used by all other modules.
Main parameters:
* sql_serv		: the MySQL server to connect to
* sql_user		: MySQL username
* sql_pwd		: MySQL password
* sql_db		: MySQL database to use

* debug			: Error handler will do full stack dump, if true (development mode); will send error reports to adminmail, and display a general error message otherwise (deployment mode)
* adminmail		: Error handler sends error reports here
* errorlogs		: File to save errors to

A common trick here is to set up variables based on the domain of the request (eg. www.example.com sets sql_db to live database, and debug to false, while dev.example.com sets sql_db to development database, and debug to true). This allows the same codebase to be used across development, and deployment versions, so deploy can literally be a git pull.


<h3>Common utilities (cog_common.php)</h3>
Common utility functions



<b>string random_string(int $length)</b>
returns an alphanumeric (lowercase, uppercase, and numeric characters only), random string with $length characters

<b>bool beginsWith( $str, $sub ) </b>
returns true, if $str starts with $sub

<b>bool endsWith( $str, $sub ) </b>
returns true, if $str ends with $sub

<b>array  update($r, $src, $fields = null)</b>
updates the variables in r from src, similiar to python's update function. If $fields is set, updates specific fields only.

<b>void redirect($url)</b>
sets the header to a new location, and returns null -useful for singleliner <i> return redirect("/");</i>


<h3>Error handler (cog_errorhandler.php)</h3>
A general error handler, providing both development, and deployment-level intelligent error handling.

Distinction between deployment, and development mode is made by the $g_cfg["debug"] variable; optimally, this is determined in  _config.php, based on domain-level demuxing (eg. development version to reside under dev.tld.com, and deployment on www.tld.com).

In development mode, this provides a detailed stack trace on all errors, eg:

```
Debug Msg

ERROR: internal error: can not execute: select * from non_existing_table

MySQL error: Table 'guestbook.non_existing_table' doesn't exist
in errorhandler on line 0
Trace:
in class WebHandler::result() called by /www/guestbook/guestbook.php, line 165
....
in function errorHandler(1, "internal error: can not execute:
select * from non_existing_table
MySQL error: Table 'mindverse.cards.non_existing_table' doesn't exist",
 "errorhandler", 0, 0) called by /www/guestbook/engine/cog_errorhandler.php, line 180
```

In deployment mode, a generic error message is displayed to the user, and detailed stack info, along with the error message is sent to the admin's e-mail address, as stored in $g_cfg["adminmail"] .
Generally, sending an e-mail on each, and every server-level error (including PHP's info, and debug log events) has been found to be a very good incentive on keeping the codebase robust against user-level errors, and getting an early warning on problem escalation.

Including the module automatically sets up the necessary error handling variables; during the normal course of handling a request, no calls are made to this module.

For handling consistency problems, two functions are provided:

<b>void internal_error($str)</b>
Raises an internal error, logs the current stackdump, displays the detailed stack trace as described above in debug mode, and halts the execution of the script.

<b>void hack_sign($str)</b>
Similar to the above, except used, when there's a strong suspicion of intentionally malicious user input. Sends an e-mail to the administrator regardless of debug flag.

<h3>Database functions (cog_database.php)</h3>
A KISS MySQL Database abstraction layer, with a couple of benefits of using this module instead of directly querying MySQL:

-Massive shortcut in getting the update / insert queries right via the dbupdate / dbinsert functions
-Read / write separation readily available for your typical Master / Slave MySQL replication configuration:
 * All reads are done via gettable
 * All other functions are write operations
-All parameters to the queries are auto-escaped, eliminating SQL injection attack vectors
-All executed database queries are logged along with their execution time to the $pagequeries global variable; useful during performance tuning.

Public functions:

<b>void initsql(void)</b>
Initalizes the database connection based on the configuration object ($g_cfg).

<b>array gettable(string $query, array $vals = null)</b>
Resolves the query using vals, and returns the result of the select in an array.

<b>bool dbcommit(string $query, array $vals = null) </b>
Resolves the query's parameters, and executes the query immediately.
Returns true on success; uses the errorhandler to raise an error on query execution failure.


<b>int dbinsert(string $table, array $fields, array $funcs = null) </b>
Inserts a new row into given table, with given fields. Parameters supplied via the $funcs array are executed directly, which is useful for mysql function calls (eg. now() ).
Returns the newly inserted row's ID on success.

<b>bool dbupdate(string $table, array $fields, array $where = array(1=>0), array $funcs = null)</b>
Updates the given table with the contents of $fields array for rows matching the $where array.


<b>int dbupinsert(string $table, array $fields, array $onduplicate, array $funcs = null)</b>
Inserts a new row, or updates an existing row on constraint duplicate, into given table, with given fields.


<h3>Templating (cog_scriptor.php)</h3>

Scriptor takes a template file's name, and an array of data from the PHP back-end, and returns an HTML output for display.

Public functions:

<b>constructor Scriptor(string $fn, array $vals)</b>
The constructor takes a template file, and a list of data points

<b>bool validate(string $formname, array $params)</b>
Validates a given form, using the user submitted parameters in $params; returns true, if user submission passes all validators, false otherwise.

<b>string result()</b>
Returns the fullfilled template

<b>bool update($vals)</b>
Inserts new data, and updates the resulting HTML snippet.



Other than substituding each <%data%> with it's respective value, Scriptor also features the following special tags:

<b><cog:if name="varname" value="varvalue"> </b>
The content will be rendered, if <i>varname</i> exists in the datalist, and it's value equals to <i>varvalue</i>.

<b><cog:iterator datasource="source"> </b>
Iterates through an array given as <i>source</i>, and for each element, renders the content, substituding variables within the array.

<b><cog:form name="myform" action="" method="post"> </b>
Generates a cross-site scripting (<a href="http://en.wikipedia.org/wiki/Cross-site_scripting">XSS</a>) safe, validatable form.

<b><cog:validator func="func" target="name"> </b>
Validates <i>name</i> posted parameter by passing it into validator_<i>func</i>, and checking return value; content of the tag is rendered, if validation fails.


New controls can be dynamically added by simply declaring a function with ctrl_ prefix, eg:

```php
// includes a file, and passes it back to Scriptor for evaluation
function ctrl_file($scr, $param, $script, $vals) {
	return $scr->ev(file_get_contents($param["name"]), $vals);
}
```

Control function takes the Scriptor instance, an array of tag parameters, the inner XML content, and the current value array; and returns the built-up HTML snippet.


<h3>Multivariant testing (cog_multivariant.php)</h3>
Cog provides tools for multivariant testing out-of-the-box.

<b>A/B testing, and Multivariant testing</b>
A/B testing is one of the simplest way for conversion funnel optimization. Basically, it's showing two alternative versions of the same page to different visitors, and using your conversion metric, figuring out which version "clicks" with your statistically significant target audience. Usual subjects are Call to Action, Title, "Purchase" button (and presentations thereof).
The difference between A/B testing, and Multivariant testing is the number of tests used: while A/B testing tests 2 alternative versions, Multivariant testing uses an inventory of arbitrary number of variants.

<b>Setting up test variants</b>
Cog achieves Multivariant testing via two Scriptor extension. Including cog_multivariant.php, enables you to simply update your front-end, eg:
```html
<cog:variant name="call_to_action" value="try" >
 Try it now! 30 day money-back guarantee
<cog:variant>

<cog:variant name="call_to_action" value="buy" >
 Buy now! Limited trial, only $2.99
<cog:variant>
```

The Multivariant extension automagically sets up the relevant test rows in the database, and assigns users (based on session) to a randomly chosen test. Once assigned, users will consistently be displayed the same test variant as long as session is maintained.


<b>Hitting test goals</b>
In order to test a variant, you also need to know your goals -what you seek to improve. This can be eg. a checkout "thank you" page, or any user-event.
There are two ways to trigger a goal hit to the multivariant test: either via the cog:goal front-end tag, eg:

Or by calling the multivariant_hitgoal($goalname) function directly.

<b>Monitoring results</b>
Call the multivariant_admin() function from any admin back-end page to display the current score for multivariant tests.

<h3>Front-end demuxer (cog_webhandler.php)</h3>
An intelligent URL-handler for the whole site: <a href="#1329">demuxes</a> between front-end renderers based on URL, calls the correct renderer, inserts the rendered page into the master template, and returns the page HTML output.

<b>constructor WebHandler(array $urlmaps, array $defaults, string $frame)</b>
Creates a new Webhandler. Takes an array of URL - handler functions ( $urlmaps ), an array of defaults to fill up the frame page ($defaults), and the main template frame ($frame).

<b>string WebHandler::result()</b>
Evaluates the current request (based on the contents of $_SERVER array); sets the header for the results, and returns a fully rendered .html page.
The main script might optionally apply any post-processing to the rendered page; then echos it to the user.

<b>array api_demux_call(array $export_func)</b>
Published a JSON-based API trunk.


<h3>Session handler (cog_session.php)</h3>
Including this module passively takes over PHP's file-based session handling, and uses MySQL for storing session data instead.
This module doesn't implement public functions.

<hr size="1">
<b>License</b>

Cog is licensed as <a href="http://creativecommons.org/about/cc0"><b>CC0 Public Domain</b></a>. You can do whatever you want to do with it, including using it for commercial websites.


<b>Source</b>
Cog is available for download via Git:

```
git init
> git pull https://github.com/sdrinf/cog.git
```

<b>Patches / bugfixes / etc</b>
Patches are welcome via github pull requests.



