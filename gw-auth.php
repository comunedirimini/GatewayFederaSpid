<?php

include('./config/config.php');

require __DIR__ . '/vendor/autoload.php';
use Base64Url\Base64Url;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

// create a log channel
$log = new Logger('gw');
$log->pushHandler(new RotatingFileHandler($LOG_FILE,0,Logger::DEBUG));


if ($DEBUG_GATEWAY) {
	error_reporting(E_ALL);
	ini_set('display_errors', '1');
	echo '<pre>';
	echo '<h1>GATEWAY DEBUG ENABLE - GW-AUTH.PHP</h1>';
}

if ($DEBUG_GATEWAY) { echo "START - GW-AUTH.PHP -  : "; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo "LOG FILE : "; echo $LOG_FILE; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo "CONFIG PATH : "; echo $CONFIG_PATH; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo "CIPHER_METHOD:"; echo $CIPHER_METHOD ; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo "TRANSACTIONS_PATH:"; echo $TRANSACTIONS_PATH ; echo "<br>"; }


$auth = new OneLogin_Saml2_Auth(); 

$params = array();

$getPar = $_GET; 

// controllo parametri 
if (!is_array($getPar)) {
	$log->error('getPar NOT ARRAY!');
	die('A_ERROR1');
}   

if (sizeof($getPar) <> 2) {
	$log->error('getPar NOT 2! but ' . sizeof($getPar));
	die('A_ERROR2');
} 

if ($DEBUG_GATEWAY) { echo "Parametri ricevuti: "; echo "<br>"; }
if ($DEBUG_GATEWAY) { print_r($getPar); }

// recupera gli altri parametri
$iv = substr($getPar['data'],0,16);
$appId = $getPar['appId'];
$b64_ts_crypted = substr($getPar['data'],16);

if (strlen($appId) > 8) {
	$log->error('appId too long max 8!');
	die('A_ERROR2LEN');
} 

if ($DEBUG_GATEWAY) { echo "iv: "; echo $iv; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo "appId:"; echo $appId ; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo "b64_ts_crypted:"; echo $b64_ts_crypted ; echo "<br>"; }

// decode base 64
$ts_crypted_out = Base64Url::decode($b64_ts_crypted);
if ($DEBUG_GATEWAY) { echo "ts 2 decode encrypted:"; echo $ts_crypted_out ; echo "<br>"; }

// recupera i dati di configurazione
// $GATEWAY_APP_ID = ''
// $GATEWAY_RETURN_URL = ''
// $GATEWAY_APP_KEY = ''

$crtFile = $CONFIG_PATH .  $appId . '.php';
if ($DEBUG_GATEWAY) { echo 'config file :'; echo $crtFile ; echo "<br>"; }

if (file_exists($crtFile)) {
    require($crtFile);
    if ($DEBUG_GATEWAY) { echo "GATEWAY_APP_ID: "; echo $GATEWAY_APP_ID . "<br>"; }
	if ($DEBUG_GATEWAY) { echo "GATEWAY_RETURN_URL: "; echo $GATEWAY_RETURN_URL . "<br>"; }
	if ($DEBUG_GATEWAY) { echo "GATEWAY_APP_KEY: "; echo $GATEWAY_APP_KEY . "<br>"; }
} else {
    if ($DEBUG_GATEWAY) { echo $crtFile; echo " - NOT FOUND <br>"; }
	$log->error('config file not found ' . $crtFile);
    die("R_ERROR4");
}


if(! $ts_out = openssl_decrypt($ts_crypted_out, $CIPHER_METHOD, $GATEWAY_APP_KEY, $options=0, $iv)) {
	$log->error('decrypt client data error ');
	while ($msg = openssl_error_string()) { 
			if ($DEBUG_GATEWAY) {  echo $msg . "<br />\n"; }
			$log->error($msg);
	}
	die('A_ERROR4');
} 

// if(!openssl_public_decrypt($ts_crypted_out, $ts_out, $public_key_string)) die('A_ERROR4');

if ($DEBUG_GATEWAY) {  echo 'data decrypted: ' . $ts_out; echo "<br>"; }

// recupero appId e uuidv4 della transazione per fare il log ed inviarlo come relay state

$pieces = explode(";", $ts_out);
if (sizeof($pieces) <> 2) {
	die('A_ERROR_PIECES_NOT_2');
	$log->error($appId . " - ERROR_PIECES_NOT_2");
} 

$appIdDecoded = $pieces[0];
$transactionId = $pieces[1];

// controllo coerenza appId

if ($appIdDecoded <> $appId) {
	die('APPID NOT VALID');
	$log->error($appId . " - APPID NOT VALID!");
} 

if ($DEBUG_GATEWAY) { echo 'RELAY STATE: ' . $transactionId; echo "<br>"; }

// log relay state to $TRANSACTIONS_PATH/$transactionId

$transactionFileName = $TRANSACTIONS_PATH  . $transactionId . '.txt';
if ($DEBUG_GATEWAY) { echo 'transactionFileName: ' . $transactionFileName; echo "<br>"; }

file_put_contents($transactionFileName, $appId);

$url = $auth->login($transactionId,$params,false,false,true,'urn:oasis:names:tc:SAML:2.0:nameid-format:transient');   
$lastRequestID = $auth->getLastRequestID();

// $log->info('auth:'. $lastRequestID . ':' . $ts_out);

$logApp = new Logger($appId);
$logApp->pushHandler(new RotatingFileHandler($LOG_PATH . $appId . '.log',0,Logger::DEBUG));
$logApp->info('Request transactionId: ' . $transactionId);
$logApp->info('Request saml id: ' . $lastRequestID);
$logApp->info('Request url: ' . $url);

if ($DEBUG_GATEWAY) { echo "<h1>"; echo $url; echo "<br>"; echo "<a href=\"" . $url . "\">PREPARATO SAML VAI A FEDERA</a></h1><br>"; }
else {
	header('Pragma: no-cache');
	header('Cache-Control: no-cache, must-revalidate');
	header('Location: ' . $url);
	exit();
}


?>