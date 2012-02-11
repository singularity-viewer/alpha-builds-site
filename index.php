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

function print_changes($current, $next)
{
	global $DB;
	if ($res = $DB->query(kl_str_sql("select * from revs where id<=!i and id>!i", $current->nr, $next->nr))) {
		print '<table style="width: 100%;">';

		while ($row = $DB->fetchRow($res)) {
			$author = parse_email($row["author"]);
			$gid = md5($author["email"]);
			print '
            <tr>
              <td rowspan="2" style="text-align: center;"><img src="http://www.gravatar.com/avatar/' . $gid . '?r=x&amp;d=mm&amp;s=64"/><br />' .
				htmlspecialchars($author["name"]) . '</td>
              <td><a href="https://github.com/siana/SingularityViewer/commit/' . htmlspecialchars($row["hash"]) . '">' . htmlspecialchars($row["hash"]) . '</a></td>
              <td>' . htmlspecialchars($row["time"]). 
              ' (' . Layout::since(strtotime($row["time"])) . ' ago)</td>
           </tr>
           <tr>
             <td colspan="2" width="99%"><pre>' . htmlspecialchars($row["message"]) . '</pre></td>
           </tr>';

		#	pre_dump($row);
		}

		print '</table>';
	}
}

Function print_build($current, $next)
{
	print "
		<tr style=\"background-color: #303030;\">
		  <th><a href=\"#\">Build " . htmlspecialchars($current->nr). "</a></th>
		  <th>" . htmlspecialchars($current->modified). " (" . Layout::since(strtotime($current->modified)) . " ago)</th>
		  <th>" . htmlspecialchars($current->chan). "</th>
		  <th><a href='" . URL_ROOT . "/" . $current->file . "'>Windows Installer <img src=\"" . IMG_ROOT . "/dl.gif\" /></a>&nbsp;&nbsp;
              <a href='" . URL_ROOT . "/" . $current->file . ".log'>Build Log</a></th>
		</tr>";
	if ($next) {
		print '<tr><td colspan="4">';
		print_changes($current, $next);
		print "</td></tr>";
	}

}

Layout::header();

$chan = "SingularityAlpha";

$pageSize = 20;

$builds = array();

if ($res = $DB->query(kl_str_sql("select * from builds where chan=!s order by nr desc limit !i", $chan, $pageSize + 1))) {
	while ($row = $DB->fetchRow($res)) {
		
		$build = new stdClass;
		$DB->loadFromDbRow($build, $res, $row);
		$builds[] = $build;
	}
}

$nrBuilds = count($builds);

if ($nrBuilds) {
	print '<table class="build-list">';


	for ($i = 0; $i < $pageSize; $i++) {
		if (!isset($builds[$i])) continue;
		print_build($builds[$i], $builds[$i + 1]);
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
