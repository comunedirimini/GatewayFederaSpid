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


// create a log channel
$log = new Logger('gw');
$log->pushHandler(new RotatingFileHandler($LOG_FILE,0,Logger::DEBUG));
$log->pushHandler(new NativeMailerHandler('ruggero.ruggeri@comune.rimini.it', 'Accesso ai log GatewayFederaSpid','autenticazione-federa-spid@comune.rimini.it',Logger::ERROR,true, 70));

if ($DEBUG_GATEWAY) {
	error_reporting(E_ALL);
	ini_set('display_errors', '1');
	echo '<pre>';
	echo '<h1>GATEWAY DEBUG ENABLE - GW-GETLOG.PHP</h1>';
}

if ($DEBUG_GATEWAY) { echo "START - GW-GETLOG.PHP -  : "; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo "LOG PATH : "; echo $LOG_PATH; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo "CONFIG PATH : "; echo $CONFIG_PATH; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo "CIPHER_METHOD:"; echo $CIPHER_METHOD ; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo "TRANSACTIONS_PATH:"; echo $TRANSACTIONS_PATH ; echo "<br>"; }


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


$log->info('Accesso ai log da parte di: ' . $appId);
$log->error('Accesso ai log da parte di: ' . $appId);


// $log->info('auth:'. $lastRequestID . ':' . $ts_out);
// get log content from LOG_PATH

$fileFilter = $LOG_PATH . $appId . "-*.log" ;

if ($DEBUG_GATEWAY) { echo 'File Filter: ' . $fileFilter; echo "<br>"; }

echo '<pre>';

foreach (glob($fileFilter) as $filename) {
    echo "$filename size " . filesize($filename) . "\n";

    $file = fopen($filename, "r") or exit("Unable to open file!");
    //Output a line of the file until the end is reached
    while(!feof($file))
      {
      echo fgets($file);
      }
    fclose($file);

}

// display log 



?>