<?php

/**
 * @file
 * Acquia CloudApi Databases response compatible with cpliakas/acquia-sdk-php
 *
 * NOTICE: This source code was derived from acquia-sdk-php, covered by
 * the GPLv3 software license, on 2 Dec 2013 (0bd839a179).
 *
 * @see https://github.com/cpliakas/acquia-sdk-php/blob/0bd839a179/src/Acquia/Cloud/Api/Response/Databases.php
 */

class AcquiaCloudApiResponseDatabases extends ArrayObject
{
    /**
     * @param array $dbs
     */
    public function __construct($dbs)
    {
        foreach ($dbs as $db) {
            $this[$db['name']] = new AcquiaCloudApiResponseDatabase($dbs);
        }
    }
}
