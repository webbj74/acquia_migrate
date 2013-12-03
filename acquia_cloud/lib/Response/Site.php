<?php

/**
 * @file
 * Acquia CloudApi Site response compatible with cpliakas/acquia-sdk-php
 *
 * NOTICE: This source code was derived from acquia-sdk-php, covered by
 * the GPLv3 software license, on 2 Dec 2013 (0bd839a179).
 *
 * @see https://github.com/cpliakas/acquia-sdk-php/blob/0bd839a179/src/Acquia/Cloud/Api/Response/Site.php
 */

class AcquiaCloudApiResponseSite extends ArrayObject
{
    /**
     * @param array|string $data
     */
    public function __construct($data)
    {
        if (is_string($data)) {
            $data = array('name' => $data);
        }
        parent::__construct($data);
        list($this['hosting_stage'], $this['site_group']) = explode(':', $data['name']);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this['name'];
    }
}
