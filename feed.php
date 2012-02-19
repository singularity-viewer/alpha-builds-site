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


function sort_by_date($a, $b) 
{
	if ($a["time"] < $b["time"]) {
		return 1;
	} else if ($a["time"] > $b["time"]) {
		return -1;
	}
	return 0;
}

function print_changeset($row)
{
	$author = parse_email($row["author"]);
	$gid = md5($author["email"]);
	$avatar = (USE_SSL ? "https://secure.gravatar.com" : "http://www.gravatar.com") .
		"/avatar/$gid?r=x&amp;d=mm&amp;s=48";
	return '<tr><td valign="top" align="center"><img src="' . $avatar . '" alt="Avatar"/><br/>' 
		. htmlspecialchars($author["name"]) . '</td>'
        . '<td valign="top"><a href="https://github.com/siana/SingularityViewer/commit/' . htmlspecialchars($row["hash"]) . '">' . htmlspecialchars($row["hash"]) . '</a><br/>'
		. htmlspecialchars($row["time"]). "<br/><br/>\n\n<pre>" 
		. htmlspecialchars($row["message"]) . '</pre></td>';
}

function print_changes($current, $next)
{
	global $DB;

	$ret = "";

	$revs = array();
	if (!($res = $DB->query(kl_str_sql("select revisions from changes where build<=!i and build>!i order by build desc", $current->nr, $next->nr)))) {
		return $ret;
	} else {
		while ($row = $DB->fetchRow($res)) {
			$revs = array_merge($revs, explode(",", $row["revisions"]));
		}
	}

	if ($res = $DB->query("select * from revs where hash in ('" . implode("','", $revs) . "')")) {
		$ret .= '<table width="100%">';

		$changesets = array();

		while ($row = $DB->fetchRow($res)) {
			$changesets[] = $row;
		}

		usort($changesets, "sort_by_date");

		foreach ($changesets as $change) {
			$ret .= print_changeset($change);
		}

		$ret .= '</table>';
	}

	return $ret;
}

$buildFeed = new FeedWriter(ATOM);
$buildFeed->setTitle('Singularity Automatic Development Builds');
$buildFeed->setLink('http://files.streamgrid.net/singularity/');
$buildFeed->setDescription('Latest automated build of the Singularity Viewer project');
// $buildFeed->setImage('Testing the RSS writer class',
//		    'http://www.ajaxray.com/projects/rss',
//		    'http://www.rightbrainsolution.com/images/logo.gif');

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
	for ($i = 0; $i < $pageSize; $i++) {
		if (!isset($builds[$i])) continue;
		$newItem = $buildFeed->createNewItem();
		$newItem->setTitle("Singularity Alpha build " . $builds[$i]->nr);
		$newItem->setLink("http://files.streamgrid.net/singularity/?build_id=" . $builds[$i]->nr);
		$newItem->setDate($builds[$i]->modified);
		if (isset($builds[$i+1])) {
			$newItem->setDescription(print_changes($builds[$i], $builds[$i + 1]));
		}
		$buildFeed->addItem($newItem);
	}

	$buildFeed->genarateFeed();

}


/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: noet sw=4 ts=4 fdm=marker
 * vim<600: noet sw=4 ts=4
 */
