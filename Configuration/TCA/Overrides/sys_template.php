<?php
defined('TYPO3_MODE') || die();

(function () {

    $extensionKey = 'oauth2_server';

    /**
     * Default TypoScript
     */
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
        $extensionKey,
        'Configuration/TypoScript',
        'OAuth2 Server'
    );

})();
