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

MYSQL_OPTS="--complete-insert --disable-keys --single-transaction -u <?php echo $SRC_DB_USER; ?> -h <?php echo $SRC_DB_HOST; ?>"
SRC_DB_SKIP_DATA="^(cache|cache_.*|flood|semaphore|sessions|watchdog)$"

echo `now` ">>> Generating list of tables"
TABLES=`mysql --skip-column-names -e 'show tables' -u <?php echo $SRC_DB_USER; ?> -p<?php echo $SRC_DB_PASS; ?> -h <?php echo $SRC_DB_HOST; ?> <?php echo $SRC_DB_NAME; ?>`
echo `now` ">>> dumping structure to <?php echo $SRC_EXPORT_DIR; ?>/<?php echo $SRC_DB_NAME; ?>.<?php echo $IMPORT_TIMESTAMP; ?>.sql"
mysqldump ${MYSQL_OPTS} --no-data --password=<?php echo $SRC_DB_PASS; ?> <?php echo $SRC_DB_NAME; ?> ${TABLES} | sed -e 's/ENGINE=MyISAM/ENGINE=InnoDB/g' > <?php echo $SRC_EXPORT_DIR; ?>/<?php echo $SRC_DB_NAME; ?>.<?php echo $IMPORT_TIMESTAMP; ?>.sql

# Dump Data, Excluding Certain Tables
echo `now` ">>> Generating list of tables to exclude data from."
TABLES2=`echo "$TABLES" | grep -Ev "${SRC_DB_SKIP_DATA}"`
echo `now` ">>> dumping data to <?php echo $SRC_EXPORT_DIR; ?>/<?php echo $SRC_DB_NAME; ?>.<?php echo $IMPORT_TIMESTAMP; ?>.sql... patience please."
mysqldump ${MYSQL_OPTS} --no-create-info --password=<?php echo $SRC_DB_PASS; ?> <?php echo $SRC_DB_NAME; ?> ${TABLES2} >> <?php echo $SRC_EXPORT_DIR; ?>/<?php echo $SRC_DB_NAME; ?>.<?php echo $IMPORT_TIMESTAMP; ?>.sql

echo `now` ">>> gzipping <?php echo $SRC_EXPORT_DIR; ?>/<?php echo $SRC_DB_NAME; ?>.<?php echo $IMPORT_TIMESTAMP; ?>.sql"
gzip -v <?php echo $SRC_EXPORT_DIR; ?>/<?php echo $SRC_DB_NAME; ?>.<?php echo $IMPORT_TIMESTAMP; ?>.sql
SQLDUMP=<?php echo $SRC_DB_NAME; ?>.<?php echo $IMPORT_TIMESTAMP; ?>.sql.gz

echo `now` ">> Making sure <?php echo $DST_IMPORT_DIR; ?> exists"
<?php echo $SSH_CMD; ?> mkdir -vp <?php echo $DST_IMPORT_DIR; ?>

echo `now` ">>> copying to Acquia Cloud"
scp -i /tmp/acquia_migrate/acquia.rsa <?php echo $SRC_EXPORT_DIR; ?>/${SQLDUMP} <?php echo $ACQUIA_SITEGROUP; ?>@<?php echo $ACQUIA_HOSTNAME; ?>:<?php echo $DST_IMPORT_DIR; ?>

echo `now` ">>> unzipping dumpfile on Acquia Cloud"
<?php echo $SSH_CMD; ?> gunzip -v <?php echo $DST_IMPORT_DIR; ?>/${SQLDUMP}

echo `now` ">>> importing dumpfile to Acquia Cloud... patience please."
SQL_IMPORT="mysql --database='<?php echo $DST_DB_NAME; ?>' --host='<?php echo $DST_DB_HOST; ?>' --port='3306' --user='<?php echo $DST_DB_USER; ?>' -p<?php echo $DST_DB_PASS; ?> < <?php echo $DST_IMPORT_DIR; ?>/<?php echo $SRC_DB_NAME; ?>.<?php echo $IMPORT_TIMESTAMP; ?>.sql"
<?php echo $SSH_CMD; ?> "$SQL_IMPORT"

echo `now` ">>> Running drupal database updates"
<?php echo $SSH_CMD; ?> "drush @prod --uri=<?php echo $DST_DRUSH_URI; ?> updb -y" && UPDB=$? || UPDB=$?
echo `now` ">>> Enabling cache_lifetime to 5m"
<?php echo $SSH_CMD; ?> "drush @prod --uri=<?php echo $DST_DRUSH_URI; ?> vset -y cache_lifetime 300"
echo `now` ">>> Enabling normal page caching"
<?php echo $SSH_CMD; ?> "drush @prod --uri=<?php echo $DST_DRUSH_URI; ?> vset -y cache 1"

## UNCOMMENT IF THE DESTINATION IS PRESSFLOW
#echo ">>> Enabling path_alias_cache"
#<?php echo $SSH_CMD; ?> "drush @prod --uri=<?php echo $DST_DRUSH_URI; ?> pm-enable -y path_alias_cache"
#echo ">>> Enabling external page caching"
#<?php echo $SSH_CMD; ?> "drush @prod --uri=<?php echo $DST_DRUSH_URI; ?> vset -y cache 3"
#echo ">>> Setting page_cache_max_age to 15m"
#<?php echo $SSH_CMD; ?> "drush @prod --uri=<?php echo $DST_DRUSH_URI; ?> vset -y page_cache_max_age 900"

MIGRATION_END=`date -u +%s`
RUNTIME=`expr $MIGRATION_END - $MIGRATION_START`
verbose "End: `date -u`, Runtime: `prettytime ${RUNTIME}`" ""