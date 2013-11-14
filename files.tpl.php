#!/bin/bash

# abort on errors
set -o errexit ; set -o nounset

function verbose {
  comment "$1"
  VERBOSE_CMD=$2
  if [[ ! -z "${VERBOSE_CMD}" ]]; then
    echo `now` ">> ${VERBOSE_CMD}"
    VERBOSE_RVAL=1
  else
    VERBOSE_RVAL=2
  fi
}

function prettytime {
  printf ""%dh\ %dm\ %ds"\n" $(($1/3600)) $(($1%3600/60)) $(($1%60))
}

function now {
  date -u +%T
}

function comment {
  VERBOSE_COMMENT=$1
  echo "         #"
  echo "         #  ${VERBOSE_COMMENT}"
  echo "         #"
}


verbose "Start: `date -u`" ""
MIGRATION_START=`date -u +%s`

verbose "Making sure <?php echo $DST_IMPORT_DIR ?> exists" "<?php echo $SSH_CMD ?> mkdir -vp <?php echo $DST_IMPORT_DIR ?>"


comment "Executing rsync to Acquia"
(
  set -x 
  rsync -rltD -v --delete --prune-empty-dirs --temp-dir=<?php echo $DST_IMPORT_DIR ?> -e "ssh -o stricthostKeychecking=no -i /tmp/acquia_migrate/acquia.rsa" --exclude=__MACOSX --exclude=.git --exclude=.svn --exclude=.DS_Store <?php echo $SRC_PATH_PREFIX ?>/<?php echo $SRC_DOCROOT_RELATIVE_FILEPATH ?>/ <?php echo $ACQUIA_SITEGROUP ?>@<?php echo $ACQUIA_HOSTNAME ?>:<?php echo $DST_FILES_PREFIX ?>/<?php echo $DST_DOCROOT_RELATIVE_FILEPATH ?>
  
)

MIGRATION_END=`date -u +%s`
RUNTIME=`expr $MIGRATION_END - $MIGRATION_START`
verbose "End: `date -u`, Runtime: `prettytime ${RUNTIME}`" ""
