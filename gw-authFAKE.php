<?php

echo "<h1>FAKE GATEWAY for TEST SERVICE PROVIDER!</H1>";
echo "<p>Non si passa da FEDERA ma viene testato il canale di comunicazione e viene ritornata una risposta compatibile</p>";
include('./gw-auth.php');

use Base64Url\Base64Url;

// FAKE START ---------------------------------------------------------------------

echo "<pre>";
echo "<h3>Fake response - START</H3>";
// dalla risposta SAML recupero il relay STATE che contiene il transactionId
$_POST['RelayState'] = $transactionId;

$NameId = 'RGGRGR70E25H294T';
$NameIdFormat = 'urn:_____';
$attributesArray = $auth->getAttributes();


$autenticationData = $transactionId;
$autenticationData = $autenticationData . ";" . $NameId;
$autenticationData = $autenticationData . ";" . 'FAKE_FEDERA';
$autenticationData = $autenticationData . ";" . 'FAKE_AUTHORITY';
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

// ABILITARE I LOG GIA' ABILITATI da auth.php 
/*
$log = new Logger('gw');
$log->pushHandler(new RotatingFileHandler($LOG_FILE,0,Logger::DEBUG));

$logApp = new Logger($appId);
$logApp->pushHandler(new RotatingFileHandler($LOG_PATH . $appId . '.log',0,Logger::DEBUG));
$logApp->info('Request transactionId: ' . $transactionId);
$logApp->info('Request saml id: ' . $lastRequestID);
$logApp->info('Request url: ' . $url);
*/


// esecuzione regolare response.php

if ($DEBUG_GATEWAY) { echo "LOG FILE : "; echo $LOG_FILE; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo "CONFIG PATH : "; echo $CONFIG_PATH; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo "CIPHER_METHOD:"; echo $CIPHER_METHOD ; echo "<br>"; }
if ($DEBUG_GATEWAY) { echo "TRANSACTIONS_PATH:"; echo $TRANSACTIONS_PATH ; echo "<br>"; }


if ($DEBUG_GATEWAY) { echo "Relay state: " . $_POST['RelayState']; echo "<br>"; echo "<br>"; }
if(!isset($_POST['RelayState']))  {
    $log->error('POST RelayState void!');
    die('R_ERROR1'); 
}

// get transactionId
$transactionId = $_POST['RelayState'];
if ($DEBUG_GATEWAY) { echo "transactionId: " . $transactionId; echo "<br>"; }

// verifica dell'esistenza della transazione
$transactionFileName = $TRANSACTIONS_PATH  . $transactionId . '.txt';
if ($DEBUG_GATEWAY) { echo 'transactionFileName: ' . $transactionFileName; echo "<br>"; }


if (file_exists($transactionFileName)) {
    $appId = file_get_contents($transactionFileName);
    if ($DEBUG_GATEWAY) { echo 'appId: ' . $appId; echo "<br>"; }
    // rimuove il file della transazione
    unlink($transactionFileName);

} else {
    $log->error('Transaction file NOT exists : ' . $transactionFileName);
    die('R_ERROR_TRANSACTION');
}

// $log->info('resp:'. $key . ':' . $autenticationData);

$crtFile = $CONFIG_PATH .  $appId . '.php';
echo $crtFile; echo "<br>";

// recupera i dati di configurazione

$crtFile = $CONFIG_PATH .  $appId . '.php';
if ($DEBUG_GATEWAY) { echo $crtFile ; echo "<br>"; }

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

if ($DEBUG_GATEWAY) { echo "<h4>autenticationData_crypted</h4>"; echo $autenticationData_crypted; echo "<br>"; }

$autenticationData_crypted_b64 =  Base64Url::encode($autenticationData_crypted);
if ($DEBUG_GATEWAY) { echo "<h4>autenticationData_crypted_b64</h4>"; echo $autenticationData_crypted_b64; echo "<br>"; }

$url2redirect = $GATEWAY_RETURN_URL . '?data=' . $iv . $autenticationData_crypted_b64;

$logApp->info('Response start transaction Id : ' . $transactionId );
$logApp->info('Response url : ' . $url2redirect );
$logApp->info('Response autenticationData' );
$logApp->info( $autenticationData );


if ($DEBUG_GATEWAY) { echo $url2redirect; echo "<br>"; echo "<a href=\"" . $url2redirect . "\">FEDERA HA RISPOSTO RITORNO AL CLIENT</a>"; }
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