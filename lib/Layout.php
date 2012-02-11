<?php


class Layout
{

  function since($since)
  {
    $since = time() - $since;
    $chunks = array(
		    array(60 * 60 * 24 * 365 , 'year'),
		    array(60 * 60 * 24 * 30 , 'month'),
		    array(60 * 60 * 24 * 7, 'week'),
		    array(60 * 60 * 24 , 'day'),
		    array(60 * 60 , 'hour'),
		    array(60 , 'minute'),
		    array(1 , 'second')
		    );

    for ($i = 0, $j = count($chunks); $i < $j; $i++) {
      $seconds = $chunks[$i][0];
      $name = $chunks[$i][1];
      if (($count = floor($since / $seconds)) != 0) {
	break;
      }
    }

    $print = ($count == 1) ? '1 '.$name : "$count {$name}s";
    return $print;
  }

  function header()
  { ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
    <link rel="stylesheet" type="text/css" href="<?php print URL_ROOT ?>/buildsite.css"/>
    <link rel="shortcut icon" href="<?php print IMG_ROOT ?>/favicon.ico" type="image/x-icon" />
    <title>Singularity Viewer Automated Build System</title>
  </head>
  <body>
      <div id="everything">
      <div id="page-wrapper">
      <div id="header"></div>
      <div class="container"><p style="font-size: 20px;">Automated Build System</p>
  

<?php
  }

  function footer()
  {
  { ?>
       </div><!-- container -->
       <div class="container">
        <table style="width: 100%; border: none; padding: 0;"><tr>
         <td class="bottom-links"><a href="http://www.singularityviewer.org/">Sigularity Main Site</a></td>
         <td class="bottom-links"><a href="http://www.singularityviewer.org/about">About</a></td>
         <td class="bottom-links"><a href="http://code.google.com/p/singularity-viewer/issues/">Issue Tracker</a></td>
         <td class="bottom-links"><a href="https://github.com/siana/SingularityViewer">Source Tracker</a></td>
      <td width="50%" style="text-align: right;">&copy; 2012 Singularity Viewer Project</td>
        </tr></table>
       </div> 
      </div><!-- everything -->
    </div><!-- page-wrapper -->
  </body>
</html>
  
<?php
  }
  }
}
