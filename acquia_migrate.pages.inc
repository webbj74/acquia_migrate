<?php

/**
 * @file
 *   Acquia Migrate configuration page.
 */

use Acquia\Cloud\Api\CloudApiClient;


/**
 * Main page function
 */
function acquia_migrate_settings_page($arg = NULL) {
  $migrate_user = variable_get('acquia_migrate_cloudapi_username', '');
  $migrate_password = variable_get('acquia_migrate_cloudapi_password', '');
  if (empty($migrate_user) || empty($migrate_password)) {
    return drupal_get_form('acquia_migrate_settings_form');
  }
  else {
    return drupal_get_form('acquia_migrate_migrate_form', $migrate_user, $migrate_password);
  }
}

/**
 * Settings form builder function.
 */
function acquia_migrate_settings_form($form, &$form_state) {

  $form = array();

  $form['#prefix'] = t(
    'Enter your <a href="!net">CloudAPI username and password</a>.',
    array(
      '!net' => url('https://docs.acquia.com/cloud/api'),
    )
  );
  $form['acquia_migrate_cloudapi_username'] = array(
    '#type' => 'textfield',
    '#title' => t('CloudAPI Username'),
    '#default_value' => variable_get('acquia_migrate_cloudapi_username', ''),
    '#required' => TRUE,
  );
  $form['acquia_migrate_cloudapi_password'] = array(
    '#type' => 'textfield',
    '#title' => t('CloudAPI Password'),
    '#default_value' => variable_get('acquia_migrate_cloudapi_password', ''),
    '#required' => TRUE,
  );
  $form['actions'] = array('#type' => 'actions');
  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Connect'),
  );

  return $form;
}

function acquia_migrate_settings_form_submit($form, &$form_state) {
  $migrate_user = $form_state['values']['acquia_migrate_cloudapi_username'];
  $migrate_password = $form_state['values']['acquia_migrate_cloudapi_password'];
  
  variable_set('acquia_migrate_cloudapi_username', $migrate_user);
  variable_set('acquia_migrate_cloudapi_password', $migrate_password);

  // Redirect to the path without the suffix.
  $form_state['redirect'] = array('admin/config/system/acquia-migrate');

}


/**
 * Settings form builder function.
 */
function acquia_migrate_migrate_form($form, &$form_state, $migrate_user, $migrate_password) {
  $form = array();

  $envs = array('dev', 'stage', 'prod',);
  $cloudapi = acquia_migrate_cloudapi_connect();

  $sites = array();
  foreach($cloudapi->sites() as $site) {
    $site = explode(':', $site);
    $sites["$site[1]"] = "$site[1]";
  }

  $form['#prefix'] = t('Select the server to connect to.');

  if (count($envs) > 1) {
    $selected = isset($form_state['values']['site']) ? $form_state['values']['site'] : key($sites);
    $form['site'] = array(
      '#type' => 'select',
      '#title' => t('Select site for migration'),
      '#options' => $sites,
      '#description' => t(
        'Select which site your site should be migrated to.
                Only sites that are running trunk or branch can
                be overwritten by migration. sites running a tag are not included.'
      ),
      '#ajax' => array(
        'callback' => 'acquia_migrate_databases_callback',
        'wrapper' => 'databases',
        'effect' => 'slide',
        ),
    );

    $form['database'] = array(
      '#type' => 'select',
      '#title' => t("Select database for migration"),
      '#prefix' => '<div id="databases">',
      '#suffix' => '</div>',
      '#options' => acquia_migrate_databases($selected),
      '#default_value' => isset($form_state['values']['database']) ? $form_state['values']['database'] : '',
    );

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['code'] = array(
      '#prefix' => '<ol><li>',
      '#type' => 'submit',
      '#value' => t('Migrate code'),
      '#suffix' => '<small>' . 
                    t("This only needs to be done for one site in your multi-site.")
                    . '</small></li>'
    );
    $form['actions']['files'] = array(
      '#prefix' => '<li>',  
      '#type' => 'submit',
      '#value' => t('Migrate files'),
      '#suffix' => '</li>'
    );
    $form['actions']['database'] = array(
      '#prefix' => '<li>',  
      '#type' => 'submit',
      '#value' => t('Migrate database'),
      '#suffix' => '</li></ol>'
    );
  }
  else {
    $form['site'] = array(
      '#markup' => t('Available site for migration: %env', array('%env' => array_pop($envs))),
    );
  }

  return $form;
}

