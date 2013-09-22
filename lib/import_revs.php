#!/usr/bin/php
<?php

if (PHP_SAPI != "cli") {
	print "Utility script 0x55424523.";
	die();
}

// create table revs(id integer, hash varchar, author varchar, time timestamp, message text, diff text, primary key(id));
// create index hash_index on revs(hash);

define("SITE_ROOT", realpath(dirname(__file__) . "/.."));
require_once SITE_ROOT . "/lib/init.php";

function import_rev(&$existing, $raw, $chan)
{
	global $DB;

	$log = explode("\n", rtrim($raw));

	$hash = $log[0];
	if (isset($existing[$hash])) return;
	$author = "";
	$date = "";
	$msg = "";
	$inMsg = false;
	$nrLog = count($log);

	for ($i=0; $i<$nrLog; $i++) {
		if ($inMsg) {
			$msg .=  substr($log[$i], 4);
			if ($i<$nrLog-1) {
				$msg .= "\n";
			}
		} else {
			if (preg_match("|^author\\s*([^>]*>)\\s*([\\d]+)\\s*(.*)|i", $log[$i], $m)) {
				$author = $m[1];
				$date = (int)$m[2];
			} else if  (!trim($log[$i])) {
				$inMsg = true;
			}
		}
	}

	$DB->query(
	   kl_str_sql(
		  "insert into revs (hash, chan, author, time, message) values (!s, !s, !s, !t, !s)",
		                     $hash, $chan, $author, $date, $msg));
  
}

function import_revs()
{
	global $DB, $CHANS;

	$DB->query("begin transaction"); 
	if (!($res = $DB->query("select * from revs"))) {
		$DB->query("create table revs(hash varchar, chan varchar, author varchar, time timestamp, message text, diff text, primary key(hash))");
	}

	$existing_revs = array();
	while ($row = $DB->fetchRow($res)) {
		$existing_revs[$row['hash']] = 1;
	}

	$DB->query("commit"); 

	foreach ($CHANS as $chan => $branch) {
		exec("git fetch --all 2>&1");
		if ($branch == "HEAD") {
			$branch = "FETCH_HEAD";
		}
		exec("git reset --soft $branch 2>&1");

		$DB->query("begin transaction");
		
		$revs = array_reverse(explode(chr(0), rtrim(`git rev-list HEAD --header`)));
		$nrRevs = count($revs);

		print "Importing $nrRevs revisions for $chan\n";

		for ($i = 0; $i < $nrRevs; $i++) {
			import_rev($existing_revs, $revs[$i], $chan);
		}

		$res = $DB->query("commit");
	}

}

function update_builds()
{
	global $DB;

	$builds = glob(SITE_ROOT . "/*_*_Setup.exe");
	$latest = 0;

	// check if table exists
	if (!($res = $DB->query("select count(*) as c from builds"))) {
		$DB->query("create table builds(nr integer, chan varchar, version varchar, hash varchar, file varchar, modified timestamp, primary key(nr, chan))");
	}
	
	for ($i=0; $i<count($builds); $i++) {
		$file = basename($builds[$i]);
		if (preg_match("|^(\w+)_(\d+)-(\d+)-(\d+)-(\d+)_|", $file, $m)) {
			$chan = $m[1];
			$major = $m[2];
			$minor = $m[3];
			$maintenance = $m[4];
			$build = $m[5];
			$modified = filemtime(SITE_ROOT . "/" . $file);
			$version = "$major.$minor.$maintenance.$build";
			$res = $DB->query(kl_str_sql("select count(*) as c from builds where nr=!i and chan=!s", $build, $chan));
			$row = $DB->fetchRow($res);
			if ($row["c"] === "0") {
				$DB->query(kl_str_sql("insert into builds (nr, chan, version, file, modified) ".
									  "values (!i, !s, !s, !s, !t)",
									  $build, $chan, $version, $file, $modified));
			}
		}
	}

}

function save_build_changes($changes, $chan)
{
	global $DB;


	$DB->query("begin transaction");
	foreach ($changes as $buildNr => $revs) {
		$DB->query(kl_str_sql("delete from  changes where build=!i and chan=!s", $buildNr, $chan));
		$DB->query(kl_str_sql("insert into changes (build, chan, revisions) values (!i, !s, !s)", $buildNr, $chan, implode(",", $revs)));
	}
	$DB->query("commit");

}


function set_changes($build, $chan)
{
	global $DB;

	$DB->query("begin transaction");
	if (!($res = $DB->query("select count(*) from changes"))) {
		$DB->query("create table changes (build integer, chan varchar, revisions text, primary key(build, chan))");
	}
	$DB->query("commit");

	if (!($res = $DB->query(kl_str_sql("select * from builds where chan=!s and nr<=!i order by nr desc", $chan, $build)))) {
		return;
	}

	if (!($current = $DB->fetchRow($res))) return;
	if (!($previous = $DB->fetchRow($res))) return;

	chdir(SITE_ROOT . "/lib/source");
	
	$revs = explode("\n", rtrim(`git rev-list {$previous["hash"]}..{$current["hash"]}`));
	$changes = array();
	$changes[$build] = $revs;
	save_build_changes($changes, $chan);
}


function add_build($build, $chan, $version, $hash)
{
	global $DB;

	// check if table exists
	if (!($res = $DB->query("select count(*) as c from builds"))) {
		$DB->query("create table builds(nr integer, chan varchar, version varchar, hash varchar,  file varchar, modified timestamp, primary key(nr, chan))");
	}
	
	$res = $DB->query(kl_str_sql("select count(*) as c from builds where nr=!i and chan=!s", $build, $chan));
	$row = $DB->fetchRow($res);
	if ($row["c"] === "0") {
		$DB->query(kl_str_sql("insert into builds (nr, chan, version, hash, modified) ".
							  "values (!i, !s, !s, !s, !t)",
							  $build, $chan, $version, $hash, time() - date("Z")));
	}
}

/* main */
if ($_SERVER['argc'] < 4) {
	print "Too few arguments.\nUsage: import_revs.php <channel> <version> <hash>\n";
	exit(1);
}

$CHAN = $_SERVER['argv'][1];
$VERSION = $_SERVER['argv'][2];
$HASH = $_SERVER['argv'][3];
$build_parts = explode(".", $VERSION);

if (count($build_parts) != 4) {
	print "Wrong version format, expected x.y.z.build\n";
	die();
}
$BUILD = $build_parts[3];

$DB->query("PRAGMA synchronous = OFF");
chdir(SITE_ROOT . "/lib/source");
exec("git fetch --all");

import_revs();
add_build($BUILD, $CHAN, $VERSION, $HASH);
set_changes($BUILD, $CHAN);

//chdir(SITE_ROOT);
//update_builds();

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: noet sw=4 ts=4 fdm=marker
 * vim<600: noet sw=4 ts=4
 */
