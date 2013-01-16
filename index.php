<?php

define("SITE_ROOT", realpath(dirname(__file__)));
require_once SITE_ROOT . "/lib/init.php";

function parse_email($email) 
{
	$ret = array("name" => $email, "email" => "");
	if (preg_match("|([^\<]*)<([^>]*)>|", $email, $m)) {
		$ret["name"] = trim($m[1]);
		$ret["email"] = trim($m[2]);
	}
	return $ret;
}

function print_changeset($row)
{
	$author = parse_email($row["author"]);
	$gid = md5(strtolower($author["email"]));
	$avatar = (USE_SSL ? "https://secure.gravatar.com" : "http://www.gravatar.com") .
		"/avatar/$gid?r=x&amp;d=mm&amp;s=48";
	print '
            <tr>
              <td rowspan="2" style="text-align: center;"><img src="' . $avatar . '" alt="Avatar"/><br />' .
				htmlspecialchars($author["name"]) . '</td>
              <td><a href="https://github.com/siana/SingularityViewer/commit/' . htmlspecialchars($row["hash"]) . '">' . htmlspecialchars($row["hash"]) . '</a></td>
              <td>' . htmlspecialchars($row["time"]). 
              ' (' . Layout::since(strtotime($row["time"])) . ' ago)</td>
           </tr>
           <tr>
             <td colspan="2" width="99%"><pre>' . htmlspecialchars($row["message"]) . '</pre></td>
           </tr>';
}

function sort_by_date($a, $b) 
{
	if ($a["time"] < $b["time"]) {
		return 1;
	} else if ($a["time"] > $b["time"]) {
		return -1;
	}
	return 0;
}

function print_changes($current, $next, $chan)
{
	global $DB;
	$revs = array();
	if (!($res = $DB->query(kl_str_sql("select revisions from changes where chan=!s and build<=!i and build>!i order by build desc", $chan, $current->nr, $next->nr)))) {
		return;
	} else {
		while ($row = $DB->fetchRow($res)) {
			$revs = array_merge($revs, explode(",", $row["revisions"]));
		}
	}

	if ($res = $DB->query(kl_str_sql("select * from revs where chan=!s and hash in ('" . implode("','", $revs) . "')", $chan))) {

		$changesets = array();

		while ($row = $DB->fetchRow($res)) {
			$changesets[] = $row;
		}

		if (count($changesets) == 0) return;

		print '<table style="width: 99%;">';

		usort($changesets, "sort_by_date");
		
		foreach ($changesets as $change) {
			print_changeset($change);
		}

		print '</table>';
	}
}

