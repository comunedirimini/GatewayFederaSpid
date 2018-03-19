<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include('Base64Url.php');
$b64url = new Base64Url\Base64Url;



$authenticatedUser = $_GET['authenticatedUser'];


echo "<pre>";
echo "LANDING PAGE ...";
echo "<br>";
echo $authenticatedUser; echo "<br>";


// $b64_ts_crypted =  base64_encode($ts_crypted);
$authenticatedUser_decoded =  $b64url->decode($authenticatedUser);
echo $authenticatedUser_decoded; echo "<br>";


$fp=fopen("./certs/private.pem","r") or die('ERROR: private certificate not found!');
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


// $url = $auth->login($gwId,$params,false,false,true,true);   // Method that sent the AuthNRequest

// echo "Ciao";

// echo $url;


// $lastMessageId = $auth->getLastMessageId();
// $lastAssertionId = $auth->getLastAssertionId();
// $lastRequestID = $auth->getLastRequestID();

// echo '<h1>msg:' . $lastRequestID . '</h1>';
// echo '<h1>ass:' . $lastAssertionId . '</h1>';

// exit();





?>
