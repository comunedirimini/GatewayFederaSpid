<?php

// include('/dati/gateway-federa/gw-config.php');

require __DIR__ . '/vendor/autoload.php';
use Base64Url\Base64Url;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\NativeMailerHandler;


// caricamento impostazioni da .env e settaggio variabili da $_ENV

$dotenv = Dotenv\Dotenv::create('/dati/gateway-federa/', 'gw-config.env');
$dotenv->load();

$DEBUG_GATEWAY = (getenv('DEBUG_GATEWAY') === 'true'? true : false);
$LOG_FILE = $_ENV['LOG_FILE'];
$LOG_PATH = $_ENV['LOG_PATH'];
$CONFIG_PATH = $_ENV['CONFIG_PATH'];
$TRANSACTIONS_PATH = $_ENV['TRANSACTIONS_PATH'];
$CIPHER_METHOD = $_ENV['CIPHER_METHOD'];


if ($DEBUG_GATEWAY) {
	error_reporting(E_ALL);
	ini_set('display_errors', '1');
	echo '<pre>';
    echo '<h1>GATEWAY DEBUG ENABLE! - GW-RESPONSE.PHP</h1>';
    print_r($_ENV);
}

if ($_SERVER['REQUEST_METHOD'] <> 'POST') {
    die('R_ERROR0'); 
}

if ($DEBUG_GATEWAY) { echo "LOG FILE         : "; echo $LOG_FILE; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo "CONFIG PATH      : "; echo $CONFIG_PATH; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo "CIPHER_METHOD    :"; echo $CIPHER_METHOD ; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo "TRANSACTIONS_PATH:"; echo $TRANSACTIONS_PATH ; echo "<br>"; }


// create a log channel
$log = new Logger('gw');
$log->pushHandler(new RotatingFileHandler($LOG_FILE,0,Logger::DEBUG));
if (!$DEBUG_GATEWAY) $log->pushHandler(new NativeMailerHandler('ruggero.ruggeri@comune.rimini.it', 'Errore GatewayFederaSpid','autenticazione-federa-spid@comune.rimini.it',Logger::ERROR,true, 70));

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
 
// controlla il RelayState se esiste
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

//check and get client configuration file .env
// TODO DA CIFRARE IL FILE CON I PARAMETRI
$crtFile = $CONFIG_PATH .  $appId . '.env';
if ($DEBUG_GATEWAY) { echo 'config file :'; echo $crtFile ; echo "<br>"; }

if (file_exists($crtFile)) {
    
    $dotenv = Dotenv\Dotenv::create($CONFIG_PATH, $appId . '.env');
	$dotenv->load();

	$GATEWAY_APP_ID = $_ENV['GATEWAY_APP_ID'];
	$GATEWAY_RETURN_URL = $_ENV['GATEWAY_RETURN_URL'];
    $GATEWAY_APP_KEY = $_ENV['GATEWAY_APP_KEY'];
    
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
$autenticationData = $autenticationData . ";" . ( isset($attributesArray['authenticationMethod'][0]) ? $attributesArray['authenticationMethod'][0] : 'NOauthenticationMethod');
$autenticationData = $autenticationData . ";" . ( isset($attributesArray['authenticatingAuthority'][0]) ? $attributesArray['authenticatingAuthority'][0] : 'NOauthenticatingAuthority');
$autenticationData = $autenticationData . ";" . ( isset($attributesArray['spidCode'][0]) ? $attributesArray['spidCode'][0] : 'NOspidCode');
$autenticationData = $autenticationData . ";" . ( isset($attributesArray['policyLevel'][0]) ? $attributesArray['policyLevel'][0] : 'NOpolicyLevel');
$autenticationData = $autenticationData . ";" . ( isset($attributesArray['trustLevel'][0]) ? $attributesArray['trustLevel'][0] : 'NOtrustLevel');
$autenticationData = $autenticationData . ";" . ( isset($attributesArray['userid'][0]) ? $attributesArray['userid'][0] : 'NOuserid');
$autenticationData = $autenticationData . ";" . ( isset($attributesArray['CodiceFiscale'][0]) ? $attributesArray['CodiceFiscale'][0] : 'NOCodiceFiscale');
$autenticationData = $autenticationData . ";" . ( isset($attributesArray['nome'][0]) ? $attributesArray['nome'][0] : 'NOnome');
$autenticationData = $autenticationData . ";" . ( isset($attributesArray['cognome'][0]) ? $attributesArray['cognome'][0] : 'NOcognome');
$autenticationData = $autenticationData . ";" . ( isset($attributesArray['dataNascita'][0]) ? $attributesArray['dataNascita'][0] : 'NOdataNascita');
$autenticationData = $autenticationData . ";" . ( isset($attributesArray['luogoNascita'][0]) ? $attributesArray['luogoNascita'][0] : 'NOluogoNascita');
$autenticationData = $autenticationData . ";" . ( isset($attributesArray['statoNascita'][0]) ? $attributesArray['statoNascita'][0] : 'NOstatoNascita');

/*
authenticationMethod,
authenticatingAuthority,
spidCode,
policyLevel,
trustLevel,
userid,
CodiceFiscale,
nome,
cognome,
dataNascita,
luogoNascita,
statoNascita
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
$logApp->pushHandler(new RotatingFileHandler($LOG_PATH . $appId . '.log',0,Logger::DEBUG));
$logApp->info('Response HTTP_REFERER : ' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '') );
$logApp->info('Response REMOTE_ADDR : ' . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '') );
$logApp->info('Response transactionId : ' . $transactionId );
$logApp->info('Response url : ' . $url2redirect );
$logApp->info('Response autenticationData : ' . $autenticationData );

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