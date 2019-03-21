<?php

echo "<h4>***FAKE GATEWAY for TEST SERVICE PROVIDER ***</H4>";
echo "<p>Non si passa da FEDERA ma viene testato il canale di comunicazione e viene ritornata una risposta compatibile</p>";

require __DIR__ . '/vendor/autoload.php';
use Base64Url\Base64Url;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\NativeMailerHandler;

$dotenv = Dotenv\Dotenv::create('/dati/gateway-federa/', 'gw-config.env');
$dotenv->load();

$DEBUG_GATEWAY = true;
$LOG_FILE = '/dati/gateway-federa/log/gateway-fake.log';
$LOG_PATH = '/dati/gateway-federa/log/gateway-fake.log';
$CONFIG_PATH = $_ENV['CONFIG_PATH'];
$TRANSACTIONS_PATH = $_ENV['TRANSACTIONS_PATH'];
$CIPHER_METHOD = $_ENV['CIPHER_METHOD'];

if ($DEBUG_GATEWAY) { echo "LOG FILE         :"; echo $LOG_FILE; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo "LOG PATH         :"; echo $LOG_PATH; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo "CONFIG PATH      :"; echo $CONFIG_PATH; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo "CIPHER_METHOD    :"; echo $CIPHER_METHOD ; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo "TRANSACTIONS_PATH:"; echo $TRANSACTIONS_PATH ; echo "<br>"; }

$log = new Logger('gw-fake');
$log->pushHandler(new RotatingFileHandler($LOG_FILE,0,Logger::DEBUG));

// FAKE START ---------------------------------------------------------------------

echo "<pre>";
echo "<h4>Fake response - START</H4>";
// dalla risposta SAML recupero il relay STATE che contiene il transactionId


$params = array();

$getPar = $_GET; 

// controllo parametri in arrivo

// i parametri devono essere un array
if (!is_array($getPar)) {
	$log->error('getPar NOT ARRAY!');
	die('A_ERROR1');
}   

// deve essere di lunghezza 2
if (sizeof($getPar) <> 2) {
	$log->error('getPar NOT 2! but :' . sizeof($getPar));
	die('A_ERROR2');
} 

if ($DEBUG_GATEWAY) { echo "Parametri ricevuti: "; echo "<br>"; }
if ($DEBUG_GATEWAY) { print_r($getPar); }

// recupera gli altri parametri separo $iv ed i dati cifrati

$iv = substr($getPar['data'],0,16);
$appId = $getPar['appId'];
$b64_ts_crypted = substr($getPar['data'],16);

if (strlen($appId) > 8) {
	$log->error('appId too long max 8!', $appId);
	die('A_ERROR2LEN');
} 

if ($DEBUG_GATEWAY) { echo "iv            :"; echo $iv; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo "appId         :"; echo $appId ; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo "b64_ts_crypted:"; echo $b64_ts_crypted ; echo "<br>"; }

// decode base 64
$ts_crypted_out = Base64Url::decode($b64_ts_crypted);
if ($DEBUG_GATEWAY) { echo "ts 2 decode64 encrypted:"; echo $ts_crypted_out ; echo "<br>"; }

// recupera i dati di configurazione
// $GATEWAY_APP_ID = ''
// $GATEWAY_RETURN_URL = ''
// $GATEWAY_APP_KEY = ''

$crtFile = $CONFIG_PATH .  $appId . '.env';
if ($DEBUG_GATEWAY) { echo 'config file :'; echo $crtFile ; echo "<br>"; }

// controlla che il file esista per la configurazione .env
// e carica i parametri

