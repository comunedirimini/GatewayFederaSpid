<?php

if ($DEBUG_GATEWAY) {
	error_reporting(E_ALL);
	ini_set('display_errors', '1');
	echo '<pre>';
	echo '<h1>GATEWAY DEBUG ENABLE! - GW-RESPONSE.PHP</h1>';
}

include('./config/config.php');

if ($DEBUG_GATEWAY) { echo "LOG FILE : "; echo $LOG_FILE; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo "CONFIG PATH : "; echo $CONFIG_PATH; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo "CIPHER_METHOD:"; echo $CIPHER_METHOD ; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo "TRANSACTIONS_PATH:"; echo $TRANSACTIONS_PATH ; echo "<br>"; }

require __DIR__ . '/vendor/autoload.php';
use Base64Url\Base64Url;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

// create a log channel
$log = new Logger('gw');
$log->pushHandler(new RotatingFileHandler($LOG_FILE,0,Logger::DEBUG));

// create Saml client and process response
$auth = new OneLogin_Saml2_Auth();
$auth->processResponse();
$errors = $auth->getErrors();

if (!empty($errors)) {
	echo '<p>', implode(', ', $errors), '</p>';
	$log->error('response.php error processing response');
	$log->error($errors);
    exit();
}
 
// check RelayState
if(!isset($_POST['RelayState']))  {
    $log->error('POST RelayState void!');
    die('R_ERROR1'); 
}

// get transactionId
$transactionId = $_POST['RelayState'];
if ($DEBUG_GATEWAY) { echo "transactionId: " . $transactionId; echo "<br>"; }

// check if exists transactionId
$transactionFileName = $TRANSACTIONS_PATH  . $transactionId . '.txt';
if ($DEBUG_GATEWAY) { echo 'transactionFileName: ' . $transactionFileName; echo "<br>"; }

if (file_exists($transactionFileName)) {
    $appId = file_get_contents($transactionFileName);
    if ($DEBUG_GATEWAY) { echo 'appId: ' . $appId; echo "<br>"; }
    // delete transaction file
    unlink($transactionFileName);
} else {
    $log->error('Transaction file NOT exists : ' . $transactionFileName);
    die('R_ERROR_TRANSACTION');
}

//check and get client configuration file
$crtFile = $CONFIG_PATH .  $appId . '.php';
if ($DEBUG_GATEWAY) { echo $crtFile ; echo "<br>"; }

if (file_exists($crtFile)) {
    require($crtFile);
    if ($DEBUG_GATEWAY) { echo "GATEWAY_APP_ID: "; echo $GATEWAY_APP_ID . "<br>"; }
	if ($DEBUG_GATEWAY) { echo "GATEWAY_RETURN_URL: "; echo $GATEWAY_RETURN_URL . "<br>"; }
	if ($DEBUG_GATEWAY) { echo "GATEWAY_APP_KEY: "; echo $GATEWAY_APP_KEY . "<br>"; }
} else {
    if ($DEBUG_GATEWAY) { echo $crtFile; echo " - NOT FOUND <br>"; }
	$log->error('client config file not found ' . $crtFile);
    die("R_ERROR4");
}

// get autentication data

$NameId = $auth->getNameId();
$NameIdFormat = $auth->getNameIdFormat();
$attributesArray = $auth->getAttributes();

$autenticationData = $transactionId;
$autenticationData = $autenticationData . ";" . $NameId;
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

/*
authenticationMethod,authenticatingAuthority,policyLevel,trustLevel,userid,CodiceFiscale,nome,cognome,dataNascita,luogoNascita,statoNascita
*/


if ($DEBUG_GATEWAY) { print_r($attributesArray); echo "<br>"; echo $autenticationData; echo "<br>"; }


// build response to the client

// set iv
$iv_length = openssl_cipher_iv_length($CIPHER_METHOD);
if ($DEBUG_GATEWAY) { echo "iv_length : " .$iv_length; echo "<br>"; }

$iv = random_str($iv_length);
if ($DEBUG_GATEWAY) { echo "iv : " .$iv; echo "<br>"; }

if (!$autenticationData_crypted = openssl_encrypt($autenticationData, $CIPHER_METHOD, $GATEWAY_APP_KEY, $options=0, $iv)) {
    if ($DEBUG_GATEWAY) {  while ($msg = openssl_error_string())  echo $msg . "<br />\n"; }
    die("R_ERROR5");
}

if ($DEBUG_GATEWAY) { echo "<h4>autenticationData_crypted</h4>"; echo $autenticationData_crypted; echo "<br>"; }

$autenticationData_crypted_b64 =  Base64Url::encode($autenticationData_crypted);
if ($DEBUG_GATEWAY) { echo "<h4>autenticationData_crypted_b64</h4>"; echo $autenticationData_crypted_b64; echo "<br>"; }

$url2redirect = $GATEWAY_RETURN_URL . '?data=' . $iv . $autenticationData_crypted_b64;

// setup log for appId
$logApp = new Logger($appId);
$logApp->info('Response start transaction Id : ' . $transactionId );
$logApp->info('Response url : ' . $url2redirect );
$logApp->info('Response autenticationData' );
$logApp->info( $autenticationData );


if ($DEBUG_GATEWAY) { echo "<h1>"; echo $url2redirect; echo "<br>"; echo "<a href=\"" . $url2redirect . "\">FEDERA HA RISPOSTO RITORNO AL CLIENT con i dati di autenticazione!</a></h1>"; }
else {
	header('Location: ' . $url2redirect);
}


// utility ---------------------------------------------------------------------------------------------

function random_str($length, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
{
    $pieces = [];
    $max = mb_strlen($keyspace, '8bit') - 1;
    for ($i = 0; $i < $length; ++$i) {
        $pieces []= $keyspace[random_int(0, $max)];
    }
    return implode('', $pieces);
}


?>