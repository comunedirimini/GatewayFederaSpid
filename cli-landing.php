<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/vendor/autoload.php';

use Base64Url\Base64Url;

$authenticatedUser = $_GET['authenticatedUser'];

echo "<pre>";
echo "LANDING PAGE ...";
echo "<br>";
echo $authenticatedUser; echo "<br>";

// $b64_ts_crypted =  base64_encode($ts_crypted);
$authenticatedUser_decoded =  Base64Url::decode($authenticatedUser);
echo $authenticatedUser_decoded; echo "<br>";

$fp=fopen("./cli_certs/private.pem","r") or die('ERROR: private certificate not found!');
$private_key_string=fread($fp,8192);
fclose($fp);

echo $private_key_string;
echo "<br>";

if(!openssl_private_decrypt($authenticatedUser_decoded, $authenticatedUser_decrypted, $private_key_string)) {
	while ($msg = openssl_error_string())  echo $msg . "<br />\n";
	die('ERRORE nella  openssl_private_decrypt');
}

echo $authenticatedUser_decrypted;
echo "<br>";


$authenticatedDataArray = explode(";", $authenticatedUser_decrypted);

print_r($authenticatedDataArray);

?>