function acquia_migrate_migrate_form_submit($form, &$form_state) {
  $cloudapi = acquia_migrate_cloudapi_connect();
  $op = $form_state['values']['op'];
  $site = $form_state['values']['site'];
  $database = $form_state['values']['database'];
  $docroot = preg_quote(DRUPAL_ROOT . '/');
  $environment = $cloudapi->environment($site, 'prod');
  $ssh_host = $environment['ssh_host'];
  $ssh_cmd = "ssh -o stricthostKeychecking=no -i /tmp/acquia_migrate/acquia.rsa $site@$ssh_host";
  
  if (!acquia_migrate_sshkey_exists($site)) {
    $publicKey = file_get_contents('/tmp/acquia_migrate/acquia.rsa.pub');
    $cloudapi->addSshKey($site, $publicKey, 'acquiamigration');
  }

  switch ($op) {
    case t('Migrate code'):
      $site_record = $cloudapi->site($site);
      $output = theme('code', array(
        'SSH_CMD' => $ssh_cmd,
        'DST_VCS_WORK_TREE' => "/mnt/tmp/$site/import/" . $site,
        'DST_IMPORT_DIR' => "/mnt/tmp/$site/import",
        'DST_VCS_REMOTE' => $site_record['vcs_url'],
        'DST_IMPORT_BRANCH' => 'import',
        'DST_MERGE_BRANCH' => 'master',
        'IMPORT_TIMESTAMP' => date('Ymd-Hi'),
        'DST_TEMP' => "/mnt/tmp/$site",
        'SRC_PATH_PREFIX' => $docroot,
        'ACQUIA_SITEGROUP' => $site,
        'ACQUIA_HOSTNAME' => $ssh_host,
        ));
      file_put_contents(TMP_PATH . '/code_to_acquia.sh', $output);
      chmod(TMP_PATH . '/code_to_acquia.sh', 0777);
      exec(TMP_PATH . '/code_to_acquia.sh');
      drupal_set_message(t('Migrating code for envrionment %env from %docroot', array('%env' => $site, '%docroot' => $docroot)));
      break;
    case t('Migrate files'):
      $output = theme('files', array(
        'SSH_CMD' => $ssh_cmd,
        'DST_IMPORT_DIR' => "/mnt/tmp/$site/import",
        'DST_FILES_PREFIX' => "/mnt/gfs/$site.prod",
        'DST_DOCROOT_RELATIVE_FILEPATH' => variable_get('file_public_path','sites/default/files'),
        'SRC_DOCROOT_RELATIVE_FILEPATH' => variable_get('file_public_path','sites/default/files'),
        'SRC_PATH_PREFIX' => $docroot,
        'ACQUIA_SITEGROUP' => $site,
        'ACQUIA_HOSTNAME' => $ssh_host,
        ));
      file_put_contents(TMP_PATH . '/files_to_acquia.sh', $output);
      chmod(TMP_PATH . '/files_to_acquia.sh', 0777);
      exec(TMP_PATH . '/files_to_acquia.sh');
      drupal_set_message(t('Migrating files for envrionment %env', array('%env' => $site)));
      break;
    case t('Migrate database'):
      global $databases;
      $SRC_DB = $databases['default']['default'];
      $DST_DB = $cloudapi->environmentDatabase($site, 'prod', $database);
      $output = theme('database', array(
        'SSH_CMD' => $ssh_cmd,
        'SRC_DB_USER' => $SRC_DB['username'],
        'SRC_DB_PASS' => $SRC_DB['password'],
        'SRC_DB_HOST' => $SRC_DB['host'],
        'SRC_DB_NAME' => $SRC_DB['database'],
        'SRC_EXPORT_DIR' => TMP_PATH,
        'IMPORT_TIMESTAMP' => date('Ymd-Hi'),
        'DST_IMPORT_DIR' => "/mnt/tmp/$site",
        'ACQUIA_SITEGROUP' => $site,
        'ACQUIA_HOSTNAME' => $ssh_host,
        'DST_DB_NAME' => $DST_DB['instance_name'],
        'DST_DB_HOST' => $DST_DB['host'],
        'DST_DB_USER' => $DST_DB['username'],
        'DST_DB_PASS' => $DST_DB['password'],
        'DST_DRUSH_URI' => 'default',
        ));
      file_put_contents(TMP_PATH . '/data_to_acquia.sh', $output);
      chmod(TMP_PATH . '/data_to_acquia.sh', 0777);
      exec(TMP_PATH . '/data_to_acquia.sh');

      drupal_set_message(t('Migrating database'));
      break;
  }

  if (acquia_migrate_sshkey_exists($site)) {
    // This doesn't seem to work and I'm not in the mood to fix.
    //$cloudapi->deleteSshKey($site, 'acquiamigration');
  }
  return;
}

function acquia_migrate_databases_callback($form, $form_state) {
  return $form['database'];
}

