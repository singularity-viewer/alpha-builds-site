<?php

class DBH
{
	public
		$db_name,
		$db_pass,
		$db_user,
		$db_host,
		$dbh,
		$last_error = "";

	function log($line)
	{
		return;
		static $f = false;
		static $failed = false;

		if ($failed) {
			return false;
		}

		if (!$f) {
			$f = @fopen(SITE_ROOT.'/lib/site.log', 'a');
		}

		if (!$f) {
			$failed = true;
			return false;
		}

		@fwrite($f, "[".date('Y-m-d H:i')."] ".$line."\n");
	}

	function connect($db_name, $db_host, $db_user, $db_pass)
	{
		$this->db_name = $db_name;
		$this->db_pass = $db_pass;
		$this->db_user = $db_user;
		$this->db_host = $db_host;

		try {
			$this->dbh = new SQLite3($db_name, SQLITE3_OPEN_READWRITE);
		} catch (Exception $e) {
			DBH::log("[error] connection to database failed: " . $e->getMessage());
			return false;
		} 

		return true;
	}

	function query($q)
	{
		$res = $this->dbh->query($q);

		if (!$res) {
			DBH::log("[error] ".$q);
			$error_msg = $this->dbh->lastErrorMsg();
			DBH::log("[error_msg] " . $error_msg);
			$this->last_error = $error_msg;

			$e = debug_backtrace();
			$c = count($e);
			$btr = "";

			for ($i=0; $i<$c; $i++) {
				$btr .= "{$e[$i]['class']}::{$e[$i]['function']} {$e[$i]['file']}({$e[$i]['line']})\n";
			}

			DBH::log("[backtrace]\n".$btr);

			return false;
		} else {
			DBH::log("[success] ".$q);
			return $res;
		}
	}

	function loadFromDbRow(&$obj, $res, $row)
	{
		foreach ($row as $symbolicName => $nativeName){
			$obj->{$symbolicName} = $nativeName;
		}
		return true;
	}

	function fetchRow($res)
	{
		return $res->fetchArray();
	}
  
}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: sw=4 ts=4 fdm=marker
 * vim<600: sw=4 ts=4
 */