if (file_exists($crtFile)) {
	
	$dotenv = Dotenv\Dotenv::create($CONFIG_PATH, $appId . '.env');
	$dotenv->load();

	$GATEWAY_APP_ID = $_ENV['GATEWAY_APP_ID'];
	$GATEWAY_RETURN_URL = $_ENV['GATEWAY_RETURN_URL'];
	$GATEWAY_APP_KEY = $_ENV['GATEWAY_APP_KEY'];
	
	if ($DEBUG_GATEWAY) { echo "GATEWAY_APP_ID    : "; echo $GATEWAY_APP_ID . "<br>"; }
	if ($DEBUG_GATEWAY) { echo "GATEWAY_RETURN_URL: "; echo $GATEWAY_RETURN_URL . "<br>"; }
	if ($DEBUG_GATEWAY) { echo "GATEWAY_APP_KEY   : "; echo $GATEWAY_APP_KEY . "<br>"; }
} else {
    if ($DEBUG_GATEWAY) { echo $crtFile; echo " - NOT FOUND <br>"; }
	$log->error('config file not found : ' . $crtFile);
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

// controllo coerenza appId, ovvero il parametro inviato deve corrispondere a quello cifrato

if ($appIdDecoded <> $appId) {
	die('APPID NOT VALID');
	$log->error($appId . " - APPID NOT VALID!" . $appIdDecoded);
} 
if ($DEBUG_GATEWAY) { echo 'APPID VALID: ' . $appId; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo 'RELAY STATE: ' . $transactionId; echo "<br>"; }


$_POST['RelayState'] = $transactionId;

$NameId = 'RGGRGR70E25H294T';
$NameIdFormat = 'urn:_____';

$autenticationData = $transactionId;
$autenticationData = $autenticationData . ";" . $NameId;
$autenticationData = $autenticationData . ";" . 'FAKE_FEDERA';
$autenticationData = $autenticationData . ";" . 'FAKE_AUTHORITY';
$autenticationData = $autenticationData . ";" . 'FAKE_SPID_CODE';
$autenticationData = $autenticationData . ";" . 'FAKE_POLICY_LEVEL';
$autenticationData = $autenticationData . ";" . 'FAKE_TRUST_LEVEL';
$autenticationData = $autenticationData . ";" . 'F_USERID';
$autenticationData = $autenticationData . ";" . 'F_CF';
$autenticationData = $autenticationData . ";" . 'F_NOME';
$autenticationData = $autenticationData . ";" . 'F_COGNOME';
$autenticationData = $autenticationData . ";" . 'F_DATA_NASCITA';
$autenticationData = $autenticationData . ";" . 'F_LUOGO_NASCITA';
$autenticationData = $autenticationData . ";" . 'F_STATO_NASCITA';

// FAKE END ---------------------------------------------------------------------

if ($DEBUG_GATEWAY) { echo 'AuthData: ' . $autenticationData; echo "<br>"; }

// esecuzione regolare response.php


// get transactionId
$transactionId = $_POST['RelayState'];
if ($DEBUG_GATEWAY) { echo "transactionId: " . $transactionId; echo "<br>"; }

// verifica dell'esistenza della transazione
$transactionFileName = $TRANSACTIONS_PATH  . $transactionId . '.txt';
if ($DEBUG_GATEWAY) { echo 'transactionFileName: ' . $transactionFileName; echo "<br>"; }


// $log->info('resp:'. $key . ':' . $autenticationData);

$crtFile = $CONFIG_PATH .  $appId . '.php';
echo $crtFile; echo "<br>";

// recupera i dati di configurazione

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
} else {
    if ($DEBUG_GATEWAY) { echo $crtFile; echo " - NOT FOUND <br>"; }
	$log->error('config file not found ' . $crtFile);
    die("R_ERROR4");
}

// prepara la risposta da inviare al client

// genera iv
$iv_length = openssl_cipher_iv_length($CIPHER_METHOD);
echo "iv_length : " .$iv_length; echo "<br>";
$iv = random_str($iv_length);
echo "iv : " .$iv; echo "<br>";

if (!$autenticationData_crypted = openssl_encrypt($autenticationData, $CIPHER_METHOD, $GATEWAY_APP_KEY, $options=0, $iv)) {
    if ($DEBUG_GATEWAY) {  while ($msg = openssl_error_string())  echo $msg . "<br />\n"; }
    die("R_ERROR5");
}

if ($DEBUG_GATEWAY) { echo "autenticationData_crypted:"; echo $autenticationData_crypted; echo "<br>"; }

$autenticationData_crypted_b64 =  Base64Url::encode($autenticationData_crypted);
if ($DEBUG_GATEWAY) { echo "autenticationData_crypted_b64:"; echo $autenticationData_crypted_b64; echo "<br>"; }

$url2redirect = $GATEWAY_RETURN_URL . '?data=' . $iv . $autenticationData_crypted_b64;


echo $url2redirect; echo "<br>"; echo "<h1><a href=\"" . $url2redirect . "\">FAKE FEDERA HA RISPOSTO RITORNO AL CLIENT</a></h1>"; 


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