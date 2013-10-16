<?php
define("SITE_ROOT", realpath(dirname(__file__)));
require_once SITE_ROOT . "/lib/init.php";

$builds = array();

$res = $DB->query("select chan, nr as build_nr, version, hash, modified from builds where chan='SingularityAlpha' order by nr desc, chan asc limit 1");
$row = $DB->fetchRow($res);
$build = new stdClass;

foreach($row as $key=>$val) {
  if (false === filter_var($key, FILTER_VALIDATE_INT)) {
    $build->$key = $val;
  }
}

header("Content-Type: application/json");
print json_encode($build);
