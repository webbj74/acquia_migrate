<?php

/**
 * @file
 * Acquia CloudApi Environments response compatible with cpliakas/acquia-sdk-php
 *
 * NOTICE: This source code was derived from acquia-sdk-php, covered by
 * the GPLv3 software license, on 2 Dec 2013 (0bd839a179).
 *
 * @see https://github.com/cpliakas/acquia-sdk-php/blob/0bd839a179/src/Acquia/Cloud/Api/Response/Environments.php
 */

class AcquiaCloudApiResponseEnvironments extends ArrayObject
{
    /**
     * @param array $envs
     */
    public function __construct($envs)
    {
        foreach ($envs as $env) {
            $this[$env['name']] = new AcquiaCloudApiResponseEnvironment($env);
        }
    }
}
