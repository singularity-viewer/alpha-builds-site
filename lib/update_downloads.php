#!/usr/bin/php
<?php

define("SITE_ROOT", realpath(dirname(__file__) . "/.."));
require_once SITE_ROOT . "/lib/init.php";
$sync = array("bz2", "log", "exe", "dmg");

$DB->query("begin");
$DB->query("delete from downloads");

if ($dh = opendir(SITE_ROOT)) {
  while (($file = readdir($dh)) !== false) {
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    if (in_array($ext, $sync)) {
      $q = kl_str_sql("insert into downloads(file_name) values (!s)", $file);
      $DB->query($q);
    }
  }
  closedir($dh);
}

$DB->query("commit");
