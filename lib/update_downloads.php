#!/usr/bin/php
<?php

define("SITE_ROOT", realpath(dirname(__file__) . "/.."));
require_once SITE_ROOT . "/lib/init.php";

function get_downloads()
{
  global $DB;

  $ret = array();

  if (!$res = $DB->query("select file_name from downloads")) return;

  while ($row = $DB->fetchRow($res)) {
    $ret[] = $row["file_name"];
  }

  return $ret;
}

$all_downloads = get_downloads();
$sync = array("bz2", "log", "exe", "dmg");


if ($dh = opendir(SITE_ROOT)) {
  while (($file = readdir($dh)) !== false) {
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    if (in_array($ext, $sync) && !in_array($file, $all_downloads)) {
      $q = kl_str_sql("insert into downloads(file_name) values (!s)", $file);
      $DB->query($q);
    }
  }
  closedir($dh);
}

    