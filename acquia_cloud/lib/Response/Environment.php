<?php

/**
 * @file
 * Acquia CloudApi Environment response compatible with cpliakas/acquia-sdk-php
 *
 * NOTICE: This source code was derived from acquia-sdk-php, covered by
 * the GPLv3 software license, on 2 Dec 2013 (0bd839a179).
 *
 * @see https://github.com/cpliakas/acquia-sdk-php/blob/0bd839a179/src/Acquia/Cloud/Api/Response/Environment.php
 */

class AcquiaCloudApiResponseEnvironment extends ArrayObject
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
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this['name'];
    }
}
