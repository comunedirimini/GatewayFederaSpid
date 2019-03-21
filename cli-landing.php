<html><body bgcolor="#FFAAFA">
<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/vendor/autoload.php';

use Base64Url\Base64Url;

echo "<pre>";
echo "<h1>LANDING PAGE CLIENT ...</h1>";
echo "<p>Riceve la risposta dal gateway cifrata la decifra ed utilizza i dati per il logon</p>";
echo "<br>";


if ( !$_GET['data'] ) {
	echo "PARAMETRO data mancante";
	die('data');
}


$authenticatedUser = substr($_GET['data'],16);
$iv = substr($_GET['data'],0,16);




$method = "aes-256-cbc";
echo "Cypher method: " . $method;
echo "<br>";

echo "iv: "; echo $iv; echo "<br>"; 
echo "data: "; echo $authenticatedUser; echo "<br>";

// $b64_ts_crypted =  base64_encode($ts_crypted);
$authenticatedUser_decoded =  Base64Url::decode($authenticatedUser);
echo $authenticatedUser_decoded; echo "<br>";

$fp=fopen("./cli-key.txt","r") or die('ERROR: key not found!');
$private_key_string=fread($fp,8192);
fclose($fp);

echo $private_key_string;
echo "<br>";

if( ! $authenticatedUser_decrypted = openssl_decrypt($authenticatedUser_decoded, $method, $private_key_string, $options=0, $iv)) {
	while ($msg = openssl_error_string())  echo $msg . "<br />\n";
	die('ERRORE nella  openssl_decrypt');
}

echo $authenticatedUser_decrypted;
echo "<br>";


$authenticatedDataArray = explode(";", $authenticatedUser_decrypted);

echo "<h1>Risposta dal gw .. dati utente ...</h1>";

print_r($authenticatedDataArray);

?>

<a href="cli-start.php">START</a>


</body></html>