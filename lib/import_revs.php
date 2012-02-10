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

function import_rev($id, $hash)
{
	global $DB;

	print "Importing revision number $id with hash $hash\n";
	$log = explode("\n", rtrim(`git log -n1 $hash`));

	$author = "";
	if (preg_match("|Author:\\s*(.*)|i", $log[1], $m)) {
		$author = $m[1];
	}

	$date = "";
	if (preg_match("|Date:\\s*(.*)|i", $log[2], $m)) {
		$date = strtotime($m[1]);
	}

	$msg = "";
	$nrLog = count($log);
	for ($i=4; $i<$nrLog; $i++) {
		$msg .= substr($log[$i], 4);
		if ($i<$nrLog-1) {
			$msg .= "\n";
		}
	}

	$DB->query(
	   kl_str_sql(
		  "insert into revs (id, hash, author, time, message) values (!i, !s, !s, !t, !s)",
		                     $id, $hash, $author, $date, $msg));
  
}

function update_source()
{
	exec("git reset --hard", $out, $res);
	if ($res) {
		DBH::log("Command failed: ", implode("\n", $out));
		return;
	}

	exec("git pull", $out, $res);
	if ($res) {
		DBH::log("Command failed: ", implode("\n", $out));
		return;
	}

	print implode("\n", $out) . "\n";
}

function update_revs()
{
	global $DB;

	$revsStr = rtrim(`git rev-list HEAD | tac`);
	$revs = explode("\n", $revsStr);
	$nrRevs = count($revs);


	$latest = 0;
	$res = $DB->query("select max(id) as id from revs");
	if ($row = $DB->fetchRow($res)) {
		if ($DB->loadFromDbRow($dbLatest, $res, $row)) {
			$latest = (int)$dbLatest->id;
		}
	}

	print "Found $nrRevs revisions\n";
	print "Latest revision in the database: $latest\n";

	if ($latest < $nrRevs) {
		for ($rev = $latest + 1; $rev <= $nrRevs; $rev++) {
			import_rev($rev, $revs[$rev - 1]);
		}
	}
}

function update_builds()
{
	global $DB;

	$builds = glob(SITE_ROOT . "/*_*_Setup.exe");
	$latest = 0;

	// check if table exists
	if (!($res = $DB->query("select count(*) as c from builds"))) {
		$DB->query("create table builds(nr integer, chan varchar, version varchar, file varchar, primary key(nr, chan))");
	}
	
	for ($i=0; $i<count($builds); $i++) {
		$file = basename($builds[$i]);
		if (preg_match("|^(\w+)_(\d+)-(\d+)-(\d+)-(\d+)_|", $file, $m)) {
			$chan = $m[1];
			$major = $m[2];
			$minor = $m[3];
			$maintenance = $m[4];
			$build = $m[5];
			$version = "$major.$minor.$maintenance.$build";
			$res = $DB->query(kl_str_sql("select count(*) as c from builds where nr=!i and chan=!s", $build, $chan));
			$row = $DB->fetchRow($res);
			if ($row["c"] === "0") {
				$DB->query(kl_str_sql("insert into builds (nr, chan, version, file) ".
									  "values (!i, !s, !s, !s)",
									  $build, $chan, $version, $file));
			}
		}
	}

}

chdir(SITE_ROOT . "/lib/source");
update_source();
update_revs();

chdir(SITE_ROOT);
update_builds();

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: noet sw=4 ts=4 fdm=marker
 * vim<600: noet sw=4 ts=4
 */
