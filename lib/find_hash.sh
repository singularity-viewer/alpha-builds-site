#!/bin/bash

# set -x
ROOT=/var/www/singularity
SOURCE="$ROOT/lib/source"
DB="$ROOT/lib/singularity_revisions.db"

TMP_LIST="/tmp/find_hash_tmp.lst"
BUILD_LIST="/tmp/find_hash_tmp.bulds"

function update_source() {
    cd $SOURCE
    # git fetch --all
    git reset --soft FETCH_HEAD
}

# main

chan="SingularityAlpha"

update_source
git rev-list HEAD > "$TMP_LIST"
sqlite $DB "select nr from builds where (hash = '' or hash is null) and chan='$chan' order by nr desc" > "$BUILD_LIST"


cat "$BUILD_LIST" | while read build; do
    git reset --soft FETCH_HEAD

    cat "$TMP_LIST" | while read rev; do
	git reset --soft $rev
	nr=$(git rev-list HEAD | wc -l)
	if [ "x$nr" == "x$build" ]; then
	    echo "$build = $rev"
	    sqlite $DB "update builds set hash='$rev' where nr='$build' and chan='$chan'"
	    break
	fi
    done
done
