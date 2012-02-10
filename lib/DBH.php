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

		$this->dbh = @sqlite_popen($db_name, 0666, $error_msg);

		if (!$this->dbh) {
			DBH::log("[error] connection to database failed: $error_msg");
			return false;
		} 

		return true;
	}

	function query($q)
	{
		$res = @sqlite_query($this->dbh, $q, SQLITE_BOTH, $error_msg);

		if (!$res) {
			DBH::log("[error] ".$q);
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
			if ($res !== TRUE) {
				$result_id = (int)$res;
				if (!isset($this->field_desc[$result_id])) {
					$nf = sqlite_num_fields($res);
					for ($i=0; $i<$nf; $i++) {
						$this->field_desc[$result_id][sqlite_field_name($res, $i)] = sqlite_field_name($res, $i);
					}
				}
			}
			DBH::log("[success] ".$q);
			return $res;
		}
	}

	function loadFromDbRow(&$obj, $res, $row)
	{
		foreach ($row as $symbolicName => $nativeName){
			if ($nativeName && ($this->field_desc[(int)$res][$symbolicName] == "timestamp" ||
								$this->field_desc[(int)$res][$symbolicName] == "date")) {
				$obj->{$symbolicName} = strtotime($nativeName);
			} else {
				$obj->{$symbolicName} = $nativeName;
			}
		}
		return true;
	}

	function fetchRow($res)
	{
		return @sqlite_fetch_array($res);
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
