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

  variable_set('acquia_migrate_cloudapi_username', $form_state['values']['acquia_migrate_cloudapi_username']);
  variable_set('acquia_migrate_cloudapi_password', $form_state['values']['acquia_migrate_cloudapi_password']);

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
    $selected = isset($form_state['values']['environment']) ? $form_state['values']['environment'] : key($sites);
    $form['environment'] = array(
      '#type' => 'select',
      '#title' => t('Select environment for migration'),
      '#options' => $sites,
      '#description' => t(
        'Select which environment your site should be migrated to.
                Only environments that are running trunk or branch can
                be overwritten by migration. Environments running a tag are not included.'
      ),
      '#ajax' => array(
        'callback' => 'acquia_migrate_databases_callback',
        'wrapper' => 'databases',
        'effect' => 'slide',
        ),
    );

    $form['databases'] = array(
      '#type' => 'select',
      '#title' => t("Select database for migration"),
      '#prefix' => '<div id="databases">',
      '#suffix' => '</div>',
      '#options' => _acquia_migrate_databases($selected),
      '#default_value' => isset($form_state['values']['databases']) ? $form_state['values']['databases'] : '',
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
    $form['environment'] = array(
      '#markup' => t('Available environment for migration: %env', array('%env' => array_pop($envs))),
    );
  }

  return $form;
}

function acquia_migrate_migrate_form_submit($form, &$form_state) {
  $op = $form_state['values']['op'];
  $environment = $form_state['values']['environment'];
  $docroot = preg_quote(DRUPAL_ROOT . '/');
  switch ($op) {
    case t('Migrate code'):
      drupal_set_message(t('Migrating code for envrionment %env from %docroot', array('%env' => $environment, '%docroot' => $docroot)));
      break;
    case t('Migrate files'):
      drupal_set_message(t('Migrating files for envrionment %env', array('%env' => $environment)));
      break;
  }
  return;
}

function acquia_migrate_databases_callback($form, $form_state) {
  return $form['databases'];
}

function _acquia_migrate_databases($environment) {
  composer_manager_register_autoloader();
  $migrate_user = variable_get('acquia_migrate_cloudapi_username', '');
  $migrate_password = variable_get('acquia_migrate_cloudapi_password', '');

  $cloudapi = CloudApiClient::factory(array(
      'username' => $migrate_user,
      'password' => $migrate_password,
    ));
  $sitedatabases = $cloudapi->siteDatabases($environment);
  $dbs = array();
  foreach($sitedatabases as $key => $value) {
    $dbs[$key] = $key; 
  }
  return $dbs;
}