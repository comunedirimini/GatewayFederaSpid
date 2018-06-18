<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/vendor/autoload.php';

use Base64Url\Base64Url;


echo "<pre>";

echo "<h1>Client per generazione chiave RSA</h1>";
echo "<br>";


$key_lenght = 20;
$bytes = openssl_random_pseudo_bytes($key_lenght);

echo "Generazione di una key random (demo non utilizzata): " . $bytes;
echo "<br>";

?>