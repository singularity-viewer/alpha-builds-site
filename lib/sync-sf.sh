#!/bin/bash

wd=/var/www/singularity
cd $wd
rsync -av -e ssh *.exe *.dmg *.tar.bz2 *.log latifer@frs.sf.net:/home/frs/project/singularityview/alphas
$wd/lib/update_downloads.php
scp lib/singularity_revisions.db  latifer@frs.sf.net:/home/project-web/singularityview/htdocs/alpha/lib
