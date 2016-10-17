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

namespace Wurfl\Handlers;

use UaNormalizer\Helper\Utils;

/**
 * JavaMidletUserAgentHandler
 *
 *
 * @category   WURFL
 *
 * @copyright  ScientiaMobile, Inc.
 * @license    GNU Affero General Public License
 */
class JavaMidletHandler extends AbstractHandler
{
    public static $constantIDs = array(
        'generic_midp_midlet',
    );

    /**
     * @param string $userAgent
     *
     * @return bool
     */
    public function canHandle($userAgent)
    {
        $s = \Stringy\create($userAgent);

        return $s->contains('UNTRUSTED/1.0');
    }

    /**
     * @param string $userAgent
     *
     * @return string
     */
    public function applyConclusiveMatch($userAgent)
    {
        return 'generic_midp_midlet';
    }

    protected $prefix = 'JAVAMIDLET';
}
