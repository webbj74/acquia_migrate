<?php

/**
 * @file An Acquia CloudApi Client compatible with cpliakas/acquia-sdk-php
 *
 * NOTICE: This source code was derived from acquia-sdk-php, covered by
 * the GPLv3 software license, on 2 Dec 2013 (0bd839a179).
 *
 * @see https://github.com/cpliakas/acquia-sdk-php/blob/0bd839a179/LICENSE.txt
 */

/**
 * Acquia CloudApiClient object
 * @see https://github.com/cpliakas/acquia-sdk-php/blob/0bd839a179/src/Acquia/Cloud/Api/CloudApiClient.php
 */
class AcquiaCloudApiClient
{
    const BASE_URL = 'https://cloudapi.acquia.com';
    const BASE_PATH = '/v1';

    protected $base_url;
    protected $config;
    protected $headers;


    public function __construct($base_url, $config)
    {
        $this->base_url = $base_url;
        $this->config = $config;
    }

    /**
     * Factory method to create a new CloudApiClient connection.
     *
     * @param array $config Login credentials
     *
     * @return AcquiaCloudApiClient
     *
     * @throws RuntimeException
     */
    public static function factory($config = array())
    {
        $required = array(
            'base_url',
            'username',
            'password',
        );

        $defaults = array(
            'base_url' => self::BASE_URL,
            'base_path' => self::BASE_PATH,
        );

        $config = array_merge($defaults, $config);
        foreach($required as $required_key) {
          if (!isset($required_key) || empty($required_key)) {
              throw new RuntimeException("Missing required configuration parameter '{$required_key}'.");
          }
        }
        $client = new static($config['base_url'], $config);
        $curl_version = curl_version();
        $client->setDefaultHeaders(array(
            'Content-Type' => 'application/json; charset=utf-8',
            'User-Agent' => 'acquia_migrate/0.1 (jonathan.webb@acquia.com)'
            . ' curl/' . $curl_version['version']
            . ' PHP/' . PHP_VERSION
        ));

        return $client;
    }

    public function setDefaultHeaders($default_headers) {
        $this->headers = $default_headers;
    }

    /**
     * {@inheritdoc}
     */
    public function getBuilderParams()
    {
        return array(
            'base_url' => $this->getConfig('base_url'),
            'username' => $this->getConfig('username'),
            'password' => $this->getConfig('password'),
        );
    }

    /**
     * Helper function that makes the curl calls (GET).
     * @throws RuntimeException
     */
    protected function get($params)
    {
        $vars = $this->config;

        if (is_array($params[1])) {
            $vars = array_merge($vars, $params[1]);
        }

        $url = "{$this->base_url}{$params[0]}";
        while(preg_match('/([{]\+?(\w+)[}])/', $url, $matches)) {
            if (isset($vars[$matches[2]])) {
                $url = str_replace($matches[1], $vars[$matches[2]], $url);
            }
            else {
                throw new RuntimeException("Missing variable '{$matches[2]}' in API 'get' request.");
            }
        }
        $username = $this->config['username'];
        $password = $this->config['password'];
        $return_value = FALSE;
        if ($ch = curl_init($url)) {
            $headers = array();
            foreach($this->headers as $header => $value) {
                $headers[] = "{$header}: {$value}";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 150);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

            if (!$server_output = curl_exec($ch)) {
                throw new RuntimeException(curl_error($ch) . " [Requesting the URL '{$url}' with user '{$username}'']");
            }
            curl_close($ch);

            $return_value = drupal_json_decode($server_output);
        }
        else {
            throw new RuntimeException("Curl init failed in API 'get' request.");
        }
        return $return_value;
    }

    /**
     * Helper function that makes the curl calls (POST).
     * @throws RuntimeException
     */
    protected function post($params, $unused, $body)
    {
        $vars = $this->config;

        if (is_array($params[1])) {
            $vars = array_merge($vars, $params[1]);
        }

        $url = "{$this->base_url}{$params[0]}";
        while(preg_match('/([{]\+?(\w+)[}])/', $url, $matches)) {
            if (isset($vars[$matches[2]])) {
                $url = str_replace($matches[1], $vars[$matches[2]], $url);
            }
            else {
                throw new RuntimeException("Missing variable '{$matches[2]}' in API 'post' request.");
            }
        }
        $username = $this->config['username'];
        $password = $this->config['password'];
        $return_value = FALSE;
        if ($ch = curl_init($url)) {
            $headers = array();
            foreach($this->headers as $header => $value) {
                $headers[] = "{$header}: {$value}";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 150);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

            curl_setopt($ch, CURLOPT_POST, 0);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

            if (!$server_output = curl_exec($ch)) {
                throw new RuntimeException(curl_error($ch) . " [Posting to the URL '{$url}' with user '{$username}'']");
            }
            curl_close($ch);

            $return_value = drupal_json_decode($server_output);
        }
        else {
            throw new RuntimeException("Curl init failed in API 'post' request.");
        }
        return $return_value;
    }

