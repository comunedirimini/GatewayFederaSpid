<?php

if ($DEBUG_GATEWAY) {
	error_reporting(E_ALL);
	ini_set('display_errors', '1');
}

include('./config/config.php');

// libreria log
include($LOG_FILE_LIB_PATH);
$Mylog = new Log($LOG_FILE);

// libreria SAML
define("TOOLKIT_PATH", $PHP_SAML_LIB_PATH);
require_once(TOOLKIT_PATH . '_toolkit_loader.php');

// libreria Base64Url
include($BASE64URL_LIB_PATH);
$b64url = new Base64Url\Base64Url;

$auth = new OneLogin_Saml2_Auth();

if ($DEBUG_GATEWAY) echo "<pre>";

$auth->processResponse();
$errors = $auth->getErrors();

if (!empty($errors)) {
    echo '<p>', implode(', ', $errors), '</p>';
    exit();
}
 
if(!isset($_POST['RelayState'])) die('R_ERROR1'); 

$relayStateArray = explode(";",$_POST['RelayState']);
if (sizeof($relayStateArray) <> 3) die('A_ERROR2');

$key = $relayStateArray[0];
$ts  = $relayStateArray[1];
$landingPage = $relayStateArray[2];

$pos = strpos($landingPage, '?');

$JOIN_CHAR = '';

if ($pos === false) {
    $JOIN_CHAR = '?';
} else {
    $JOIN_CHAR = '&';
}

//$relayStateArray['authenticatedUser'] = $auth->getAttributes(); 
//$relayStateArray['NameId'] = $auth->getNameId();
//$relayStateArray['NameIdFormat'] = $auth->getNameIdFormat();
//$relayStateArray['SessionIndex'] = $auth->getSessionIndex();


$NameId = $auth->getNameId();
$NameIdFormat = $auth->getNameIdFormat();
$attributesArray = $auth->getAttributes();

$autenticationData = $NameId;
$autenticationData = $autenticationData . ";" . $attributesArray['authenticationMethod'][0];
$autenticationData = $autenticationData . ";" . $attributesArray['authenticatingAuthority'][0];
$autenticationData = $autenticationData . ";" . $attributesArray['policyLevel'][0];
$autenticationData = $autenticationData . ";" . $attributesArray['trustLevel'][0];
$autenticationData = $autenticationData . ";" . $attributesArray['userid'][0];
$autenticationData = $autenticationData . ";" . $attributesArray['CodiceFiscale'][0];
$autenticationData = $autenticationData . ";" . $attributesArray['nome'][0];
$autenticationData = $autenticationData . ";" . $attributesArray['cognome'][0];
$autenticationData = $autenticationData . ";" . $attributesArray['dataNascita'][0];
$autenticationData = $autenticationData . ";" . $attributesArray['luogoNascita'][0];
$autenticationData = $autenticationData . ";" . $attributesArray['statoNascita'][0];

if ($DEBUG_GATEWAY) { print_r($attributesArray); echo "<br>"; echo $autenticationData; echo "<br>"; }

$crtFile = $CERT_PATH . $key . '.crt';

echo $crtFile; echo "<br>";


$fp=fopen($crtFile,"r") or die("R_ERROR3");
$public_key_string=fread($fp,8192);
fclose($fp);

if ($DEBUG_GATEWAY) { echo $public_key_string; echo "<br>"; }

if(!openssl_public_encrypt($autenticationData,$autenticationData_crypted,$public_key_string)) {
	while ($msg = openssl_error_string())  echo $msg . "<br />\n";
	die("R_ERROR4");
}

$b64_autenticationData_crypted =  $b64url->encode($autenticationData_crypted);
if ($DEBUG_GATEWAY) { echo $b64_autenticationData_crypted; echo "<br>"; }

$url2redirect = $landingPage. $JOIN_CHAR . 'authenticatedUser=' . $b64_autenticationData_crypted;

if ($DEBUG_GATEWAY) { echo $url2redirect; echo "<br>"; echo "<a href=\"" . $url2redirect . "\">FEDERA HA RISPOSTO RITORNO AL CLIENT</a>"; }
else {
	header('Location: ' . $url2redirect);
}

?>
