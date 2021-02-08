<?php

namespace R3H6\Oauth2Server\Controller;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerAwareInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;
use R3H6\Oauth2Server\Utility\HashUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use R3H6\Oauth2Server\Utility\ScopeUtility;
use TYPO3\CMS\Core\Routing\RouterInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use League\OAuth2\Server\AuthorizationServer;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use R3H6\Oauth2Server\Domain\Repository\UserRepository;
use League\OAuth2\Server\Exception\OAuthServerException;
use R3H6\Oauth2Server\Domain\Repository\AccessTokenRepository;

class AuthorizationController implements LoggerAwareInterface
{
    public const AUTH_REQUEST_SESSION_KEY = 'oauth2/authRequest';

    use LoggerAwareTrait;
    use ExceptionHandlingTrait;

    /**
     * @var \League\OAuth2\Server\AuthorizationServer
     */
    protected $server;

    /**
     * @var \R3H6\Oauth2Server\Domain\Repository\UserRepository
     */
    protected $userRepository;

    /**
     * @var \R3H6\Oauth2Server\Domain\Repository\AccessTokenRepository
     */
    protected $accessTokenRepository;

    public function __construct(AuthorizationServer $server, UserRepository $userRepository, AccessTokenRepository $accessTokenRepository)
    {
        $this->server = $server;
        $this->userRepository = $userRepository;
        $this->accessTokenRepository = $accessTokenRepository;
    }

    public function startAuthorization(ServerRequestInterface $request): ResponseInterface
    {
        /** @var \R3H6\Oauth2Server\Configuration\RuntimeConfiguration configuration */
        $configuration = $request->getAttribute('oauth2');

        /** @var \TYPO3\CMS\Core\Site\Entity\Site $site */
        $site = $request->getAttribute('site');

        /** @var \TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication */
        $frontendUser = $request->getAttribute('frontend.user');

        // Validate the HTTP request and return an AuthorizationRequest object.
        $authRequest = $this->server->validateAuthorizationRequest($request);
        $frontendUser->setAndSaveSessionData(self::AUTH_REQUEST_SESSION_KEY, $authRequest);

        $client = $authRequest->getClient();
        $isAuthenticated = ($frontendUser->user['uid'] ?? 0) > 0; // Groups are not yet loaded in context api
        $consentPageUid = (int) $configuration->get('server.consentPageUid');

        if (!$consentPageUid) {
            throw new \RuntimeException('Missing configuration consent page uid', 1612712296482);
        }

        $consentUrl = (string) $site->getRouter()->generateUri($consentPageUid, [], '', RouterInterface::ABSOLUTE_URL);
        $redirectUrl = $client->doSkipConsent() ? (string) $request->getUri(): $consentUrl;

        if (!$isAuthenticated) {
            return new RedirectResponse('/?_='.urlencode(HashUtility::encode($redirectUrl)));
        }

        $clientId = $client->getIdentifier();
        if ($client->doSkipConsent()) {
            $this->logger->debug('client skips consent', ['clientId' => $clientId]);
            return $this->approveAuthorization($request);
        }

        $user = $this->userRepository->findByUid((int)$frontendUser->user['uid']);
        $userId = $user->getIdentifier();
        $scopes = ScopeUtility::toStrings(...$authRequest->getScopes());
        if ($this->accessTokenRepository->hasValidAccessToken($userId, $clientId, $scopes)) {
            $this->logger->debug('has valid access token', ['userId' => $userId, 'clientId' => $clientId, 'scopes' => $scopes]);
            return $this->approveAuthorization($request);
        }

        return new RedirectResponse($consentUrl);
    }

    public function approveAuthorization(ServerRequestInterface $request): ResponseInterface
    {
        /** @var \TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication */
        $frontendUser = $request->getAttribute('frontend.user');

        /** @var \League\OAuth2\Server\RequestTypes\AuthorizationRequest|null */
        $authRequest = $frontendUser->getSessionData(self::AUTH_REQUEST_SESSION_KEY);
        $frontendUser->setAndSaveSessionData(self::AUTH_REQUEST_SESSION_KEY, null);

        // Once the user has logged in set the user on the AuthorizationRequest
        $user = $this->userRepository->findByUid((int)$frontendUser->user['uid']);




        $authRequest->setUser($user);

        // Consent
        $authRequest->setAuthorizationApproved(true);

        return $this->withErrorHandling(function() use ($authRequest) {
            return $this->server->completeAuthorizationRequest($authRequest, new Response());
        });
    }

    public function denyAuthorization(ServerRequestInterface $request): ResponseInterface
    {
        /** @var \TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication */
        $frontendUser = $request->getAttribute('frontend.user');

        /** @var \League\OAuth2\Server\RequestTypes\AuthorizationRequest|null */
        $authRequest = $frontendUser->getSessionData(self::AUTH_REQUEST_SESSION_KEY);
        $frontendUser->setAndSaveSessionData(self::AUTH_REQUEST_SESSION_KEY, null);

        // Once the user has logged in set the user on the AuthorizationRequest
        $user = $this->userRepository->findByUid((int)$frontendUser->user['uid']);
        $authRequest->setUser($user);

        // Consent
        $authRequest->setAuthorizationApproved(false);

        return $this->withErrorHandling(function() use ($authRequest) {
            return $this->server->completeAuthorizationRequest($authRequest, new Response());
        });
    }
}