Function print_build($current, $next, $buildNr, $chan)
{
	print "
		<tr style=\"background-color: #303030;\">
		  <th><a href=\"" . URL_ROOT ."?build_id={$current->nr}\">Build " . htmlspecialchars($current->nr). "</a><br/>";


	if ($next) {
		if (($current->linux_file && $current->osx_file && $current->linux64_file)) {
			print "<br/><br/>";
		}
		elseif (($current->linux_file && $current->osx_file)) {
			print "<br/>";
		}
		print '
            <a class="dimmer" href="javascript:void(0)" id="toggle_link_'. $current->nr . '" onclick="javascript:toggleChanges('. $current->nr . ')">' .
	        ($buildNr ? 'Hide changes &lt;&lt;' : 'Show changes &gt;&gt;') . '</a>';
	}

 	print "</th><th>" . htmlspecialchars($current->modified). " (" . Layout::since(strtotime($current->modified)) . " ago)</th>
		  <th>" . htmlspecialchars($current->chan). "</th>
		  <th><a href='" . URL_ROOT . "/" . $current->file . "'><img src=\"" . IMG_ROOT . "/dl.gif\" alt=\"Download Windows Build\"/>&nbsp;Windows</a>&nbsp;&nbsp;
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a class='dimmer' href='" . URL_ROOT . "/" . $current->file . ".log'>Build Log</a>";

	if ($current->linux_file) {
		print "<br/><a href='" . URL_ROOT . "/" . $current->linux_file . "'><img src=\"" . IMG_ROOT . "/dl.gif\" alt=\"Download Linux Build (32 bit)\"/>&nbsp;Linux (32 bit)</a>";
		if (file_exists($current->linux_file . ".log")) {
			print "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a class='dimmer' href='" . URL_ROOT . "/" . $current->linux_file . ".log'>Build Log</a>";
		}

	}


	if ($current->linux64_file) {
		print "<br/><a href='" . URL_ROOT . "/" . $current->linux64_file . "'><img src=\"" . IMG_ROOT . "/dl.gif\" alt=\"Download Linux Build (64 bit)\"/>&nbsp;Linux (64 bit)</a>";
		if (file_exists($current->linux64_file . ".log")) {
			print "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a class='dimmer' href='" . URL_ROOT . "/" . $current->linux64_file . ".log'>Build Log</a>";
		}
	}

	if ($current->osx_file) {
		print "<br/><a href='" . URL_ROOT . "/" . $current->osx_file . "'><img src=\"" . IMG_ROOT . "/dl.gif\" alt=\"Download Mac OS X Build\"/>&nbsp;Mac OS X</a>";
		if (file_exists($current->osx_file . ".log")) {
			print "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a class='dimmer' href='" . URL_ROOT . "/" . $current->osx_file . ".log'>Build Log</a>";
		}
	}

	print "</th></tr>";

	if ($next) {
		print '<tr' . ($buildNr ? '' : ' style="display: none;"') . ' id="changes_' . $current->nr . '"><td colspan="4">';
		print_changes($current, $next, $chan);
		print "</td></tr>";
	}

}

function chan_selector($current_chan)
{
	//	return;
	global $CHANS;
	print '<form method="get" action="index.php">';
	print 'Select channel&nbsp;<select name="chan" onchange="this.form.submit()">';
	foreach($CHANS as $chan => $ref) {
		print "<option value=\"$chan\"" . ($current_chan == $chan ? " selected=\"selected\"" : "") . ">$chan</option>";
	}
	print '</select><noscript><input type="submit" value="Change"/></noscript></form><br />';

}

Layout::header();

if (isset($_GET["chan"]) && isset($CHANS[$_GET["chan"]])) {
	$chan = $_GET["chan"];
} else {
	// $chan = "SingularityMultiWearable";
	$chan = "SingularityAlpha";
}

$pageSize = 20;

$builds = array();

$buildNr = 0;
$where = "";

if (isset($_GET["build_id"])) {
	$buildNr = (int)$_GET["build_id"];
	$pageSize = 1;
	$where = kl_str_sql(" and nr <= !i ", $buildNr); 
} else {
	chan_selector($chan);
}

if ($res = $DB->query(kl_str_sql("select * from builds where chan=!s $where order by nr desc limit !i", $chan, $pageSize + 1))) {
	while ($row = $DB->fetchRow($res)) {
		
		$build = new stdClass;
		$DB->loadFromDbRow($build, $res, $row);

		$linux_file = "SingularityAlpha-i686-{$build->version}.tar.bz2";
		$build->linux_file = file_exists($linux_file) ? $linux_file : false;

		$linux64_file = "SingularityAlpha-x86_64-{$build->version}.tar.bz2";
		$build->linux64_file = file_exists($linux64_file) ? $linux64_file : false;

		$osx_file = "SingularityAlpha_" . str_replace(".", "_", $build->version) . ".dmg";
		$build->osx_file = file_exists($osx_file) ? $osx_file : false;

		$builds[] = $build;
	}
}

$nrBuilds = count($builds);

if ($nrBuilds) {
	print '<table class="build-list">';


	for ($i = 0; $i < $pageSize; $i++) {
		if (!isset($builds[$i])) continue;
		print_build($builds[$i], $builds[$i + 1], $buildNr, $chan);
	}

	print '</table>';

}
	
Layout::footer();

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: noet sw=4 ts=4 fdm=marker
 * vim<600: noet sw=4 ts=4
 */
