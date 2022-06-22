<?php

declare(strict_types=1);
namespace R3H6\Oauth2Server\Controller;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use R3H6\Oauth2Server\Configuration\Configuration;
use R3H6\Oauth2Server\Domain\Repository\UserRepository;
use R3H6\Oauth2Server\Domain\Session\SessionStorage;
use TYPO3\CMS\Core\Error\Http\ForbiddenException;
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

/**
 * UserInfo endpoint
 */
class UserInfoController extends AbstractController
{

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var ResponseFactoryInterface
     */
    protected $responseFactory;

    /**
     * @var StreamFactoryInterface
     */
    protected $streamFactory;

    /**
     * @var UserRepository
     */
    protected $userRepository;

    /**
     * @var SessionStorage
     */
    protected $sessionStorage;

    public function __construct(
        Configuration $configuration,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        UserRepository $userRepository,
        SessionStorage $sessionStorage
    ) {
        $this->configuration = $configuration;
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
        $this->userRepository = $userRepository;
        $this->sessionStorage = $sessionStorage;
    }

    public function show(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->createContext($request);
        if (!$context->isAuthenticated()) {
            throw new ForbiddenException();
        }

        if (!\is_array($this->configuration->getUserinfoAllowedPropertiesMapping())
            || empty($this->configuration->getUserinfoAllowedPropertiesMapping())
        ) {
            throw new \RuntimeException('No UserinfoAllowedPropertiesMapping defined!', 1651146878);
        }
        $allowedPropertiesDb = array_keys($this->configuration->getUserinfoAllowedPropertiesMapping());
        $allowedPropertiesDb = $this->cleanAllowedProperties($allowedPropertiesDb);
        $allowedPropertiesMapped = $this->configuration->getUserinfoAllowedPropertiesMapping();

        $user = $this->userRepository->findByUidRaw($context->getFrontendUserUid());


        $requestParams = $request->getQueryParams();
        $requestedProperties = GeneralUtility::trimExplode(
            ',',
            $requestParams['properties'] ?? $requestParams['fields'] ?? '',
            true
        );

        $responseParams['identifier'] = $user['uid'];
        $responseParams['uid'] = $user['uid'];
        foreach ($requestedProperties as $requestedProperty) {
            if (\in_array($requestedProperty, $allowedPropertiesDb, true)) {
                $responseParams[$allowedPropertiesMapped[$requestedProperty]] = $user[$requestedProperty];
            }
        }

        return $this->responseFactory->createResponse()
                                     ->withHeader('Content-Type', 'application/json; charset=utf-8')
                                     ->withBody($this->streamFactory->createStream(json_encode($responseParams)));
    }

    /**
     * @param array $allowedProperties
     *
     * @return array
     */
    protected function cleanAllowedProperties(array $allowedProperties): array
    {
        $disallowedProperties = [
            'password',
            'uc',
            'mfa',
            'felogin_forgotHash'
        ];
        $cleanAllowedProperties = array_unique($allowedProperties);

        foreach ($disallowedProperties as $disallowedProperty) {
            $hits = array_keys($cleanAllowedProperties, $disallowedProperty);
            foreach ($hits as $hit) {
                unset($cleanAllowedProperties[$hit]);
            }
        }
        return $cleanAllowedProperties;
    }
}
