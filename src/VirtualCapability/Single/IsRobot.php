<?php
/**
 * Copyright (c) 2015 ScientiaMobile, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * Refer to the LICENSE file distributed with this package.
 *
 *
 * @category   WURFL
 *
 * @copyright  ScientiaMobile, Inc.
 * @license    GNU Affero General Public License
 */

namespace Wurfl\VirtualCapability\Single;

use Wurfl\Handlers\Utils;
use Wurfl\VirtualCapability\VirtualCapability;

/**
 * Virtual capability helper
 */
class IsRobot extends VirtualCapability
{
    /**
     * @var array
     */
    protected $requiredCapabilities = array();

    /**
     * @return bool
     */
    protected function compute()
    {
        $ua = $this->request->getUserAgent();

        // Control cap, 'controlcap_is_robot' is checked before this function is called
        if ($this->request->originalHeaderExists('HTTP_ACCEPT_ENCODING') && Utils::checkIfContains(
            $ua,
            'Trident/'
        ) && !Utils::checkIfContains($this->request->getOriginalHeader('HTTP_ACCEPT_ENCODING'), 'deflate')
        ) {
            return true;
        }

        // Check against standard bot list
        return Utils::isRobot($this->request->getOriginalHeader('HTTP_USER_AGENT'));
    }
}
