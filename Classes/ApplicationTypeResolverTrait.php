<?php

namespace R3H6\Oauth2Server;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Http\ServerRequest;

/***
 *
 * This file is part of the "OAuth2 Server" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2021
 *
 ***/

trait ApplicationTypeResolverTrait
{

    /**
     * @var ServerRequestInterface
     */
    protected $request;

    /**
     * This method detect the current context and return one of the
     * following strings:
     * - FE
     * - BE
     * - CLI
     *
     * @return string
     */
    public function resolveApplicationType(): string
    {
        // @todo default to FE here?
        $context = '';
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (Environment::isCli()) {
            $context = 'CLI';
        } elseif ($request instanceof ServerRequest && ApplicationType::fromRequest($request)->isBackend()) {
            $context = 'BE';
        } elseif ($request instanceof ServerRequest && ApplicationType::fromRequest($request)->isFrontend()) {
            $context = 'FE';
        }
        return $context;
    }
}
