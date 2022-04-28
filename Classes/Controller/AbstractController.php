<?php

declare(strict_types=1);
namespace R3H6\Oauth2Server\Controller;

use Psr\Http\Message\ServerRequestInterface;
use R3H6\Oauth2Server\Http\RequestAttribute;
use R3H6\Oauth2Server\Mvc\Controller\AuthorizationContext;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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

abstract class AbstractController
{

    protected function createContext(ServerRequestInterface $request): AuthorizationContext
    {
        $context = GeneralUtility::makeInstance(AuthorizationContext::class);
        $context->setRequest($request);
        $context->setSite($request->getAttribute('site'));
        $context->setFrontendUser($request->getAttribute('frontend.user'));
        $context->setConfiguration($request->getAttribute(RequestAttribute::CONFIGURATION));
        return $context;
    }
}
