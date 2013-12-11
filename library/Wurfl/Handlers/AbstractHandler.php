<?php
namespace Wurfl\Handlers;

/**
 * Copyright (c) 2012 ScientiaMobile, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * Refer to the COPYING.txt file distributed with this package.
 *
 * @category   WURFL
 * @package    WURFL_Handlers
 * @copyright  ScientiaMobile, Inc.
 * @license    GNU Affero General Public License
 * @version    $id$
 */

/**
 * \Wurfl\Handlers\AbstractHandler is the base class that combines the classification of
 * the user agents and the matching process.
 *
 * @category   WURFL
 * @package    WURFL_Handlers
 * @copyright  ScientiaMobile, Inc.
 * @license    GNU Affero General Public License
 * @version    $id$
 */
abstract class AbstractHandler implements \Wurfl\Handlers\FilterInterface, \Wurfl\Handlers\MatcherInterface {

    /**
     * The next User Agent Handler
     * @var \Wurfl\Handlers\AbstractHandler
     */
    protected $nextHandler;

    /**
     * @var \Wurfl\Request\Normalizer\UserAgentNormalizer
     */
    protected $userAgentNormalizer;

    /**
     * @var string Prefix for this User Agent Handler
     */
    protected $prefix;

    /**
     * @var array Array of user agents with device IDs
     */
    protected $userAgentsWithDeviceID;

    /**
     * @var \Wurfl\Storage\Storage
     */
    protected $persistenceProvider;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $undetectedDeviceLogger;

    /**
     * @var array Array of WURFL IDs that are hard-coded in this matcher
     */
    public static $constantIDs = array();

    /**
     * @param \Wurfl\Context $wurflContext
     * @param \Wurfl\Request\Normalizer\NormalizerInterface $userAgentNormalizer
     */
    public function __construct($wurflContext, $userAgentNormalizer = null) {

        if (is_null($userAgentNormalizer)) {
            $this->userAgentNormalizer = new \Wurfl\Request\Normalizer\NullNormalizer();
        } else {
            $this->userAgentNormalizer = $userAgentNormalizer;
        }
        $this->setupContext($wurflContext);
    }

    /**
     * Sets the next Handler
     *
     * @param \Wurfl\Handlers\AbstractHandler $handler
     */
    public function setNextHandler(AbstractHandler $handler) {
        $this->nextHandler = $handler;
    }

    /**
     * Alias for getPrefix()
     * @return string Prefix
     * @see getPrefix()
     */
    public function getName() {
        return $this->getPrefix();
    }

    public function setupContext(\Wurfl\Context $wurflContext) {
        $this->logger = $wurflContext->logger;
        //$this->undtectedDeviceLogger = $wurflContext->undetectedDeviceLogger;
        $this->persistenceProvider = $wurflContext->persistenceProvider;
    }

    /**
     * Returns true if this handler can handle the given $userAgent
     * @param string $userAgent
     * @return bool
     */
    abstract function canHandle($userAgent);

    //********************************************************
    //
    //     Classification of the User Agents
    //
    //********************************************************
    /**
     * Classifies the given $userAgent and specified $deviceID
     * @param string $userAgent
     * @param string $deviceID
     * @return null
     */
    public function filter($userAgent, $deviceID) {
        if ($this->canHandle($userAgent)) {
            $this->updateUserAgentsWithDeviceIDMap($userAgent, $deviceID);
            return null;
        }
        if (isset($this->nextHandler)) {
            return $this->nextHandler->filter($userAgent, $deviceID);
        }
        return null;
    }

    /**
     * Updates the map containing the classified user agents.  These are stored in the associative
     * array userAgentsWithDeviceID like user_agent => deviceID.
     * Before adding the user agent to the map it normalizes by using the normalizeUserAgent
     * function.
     *
     * @see normalizeUserAgent()
     * @see userAgentsWithDeviceID
     * @param string $userAgent
     * @param string $deviceID
     */
    final function updateUserAgentsWithDeviceIDMap($userAgent, $deviceID) {
        $this->userAgentsWithDeviceID[$this->normalizeUserAgent($userAgent)] = $deviceID;
    }

    /**
     * Normalizes the given $userAgent using this handler's User Agent Normalizer.
     * If you need to normalize the user agent you need to override the function in
     * the specific user agent handler.
     *
     * @see $userAgentNormalizer, \Wurfl\Request\Normalizer\UserAgentNormalizer
     * @param string $userAgent
     * @return string Normalized user agent
     */
    public function normalizeUserAgent($userAgent) {
        return $this->userAgentNormalizer->normalize($userAgent);
    }

