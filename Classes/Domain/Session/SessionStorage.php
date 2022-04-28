<?php

declare(strict_types=1);
namespace R3H6\Oauth2Server\Domain\Session;

use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/***
 *
 * This file is part of the "OAuth2 Server" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2020
 *
 ***/

/**
 * Session storage
 */
class SessionStorage implements SingletonInterface
{

    public const SESSION_NAMESPACE = 'Oauth2Server';

    /**
     * @var FrontendUserAuthentication
     */
    protected $feUserAuth;

    /**
     * Returns the object stored in the user's PHP session
     *
     * @param string $key the session key
     * @return mixed the stored object
     */
    public function restoreFromSession(string $key)
    {
        $sessionData = $this->getFeUserAuth()->getSessionData(self::SESSION_NAMESPACE . $key);
        if ($sessionData === null) {
            return null;
        }
        return unserialize($sessionData, ['allowed_classes' => true]);
    }

    /**
     * Writes an object into the PHP session
     *
     * @param string $key the session key
     * @param mixed $object any serializable object to store into the session
     * @return SessionStorage
     */
    public function writeToSession(string $key, $object): SessionStorage
    {
        $sessionData = serialize($object);
        $this->getFeUserAuth()->setAndSaveSessionData(self::SESSION_NAMESPACE . $key, $sessionData);
        return $this;
    }

    /**
     * Cleans up the session: removes the stored object from the PHP session
     *
     * @param string $key
     * @return SessionStorage
     */
    public function cleanUpSession(string $key): SessionStorage
    {
        $this->getFeUserAuth()->setAndSaveSessionData(self::SESSION_NAMESPACE . $key, null);
        return $this;
    }

    public function setFeUserAuth(FrontendUserAuthentication $feUserAuth): SessionStorage
    {
        $this->feUserAuth = $feUserAuth;
        return $this;
    }

    /**
     * Gets a frontend user session
     *
     * @return FrontendUserAuthentication The current frontend user object
     */
    protected function getFeUserAuth(): FrontendUserAuthentication
    {
        if ($this->feUserAuth === null) {
            if (!isset($GLOBALS['TSFE']) || !$GLOBALS['TSFE']->fe_user) {
                throw new \RuntimeException('No frontend user found in session!');
            }
            $this->feUserAuth = $GLOBALS['TSFE']->fe_user;
        }
        return $this->feUserAuth;
    }
}
