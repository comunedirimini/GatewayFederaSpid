<?php

// include('./config/config.php');

if ($DEBUG_GATEWAY) {
	error_reporting(E_ALL);
	ini_set('display_errors', '1');
}

require __DIR__ . '/vendor/autoload.php';

// libreria SAML
// define("TOOLKIT_PATH", $PHP_SAML_LIB_PATH);
// require_once(TOOLKIT_PATH . '_toolkit_loader.php');

try {
    $auth = new OneLogin_Saml2_Auth();
    $settings = $auth->getSettings();
    $metadata = $settings->getSPMetadata();
    $errors = $settings->validateMetadata($metadata);
    if (empty($errors)) {
        header('Content-Type: text/xml');
        echo $metadata;
    } else {
        throw new OneLogin_Saml2_Error(
            'Invalid SP metadata: '.implode(', ', $errors),
            OneLogin_Saml2_Error::METADATA_SP_INVALID
        );
    }
} catch (Exception $e) {
    echo $e->getMessage();
}

?>