    //********************************************************
    //    Persisting The classified user agents
    //
    //********************************************************
    /**
     * Saves the classified user agents in the persistence provider
     */
    public function persistData() {
        // we sort the array first, useful for doing ris match
        if (!empty($this->userAgentsWithDeviceID)) {
            ksort($this->userAgentsWithDeviceID);
            $this->persistenceProvider->save($this->getPrefix(), $this->userAgentsWithDeviceID);
        }
    }

    /**
     * Returns a list of User Agents with their Device IDs
     * @return array User agents and device IDs
     */
    public function getUserAgentsWithDeviceId() {
        if (!isset($this->userAgentsWithDeviceID)) {
            $this->userAgentsWithDeviceID = $this->persistenceProvider->load($this->getPrefix());
        }
        return $this->userAgentsWithDeviceID;
    }

    //********************************************************
    //    Matching
    //
    //********************************************************
    /**
     * Finds the device id for the given request - if it is not found it
     * delegates to the next available handler
     *
     * @param \Wurfl\Request\GenericRequest $request
     * @return string WURFL Device ID for matching device
     */
    public function match(\Wurfl\Request\GenericRequest $request) {
        $userAgent = $request->userAgent;
        if ($this->canHandle($userAgent)) {
            return $this->applyMatch($request);
        }

        if (isset($this->nextHandler)) {
            return $this->nextHandler->match($request);
        }

        return \Wurfl\Constants::GENERIC;
    }

    /**
     * Template method to apply matching system to user agent
     *
     * @param \Wurfl\Request\GenericRequest $request
     * @return string Device ID
     */
    public function applyMatch(\Wurfl\Request\GenericRequest $request) {
        $class_name = get_class($this);
        $request->matchInfo->matcher = $class_name;
        $start_time = microtime(true);

        $userAgent = $this->normalizeUserAgent($request->userAgent);
        $request->matchInfo->normalized_user_agent = $userAgent;
        $this->logger->debug("START: Matching For  " . $userAgent);

        // Get The data associated with this current handler
        $this->userAgentsWithDeviceID = $this->persistenceProvider->load($this->getPrefix());
        if (!is_array($this->userAgentsWithDeviceID)) {
            $this->userAgentsWithDeviceID = array();
        }
        $deviceID = null;
        // Start with an Exact match
        $request->matchInfo->matcher_history .= "$class_name(exact),";
        $request->matchInfo->match_type = 'exact';
        $request->userAgentsWithDeviceID = $this->userAgentsWithDeviceID;

        $deviceID = $this->applyExactMatch($userAgent);

        // Try with the conclusive Match
        if ($this->isBlankOrGeneric($deviceID)) {
            $request->matchInfo->matcher_history .= "$class_name(conclusive),";
            $this->logger->debug("$this->prefix :Applying Conclusive Match for ua: $userAgent");
            $deviceID = $this->applyConclusiveMatch($userAgent);

            // Try with recovery match
            if ($this->isBlankOrGeneric($deviceID)) {
                // Log the ua and the ua profile
                //$this->logger->debug($request);
                $request->matchInfo->match_type = 'recovery';
                $request->matchInfo->matcher_history .= "$class_name(recovery),";
                $this->logger->debug("$this->prefix :Applying Recovery Match for ua: $userAgent");
                $deviceID = $this->applyRecoveryMatch($userAgent);

                // Try with catch all recovery Match
                if ($this->isBlankOrGeneric($deviceID)) {
                    $request->matchInfo->match_type = 'recovery-catchall';
                    $request->matchInfo->matcher_history .= "$class_name(recovery-catchall),";
                    $this->logger->debug("$this->prefix :Applying Catch All Recovery Match for ua: $userAgent");
                    $deviceID = $this->applyRecoveryCatchAllMatch($userAgent);

                    // All attempts to match have failed
                    if ($this->isBlankOrGeneric($deviceID)) {
                        $request->matchInfo->match_type = 'none';
                        if ($request->userAgentProfile) {
                            $deviceID = \Wurfl\Constants::GENERIC_MOBILE;
                        } else {
                            $deviceID = \Wurfl\Constants::GENERIC;
                        }
                    }
                }
            }
        }
        $this->logger->debug("END: Matching For  " . $userAgent);
        $request->matchInfo->lookup_time = microtime(true) - $start_time;
        return $deviceID;
    }
    /**
     * Given $deviceID is blank or generic, indicating no match
     * @param string $deviceID
     * @return bool
     */
    private function isBlankOrGeneric($deviceID) {
        return ($deviceID === null || strcmp($deviceID, "generic") === 0 || strlen(trim($deviceID)) == 0);
    }

