<?php

/**
 * @file
 * Acquia CloudApi Sites response compatible with cpliakas/acquia-sdk-php
 *
 * NOTICE: This source code was derived from acquia-sdk-php, covered by
 * the GPLv3 software license, on 2 Dec 2013 (0bd839a179).
 *
 * @see https://github.com/cpliakas/acquia-sdk-php/blob/0bd839a179/src/Acquia/Cloud/Api/Response/Sites.php
 */

class AcquiaCloudApiResponseSites extends ArrayObject
{
    /**
     * @param array $sites
     */
    public function __construct($sites)
    {
        foreach ($sites as $site) {
            $this[$site] = new AcquiaCloudApiResponseSite($site);
        }
    }
}
