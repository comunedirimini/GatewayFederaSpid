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
$log->pushHandler(new NativeMailerHandler('ruggero.ruggeri@comune.rimini.it', 'Errore GatewayFederaSpid','autenticazione-federa-spid@comune.rimini.it',Logger::ERROR,true, 70));


if ($DEBUG_GATEWAY) {
	error_reporting(E_ALL);
	ini_set('display_errors', '1');
	echo '<pre>';
	echo gettype($DEBUG_GATEWAY), "\n";
	echo $DEBUG_GATEWAY, "\n";
	echo '<h1>GATEWAY FEDERA/SPID</h1>';
	echo '<h1>ATTENZIONE DEBUG ENABLE - GW-AUTH.PHP</h1>';
	echo 'HTTP_REFERER : ' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '') . '<br/>';
	echo 'REMOTE_ADDR : ' . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '') . '<br/>';
}

if ($DEBUG_GATEWAY) { echo "START - GW-AUTH.PHP"; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo "LOG FILE         :"; echo $LOG_FILE; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo "CONFIG PATH      :"; echo $CONFIG_PATH; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo "CIPHER_METHOD    :"; echo $CIPHER_METHOD ; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo "TRANSACTIONS_PATH:"; echo $TRANSACTIONS_PATH ; echo "<br>"; }


$auth = new OneLogin_Saml2_Auth(); 

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

// aggiunge il uuidv4 inviato dal client come relay state nella richiesta SAML e come
// log in $TRANSACTIONS_PATH/$transactionId

$transactionFileName = $TRANSACTIONS_PATH  . $transactionId . '.txt';
if ($DEBUG_GATEWAY) { echo 'transactionFileName: ' . $transactionFileName; echo "<br>"; }

// metto nel file del transaction l'id della app che ha richiesto la transazione
file_put_contents($transactionFileName, $appId);

$url = $auth->login($transactionId,$params,false,false,true,'urn:oasis:names:tc:SAML:2.0:nameid-format:transient');   
$lastRequestID = $auth->getLastRequestID();

// $log->info('auth:'. $lastRequestID . ':' . $ts_out);

$logApp = new Logger($appId);
$logApp->pushHandler(new RotatingFileHandler($LOG_PATH . $appId . '.log',0,Logger::DEBUG));
$logApp->info('Request HTTP_REFERER : ' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '') );
$logApp->info('Request REMOTE_ADDR : ' . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '') );
$logApp->info('Request transactionId: ' . $transactionId);
$logApp->info('Request samlId: ' . $lastRequestID);
$logApp->info('Request url: ' . $url);

if ($DEBUG_GATEWAY) { echo "SAML_URL:"; echo $url; echo "<br>"; echo "<h1><a href=\"" . $url . "\">PREPARATO SAML VAI A FEDERA</a></h1><br>"; }
else {
	header('Pragma: no-cache');
	header('Cache-Control: no-cache, must-revalidate');
	header('Location: ' . $url);
	exit();
}


?>