    public function applyExactMatch($userAgent) {
        if (array_key_exists($userAgent, $this->userAgentsWithDeviceID)) {
            return $this->userAgentsWithDeviceID[$userAgent];
        }
        return \Wurfl\Constants::NO_MATCH;
    }

    /**
     * Attempt to find a conclusive match for the given $userAgent
     * @param string $userAgent
     * @return string Matching WURFL deviceID
     */
    public function applyConclusiveMatch($userAgent) {
        $match = $this->lookForMatchingUserAgent($userAgent);
        if (!empty($match)) {
            //die('<pre>'.htmlspecialchars(var_export($this->userAgentsWithDeviceID, true)).'</pre>');
            return $this->userAgentsWithDeviceID[$match];
        }
        return \Wurfl\Constants::NO_MATCH;
    }

    /**
     * Find a matching WURFL device from the given $userAgent. Override this method to give an alternative way to do the matching
     *
     * @param string $userAgent
     * @return string
     */
    public function lookForMatchingUserAgent($userAgent) {
        $tolerance = \Wurfl\Handlers\Utils::firstSlash($userAgent);
        return \Wurfl\Handlers\Utils::risMatch(array_keys($this->userAgentsWithDeviceID), $userAgent, $tolerance);
    }

    public function getDeviceIDFromRIS($userAgent, $tolerance) {
        $match = \Wurfl\Handlers\Utils::risMatch(array_keys($this->userAgentsWithDeviceID), $userAgent, $tolerance);
        if (!empty($match)) {
            return $this->userAgentsWithDeviceID[$match];
        }
        return \Wurfl\Constants::NO_MATCH;
    }

    public function getDeviceIDFromLD($userAgent, $tolerance=null) {
        $match = \Wurfl\Handlers\Utils::ldMatch(array_keys($this->userAgentsWithDeviceID), $userAgent, $tolerance);
        if (!empty($match)) {
            return $this->userAgentsWithDeviceID[$match];
        }
        return \Wurfl\Constants::NO_MATCH;
    }

    /**
     * Applies Recovery Match
     * @param string $userAgent
     * @return string $deviceID
     */
    public function applyRecoveryMatch($userAgent) {}

    /**
     * Applies Catch-All match
     * @param string $userAgent
     * @return string WURFL deviceID
     */
    public function applyRecoveryCatchAllMatch($userAgent) {
        if (\Wurfl\Handlers\Utils::isDesktopBrowserHeavyDutyAnalysis($userAgent)) {
            return \Wurfl\Constants::GENERIC_WEB_BROWSER;
        }
        $mobile = \Wurfl\Handlers\Utils::isMobileBrowser($userAgent);
        $desktop = \Wurfl\Handlers\Utils::isDesktopBrowser($userAgent);

        if (!$desktop) {
            $deviceId = \Wurfl\Handlers\Utils::getMobileCatchAllId($userAgent);
            if ($deviceId !== \Wurfl\Constants::NO_MATCH) {
                return $deviceId;
            }
        }

        if ($mobile) return \Wurfl\Constants::GENERIC_MOBILE;
        if ($desktop) return \Wurfl\Constants::GENERIC_WEB_BROWSER;
        return \Wurfl\Constants::GENERIC;
    }

    /**
     * Returns the prefix for this Handler, like BLACKBERRY_DEVICEIDS for the
     * BlackBerry Handler.  The "BLACKBERRY_" portion comes from the individual
     * Handler's $prefix property and "_DEVICEIDS" is added here.
     * @return string
     */
    public function getPrefix() {
        return $this->prefix . "_DEVICEIDS";
    }

    public function getNiceName() {
        $class_name = get_class($this);
        // \Wurfl\Handlers\AlcatelHandler
        preg_match('/^\Wurfl\Handlers\(.+)Handler$/', $class_name, $matches);
        return $matches[1];
    }

    /**
     * Returns true if given $deviceId exists
     * @param string $deviceId
     * @return bool
     */
    protected function isDeviceExist($deviceId) {
        $ids = array_values($this->userAgentsWithDeviceID);
        if (in_array($deviceId, $ids)) {
            return true;
        }
        return false;
    }

    public function __sleep() {
        return array(
                'nextHandler',
                'userAgentNormalizer',
                'prefix',
        );
    }
}
