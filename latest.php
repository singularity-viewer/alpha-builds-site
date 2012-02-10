<?php

// Make sure this is not cached
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
header("Content-Type: text/plain");

$chan = "SingularityAlpha";

if (isset($_GET["chan"])) {
	$chan = preg_replace("%[^\\w_-]%i", "", $_GET["chan"]);
}

$files = glob($chan . "_*.exe");

if (count($files) === 0) {
	header("HTTP/1.0 404 Not Found");
	header("Content-Type: text/plain");
	print "Requested channel was not found";
	die();
}

$files = array_reverse($files);
$host  = $_SERVER['HTTP_HOST'];
$uri   = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$latest = urlencode($files[0]);

header("Location: http://${host}${uri}/${latest}");

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: noet sw=4 ts=4 fdm=marker
 * vim<600: noet sw=4 ts=4
 */