    /**
     * Helper method to send a GET request and return parsed JSON.
     *
     * @param string $path
     * @param array $variables
     *   Variables used to expand the URI expressions.
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public function sendGet($path, $variables = array())
    {
        return $this->get(array($path, $variables));
    }

    /**
     * Helper method to send a GET request and save to a file.
     *
     * @param string $path
     * @param array $variables
     *   Variables used to expand the URI expressions.
     * @param string $tofile
     *   Path to save file
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public function saveGet($path, array $variables, $tofile)
    {
        return $this->get(array($path, $variables))->setResponseBody($tofile)->send();
    }

    /**
     * Helper method to send a POST request and return parsed JSON.
     *
     * The variables passed in the second parameter are used to expand the URI
     * expressions, which are usually the resource identifiers being requested.
     *
     * @param string $path
     * @param array $variables
     *   Variables used to expand the URI expressions.
     * @param mixed $body
     *   Defaults to null. If a non-string is passed then the data is converted
     *   to JSON.
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public function sendPost($path, $variables = array(), $body = null)
    {
        if (!is_string($body)) {
            $body = drupal_json_encode($body);
        }
        return $this->post(array($path, $variables), null, $body);
    }

    /**
     * Helper method to send a DELETE request and return parsed JSON.
     *
     * @param string $path
     * @param array $variables
     *   Variables used to expand the URI expressions.
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public function sendDelete($path, $variables = array())
    {
        return $this->delete(array($path, $variables))->send()->json();
    }

    /**
     * @return AcquiaCloudApiResponseSites
     *
     * @throws RuntimeException
     */
    public function sites()
    {
        $data = $this->sendGet('{+base_path}/sites.json');
        return new AcquiaCloudApiResponseSites($data);
    }

    /**
     * @param string $site
     *
     * @return AcquiaCloudApiResponseSite
     *
     * @throws RuntimeException
     */
    public function site($site)
    {
        $variables = array('site' => $site);
        $data = $this->sendGet('{+base_path}/sites/{site}.json', $variables);
        return new AcquiaCloudApiResponseSite($data);
    }

    /**
     * @param string $site
     *
     * @return AcquiaCloudApiResponseEnvironments
     *
     * @throws RuntimeException
     */
    public function environments($site)
    {
        $variables = array('site' => $site);
        $data = $this->sendGet('{+base_path}/sites/{site}/envs.json', $variables);
        return new AcquiaCloudApiResponseEnvironments($data);
    }

    /**
     * @param string $site
     * @param string $env
     *
     * @return AcquiaCloudApiResponseEnvironment
     *
     * @throws RuntimeException
     */
    public function environment($site, $env)
    {
        $variables = array(
            'site' => $site,
            'env' => $env,
        );
        $data = $this->sendGet('{+base_path}/sites/{site}/envs/{env}.json', $variables);
        return new AcquiaCloudApiResponseEnvironment($data);
    }

    /**
     * @param string $site
     * @param string $env
     * @param string $type
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public function installDistro($site, $env, $type)
    {
        $variables = array(
            'site' => $site,
            'env' => $env,
            'type' => $type,
        );
        return $this->sendPost('{+base_path}/sites/{site}/envs/{env}/install/{type}.json', $variables);
    }

    /**
     * @param string $site
     * @param string $env
     *
     * @return AcquiaCloudApiResponseServers
     *
     * @throws RuntimeException
     */
    public function servers($site, $env)
    {
        $variables = array(
            'site' => $site,
            'env' => $env,
        );
        $data = $this->sendGet('{+base_path}/sites/{site}/envs/{env}/servers.json', $variables);
        return new AcquiaCloudApiResponseServers($data);
    }

    /**
     * @param string $site
     * @param string $env
     * @param string $server
     *
     * @return AcquiaCloudApiResponseServer
     *
     * @throws RuntimeException
     */
    public function server($site, $env, $server)
    {
        $variables = array(
            'site' => $site,
            'env' => $env,
            'server' => $server,
        );
        $data = $this->sendGet('{+base_path}/sites/{site}/envs/{env}/servers/{server}.json', $variables);
        return new AcquiaCloudApiResponseServer($data);
    }

