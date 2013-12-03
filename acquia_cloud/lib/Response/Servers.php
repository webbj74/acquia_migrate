<?php

/**
 * @file
 * Acquia CloudApi Servers response compatible with cpliakas/acquia-sdk-php
 *
 * NOTICE: This source code was derived from acquia-sdk-php, covered by
 * the GPLv3 software license, on 2 Dec 2013 (0bd839a179).
 *
 * @see https://github.com/cpliakas/acquia-sdk-php/blob/0bd839a179/src/Acquia/Cloud/Api/Response/Servers.php
 */

class AcquiaCloudApiResponseServers extends ArrayObject
{
    /**
     * @param array $servers
     */
    public function __construct($servers)
    {
        foreach ($servers as $server) {
            $this[$server['name']] = new AcquiaCloudApiResponseServer($server);
        }
    }
}
