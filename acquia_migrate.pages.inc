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
    $sites[] = "$site";
  }

  //$sdk = libraries_load('acquia-sdk-php');
  $form['#prefix'] = t('Select the server to connect to. %xx', array('%xx' => print_r($sites,1)));

  if (count($envs) > 1) {
    $form['environment'] = array(
      '#type' => 'select',
      '#title' => t('Select environment for migration'),
      '#options' => $sites,
      '#description' => t(
        'Select which environment your site should be migrated to.
                Only environments that are running trunk or branch can
                be overwritten by migration. Environments running a tag are not included.'
      )
    );
  }
  else {
    $form['environment'] = array(
      '#markup' => t('Available environment for migration: %env', array('%env' => array_pop($envs))),
    );
  }

  return $form;
}