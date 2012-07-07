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

function import_rev($raw, $chan)
{
	global $DB;

	$log = explode("\n", rtrim($raw));

	$hash = $log[0];
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

function save_build_changes($changes, $chan)
{
	global $DB;


	$DB->query("begin transaction");
	foreach ($changes as $buildNr => $revs) {
		$DB->query(kl_str_sql("insert into changes (build, chan, revisions) values (!i, !s, !s)", $buildNr, $chan, implode(",", $revs)));
	}
	$DB->query("commit");

}

function update_revs()
{
	global $DB, $CHANS;

	$DB->query("begin transaction"); 
	if (!($res = $DB->query("delete from revs"))) {
		$DB->query("create table revs(hash varchar, chan varchar, author varchar, time timestamp, message text, diff text, primary key(hash))");
	}

	$DB->query("commit"); 

	$DB->query("begin transaction");
	if (!($res = $DB->query("delete from changes"))) {
		$DB->query("create table changes (build integer, chan varchar, revisions text, primary key(build, chan))");
	}
	$DB->query("commit");

	foreach ($CHANS as $chan => $branch) {
		exec("git fetch --all 2>&1");
		if ($branch != "HEAD") {
			exec("git reset --soft $branch 2>&1");
		}

		$DB->query("begin transaction");
		
		$revs = array_reverse(explode(chr(0), rtrim(`git rev-list HEAD --header`)));
		$nrRevs = count($revs);

		print "Importing $nrRevs revisions for $chan\n";

		for ($i=0; $i<$nrRevs; $i++) {
			import_rev($revs[$i], $chan);
		}

		$res = $DB->query("commit");

		$revs = explode("\n", rtrim(`git rev-list HEAD`));

		$res = 0;
		$c =0;
		$changesAt = array();

		while (true) {
			exec("git reset --soft HEAD~ 2>&1", $out, $res);
			if ($res != 0) {
				break;
			} else {
				$c++;
				$newRevs = explode("\n", rtrim(`git rev-list HEAD`));
				$changes = array_diff($revs, $newRevs);
				$nrChanges = count($changes);
				$build = count($revs);
				$revs = $newRevs;
				$changesAt[$build] = $changes;
				print $nrChanges . " changes in build $build\n";
				if ($build < 2169) break; // this is when we started building
			}
		}
		save_build_changes($changesAt, $chan);
	}


	print "Number resets: $c\n";
	exec("git fetch --all 2>&1");

}

function update_builds()
{
	global $DB;

	$builds = glob(SITE_ROOT . "/*_*_Setup.exe");
	$latest = 0;

	// check if table exists
	if (!($res = $DB->query("select count(*) as c from builds"))) {
		$DB->query("create table builds(nr integer, chan varchar, version varchar, file varchar, modified timestamp, primary key(nr, chan))");
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

$DB->query("PRAGMA synchronous = OFF");
chdir(SITE_ROOT . "/lib/source");
exec("git fetch --all");
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
