<?php
declare(ENCODING = 'utf-8');
namespace Wurfl\Storage;

/**
 * Copyright(c) 2011 ScientiaMobile, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or(at your option) any later version.
 *
 * Refer to the COPYING file distributed with this package.
 *
 * @category   WURFL
 * @package    WURFL_Storage
 * @copyright  ScientiaMobile, Inc.
 * @license    GNU Affero General Public License
 * @author     Fantayeneh Asres Gizaw
 * @version    $id$
 */

/**
 * Object for storing data
 * @package WURFL_Storage
 */
class StorageObject
{
    private $_value;
    private $_expiringOn;

    public function __construct($_value, $_expire)
    {
        $this->_value = $_value;
        $this->_expiringOn =($_expire === 0) ? $_expire : time() + $_expire;
    }

    public function value()
    {
        return $this->_value;
    }

    public function isExpired()
    {
        if ($this->_expiringOn === 0) {
            return false;
        }
        return $this->_expiringOn < time();
    }

    public function expiringOn()
    {
        return $this->_expiringOn;
    }

}