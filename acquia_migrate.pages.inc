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
  composer_manager_register_autoloader();

  $cloudapi = CloudApiClient::factory(array(
      'username' => $migrate_user,
      'password' => $migrate_password,
    ));

  $sites = array();
  foreach($cloudapi->sites() as $site) {
    $sites["$site"] = "$site";
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
      '#type' => 'submit',
      '#value' => t('Migrate code'),
    );
    $form['actions']['files'] = array(
      '#type' => 'submit',
      '#value' => t('Migrate files'),
    );
    $form['actions']['database'] = array(
      '#type' => 'submit',
      '#value' => t('Migrate database'),
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
  
  if (!acquia_migrate_sshkey_exists($site)) {
    $publicKey = file_get_contents('/tmp/acquia_migrate/acquia.rsa.pub');
    $cloudapi->addSshKey($site, $publicKey, 'acquiamigration');
  }

  switch ($op) {
    case t('Migrate code'):
      drupal_set_message(t('Migrating code for envrionment %env from %docroot', array('%env' => $site, '%docroot' => $docroot)));
      break;
    case t('Migrate files'):
      drupal_set_message(t('Migrating files for envrionment %env', array('%env' => $site)));
      break;
    case t('Migrate database'):
      $file = _acquia_migrate_backup_db_to_file_mysql();
      $database_creds = $cloudapi->environmentDatabase($site, 'prod', $database);
      drupal_set_message(t('Migrating database %file', array('%file' => $file)));
      break;
  }
  return;
}

function acquia_migrate_databases_callback($form, $form_state) {
  return $form['database'];
}

