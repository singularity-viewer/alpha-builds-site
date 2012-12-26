#!/usr/bin/php
<?php

define("SITE_ROOT", realpath(dirname(__file__) . "/.."));
require_once SITE_ROOT . "/lib/init.php";


function get_old_builds($chan, $nrToKeep)
{
	global $DB;

	$builds = array();

	if ($res = $DB->query(kl_str_sql("select * from builds where chan=!s and nr not in (select nr from builds where chan=!s order by nr desc limit !i)", $chan, $chan, $nrToKeep))) {
		while ($row = $DB->fetchRow($res)) {
		
			$build = new stdClass;
			$DB->loadFromDbRow($build, $res, $row);
			$linux_file = "SingularityAlpha-i686-{$build->version}.tar.bz2";
			$build->linux_file = file_exists($linux_file) ? $linux_file : false;
			$osx_file = "SingularityAlpha_" . str_replace(".", "_", $build->version) . ".dmg";
			$build->osx_file = file_exists($osx_file) ? $osx_file : false;
			$builds[] = $build;
		}
	}
	
	return $builds;
}

chdir(SITE_ROOT);
$builds = get_old_builds("SingularityAlpha", 21);
$nrBuilds = count($builds);

for ($i=0; $i<$nrBuilds; $i++) {

	$b = $builds[$i];
	$f = array();
	$f[] = $b->file;
	if ($b->linux_file) $f[] = $b->linux_file;
	if ($b->osx_file) $f[] = $b->osx_file;

	print "Cleaning build nr.: {$b->nr}\n";

	for ($j=0; $j<count($f); $j++) {
		print "    Deleting {$f[$j]}\n";
		@unlink(SITE_ROOT . "/" . $f[$j]);
		@unlink(SITE_ROOT . "/" . $f[$j] . ".log");
	}

	$DB->query(kl_str_sql("delete from builds where nr=!i and chan=!s", $b->nr, $b->chan));

}


/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: noet sw=4 ts=4 fdm=marker
 * vim<600: noet sw=4 ts=4
 */