    /**
     * @param string $site
     * @param string $env
     * @param string $server
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public function maxPhpProcs($site, $env, $server)
    {
        $variables = array(
            'site' => $site,
            'env' => $env,
            'server' => $server,
        );
        return $this->sendGet('{+base_path}/sites/{site}/envs/{env}/servers/{server}/php-procs.json', $variables);
    }

    /**
     * @param string $site
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public function sshKeys($site)
    {
        $variables = array('site' => $site);
        return $this->sendGet('{+base_path}/sites/{site}/sshkeys.json', $variables);
    }

    /**
     * @param string $site
     * @param int $id
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public function sshKey($site, $id)
    {
        $variables = array(
            'site' => $site,
            'id' => $id,
        );
        return $this->sendGet('{+base_path}/sites/{site}/sshkeys/{id}.json', $variables);
    }

    /**
     * @param type $site
     * @param type $publicKey
     * @param type $nickname
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public function addSshKey($site, $publicKey, $nickname)
    {
        $path = '{+base_path}/sites/{site}/sshkeys.json?nickname={nickname}';
        $variables = array(
            'site' => $site,
            'nickname' => $nickname,
        );
        $body = array('ssh_pub_key' => $publicKey);
        return $this->sendPost($path, $variables, $body);
    }

    /**
     * @param string $site
     * @param int $id
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public function deleteSshKey($site, $id)
    {
        $variables = array(
            'site' => $site,
            'id' => $id,
        );
        return $this->sendDelete('{+base_path}/sites/{site}/sshkeys/{id}.json', $variables);
    }

    /**
     * @param string $site
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public function svnUsers($site)
    {
        $variables = array('site' => $site);
        return $this->sendGet('{+base_path}/sites/{site}/svnusers.json', $variables);
    }

    /**
     * @param string $site
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public function svnUser($site, $id)
    {
        $variables = array(
            'site' => $site,
            'id' => $id,
        );
        return $this->sendGet('{+base_path}/sites/{site}/svnusers/{id}.json', $variables);
    }

    /**
     * @param type $site
     * @param type $publicKey
     * @param type $nickname
     *
     * @return array
     *
     * @throws RuntimeException
     *
     * @todo Testing returned a 400 response.
     */
    public function addSvnUser($site, $username, $password)
    {
        $path = '{+base_path}/sites/{site}/svnusers/{username}.json';
        $variables = array(
            'site' => $site,
            'username' => $username,
        );
        $body = array('password' => $password);
        return $this->sendPost($path, $variables, $body);
    }

    /**
     * @param string $site
     *
     * @return array
     *
     * @throws RuntimeException
     *
     * @todo Testing returned a 400 response.
     */
    public function deleteSvnUser($site, $id)
    {
        $variables = array(
            'site' => $site,
            'id' => $id,
        );
        return $this->sendDelete('{+base_path}/sites/{site}/svnusers/{id}.json', $variables);
    }

    /**
     * @param string $site
     *
     * @return AcquiaCloudApiResponseDatabases
     *
     * @throws RuntimeException
     */
    public function siteDatabases($site)
    {
        $variables = array('site' => $site);
        $data = $this->sendGet('{+base_path}/sites/{site}/dbs.json', $variables);
        return new AcquiaCloudApiResponseDatabases($data);
    }

    /**
     * @param string $site
     * @param string $db
     *
     * @return AcquiaCloudApiResponseDatabase
     *
     * @throws RuntimeException
     */
    public function siteDatabase($site, $db)
    {
        $variables = array(
            'site' => $site,
            'db' => $db,
        );
        $data = $this->sendGet('{+base_path}/sites/{site}/dbs/{db}.json', $variables);
        return new AcquiaCloudApiResponseDatabase($data);
    }

    /**
     * @param string $site
     * @param string $env
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public function environmentDatabases($site, $env)
    {
        $variables = array(
            'site' => $site,
            'env' => $env,
        );
        return $this->sendGet('{+base_path}/sites/{site}/envs/{env}/dbs.json', $variables);
    }

    /**
     * @param string $site
     * @param string $env
     * @param string $db
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public function environmentDatabase($site, $env, $db)
    {
        $variables = array(
            'site' => $site,
            'env' => $env,
            'db' => $db,
        );
        return $this->sendGet('{+base_path}/sites/{site}/envs/{env}/dbs/{db}.json', $variables);
    }

    /**
     * @param string $site
     * @param string $env
     * @param string $db
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public function databaseBackups($site, $env, $db)
    {
        $variables = array(
            'site' => $site,
            'env' => $env,
            'db' => $db,
        );
        return $this->sendGet('{+base_path}/sites/{site}/envs/{env}/dbs/{db}/backups.json', $variables);
    }

    /**
     * @param string $site
     * @param string $env
     * @param string $db
     * @param string $id
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public function databaseBackup($site, $env, $db, $id)
    {
        $variables = array(
            'site' => $site,
            'env' => $env,
            'db' => $db,
            'id' => $id,
        );
        return $this->sendGet('{+base_path}/sites/{site}/envs/{env}/dbs/{db}/backups/{id}.json', $variables);
    }

    /**
     * @param string $site
     * @param string $env
     * @param string $db
     * @param string $id
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public function downloadDatabaseBackup($site, $env, $db, $id, $outfile)
    {
        $variables = array(
            'site' => $site,
            'env' => $env,
            'db' => $db,
            'id' => $id,
        );
        return $this->saveGet('{+base_path}/sites/{site}/envs/{env}/dbs/{db}/backups/{id}/download.json', $variables, $outfile);
    }

    /**
     * @param string $site
     * @param string $env
     * @param string $db
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public function createDatabaseBackup($site, $env, $db)
    {
        $variables = array(
            'site' => $site,
            'env' => $env,
            'db' => $db,
        );
        return $this->sendPost('{+base_path}/sites/{site}/envs/{env}/dbs/{db}/backups.json', $variables);
    }

    /**
     * @param string $site
     * @param string $task
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public function taskInfo($site, $task)
    {
        $variables = array(
            'site' => $site,
            'task' => $task,
        );
        return $this->sendGet('{+base_path}/sites/{site}/tasks/{task}.json', $variables);
    }
}
