#!/bin/bash

# abort on errors
set -o errexit ; set -o nounset

function verbose {
  comment "$1"
  VERBOSE_CMD=$2
  if [[ ! -z "${VERBOSE_CMD}" ]]; then
    echo `now` ">> ${VERBOSE_CMD}"
    if [[ "${MIGRATION_DRY_RUN}" -eq 0 ]]; then
      ${VERBOSE_CMD}
    else
      VERBOSE_RVAL=1
    fi
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

BRANCH_NAME="import-sync-<?php echo $IMPORT_TIMESTAMP ?>"
GIT_CMD="<?php echo $SSH_CMD ?> git --work-tree=<?php echo $DST_VCS_WORK_TREE ?> --git-dir=<?php echo $DST_VCS_WORK_TREE ?>/.git"

echo `now` ">> Making sure <?php echo $DST_IMPORT_DIR ?> exists"
<?php echo $SSH_CMD ?> mkdir -vp <?php echo $DST_IMPORT_DIR ?>

echo `now` ">> Removing existing git work-tree if it exists"
<?php echo $SSH_CMD ?> rm -rf <?php echo $DST_VCS_WORK_TREE ?>

echo `now` ">>> Cloning repo to Acquia Cloud work-tree"
<?php echo $SSH_CMD ?> git clone <?php echo $DST_VCS_REMOTE ?> <?php echo $DST_VCS_WORK_TREE ?>

echo `now` ">>> git branch <?php echo $DST_IMPORT_BRANCH ?>"
${GIT_CMD} branch <?php echo $DST_IMPORT_BRANCH ?>  && IMBR=$? || IMBR=$?

echo `now` ">>> git checkout <?php echo $DST_IMPORT_BRANCH ?>"
${GIT_CMD} checkout <?php echo $DST_IMPORT_BRANCH ?>

echo `now` ">>> git checkout -b ${BRANCH_NAME}"
${GIT_CMD} checkout -b ${BRANCH_NAME}

echo `now` ">>> rsync to Acquia overwriting code ${BRANCH_NAME}"
rsync -rltDvz \
  --temp-dir=<?php echo $DST_TEMP ?> \
  -e "ssh -o stricthostKeychecking=no -i /tmp/acquia_migrate/acquia.rsa" \
  --delete \
  --exclude=__MACOSX \
  --exclude=.git \
  --exclude=.svn \
  --exclude=.DS_Store \
  --exclude=sites/example.com/files \
  <?php echo $SRC_PATH_PREFIX ?>/ \
  <?php echo $ACQUIA_SITEGROUP ?>@<?php echo $ACQUIA_HOSTNAME ?>:<?php echo $DST_VCS_WORK_TREE ?>/docroot

# TODO: set this up correctly
echo `now` ">>> Adding email and user as sitegroup"
${GIT_CMD} config --global user.email "<?php echo $ACQUIA_SITEGROUP ?>"
${GIT_CMD} config --global user.name "<?php echo $ACQUIA_SITEGROUP ?>"

echo `now` ">>> Updating versioning file to ensure there is something to commit"
<?php echo $SSH_CMD ?> "echo \"SCRIPT:code_to_acquia.sh:import:${BRANCH_NAME}\" > <?php echo $DST_VCS_WORK_TREE ?>/docroot/.import"

echo `now` ">>> git add --all docroot"
${GIT_CMD} add --all docroot

echo `now` ">>> git commit -m \"SCRIPT:code_to_acquia.sh:import:${BRANCH_NAME}\""
${GIT_CMD} commit -m "SCRIPT:code_to_acquia.sh:import:${BRANCH_NAME}"

# Apply new branch to our upstream tracking branch
echo `now` ">>> git checkout <?php echo $DST_IMPORT_BRANCH ?>"
${GIT_CMD} checkout <?php echo $DST_IMPORT_BRANCH ?>

echo `now` ">>> git merge ${BRANCH_NAME}"
${GIT_CMD} merge ${BRANCH_NAME}

# Apply new branch to Acquia Prod
echo `now` ">>> git checkout <?php echo $DST_MERGE_BRANCH ?>"
${GIT_CMD} checkout <?php echo $DST_MERGE_BRANCH ?>

echo `now` ">>> git cherry-pick ${BRANCH_NAME}"
${GIT_CMD} cherry-pick ${BRANCH_NAME}

# Send our changes
echo `now` ">>> git checkout <?php echo $DST_IMPORT_BRANCH ?>"
${GIT_CMD} checkout <?php echo $DST_IMPORT_BRANCH ?>

echo `now` ">>> git push origin <?php echo $DST_IMPORT_BRANCH ?>"
${GIT_CMD} push origin <?php echo $DST_IMPORT_BRANCH ?>

echo `now` ">>> git checkout <?php echo $DST_MERGE_BRANCH ?>"
${GIT_CMD} checkout <?php echo $DST_MERGE_BRANCH ?>

echo `now` ">>> git push origin <?php echo $DST_MERGE_BRANCH ?>"
${GIT_CMD} push origin <?php echo $DST_MERGE_BRANCH ?>

MIGRATION_END=`date -u +%s`
RUNTIME=`expr $MIGRATION_END - $MIGRATION_START`
verbose "End: `date -u`, Runtime: `prettytime ${RUNTIME}`" ""
