<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include('./config/config.php');

// libreria log
include($LOG_FILE_LIB_PATH);
$Mylog = new Log($LOG_FILE);

// libreria SAML
define("TOOLKIT_PATH", $PHP_SAML_LIB_PATH);
require_once(TOOLKIT_PATH . '_toolkit_loader.php');

// libreria Base64Url
include($BASE64URL_LIB_PATH);
$b64url = new Base64Url\Base64Url;


$auth = new OneLogin_Saml2_Auth();

echo "<pre>";
// print_r($_POST);
// exit();

$auth->processResponse();


$errors = $auth->getErrors();

if (!empty($errors)) {
    echo '<p>', implode(', ', $errors), '</p>';
    exit();
}
 
if(!isset($_POST['RelayState'])) die('R_ERROR1'); 
 
// $attributes = $_SESSION['samlUserdata'];
// $nameId = $_SESSION['samlNameId'];
// echo '<b>' . print_r(json_decode($_POST['RelayState'],1)) . '<b>';
// echo '<h1>Identified user: '. htmlentities($nameId) .'</h1>';

// $par2send = explode(";",$ts_out);
// if (sizeof($par2send) <> 2) die('ERROR5');
// echo print_r($par2send);
// echo "<br>";

$relayStateArray = explode(";",$_POST['RelayState']);
if (sizeof($relayStateArray) <> 3) die('A_ERROR2');

$key = $relayStateArray[0];
$ts  = $relayStateArray[1];
$landingPage = $relayStateArray[2];

$Mylog->log('relayState : ' . $_POST['RelayState'] );

$pos = strpos($landingPage, '?');

$JOIN_CHAR = '';

if ($pos === false) {
    $JOIN_CHAR = '?';
} else {
    $JOIN_CHAR = '&';
}

$relayStateArray['authenticatedUser'] = $auth->getAttributes(); 
$relayStateArray['NameId'] = $auth->getNameId();
$relayStateArray['NameIdFormat'] = $auth->getNameIdFormat();
$relayStateArray['SessionIndex'] = $auth->getSessionIndex();


$NameId = $auth->getNameId();
$NameIdFormat = $auth->getNameIdFormat();
$attributesArray = $auth->getAttributes();
// $strAttributes = http_build_query($attributesArray, '', '&amp;');


$autenticationData = $NameId;
$autenticationData = $autenticationData . ";" . $attributesArray['authenticationMethod'][0];
$autenticationData = $autenticationData . ";" . $attributesArray['authenticatingAuthority'][0];
$autenticationData = $autenticationData . ";" . $attributesArray['policyLevel'][0];
$autenticationData = $autenticationData . ";" . $attributesArray['trustLevel'][0];
$autenticationData = $autenticationData . ";" . $attributesArray['userid'][0];
$autenticationData = $autenticationData . ";" . $attributesArray['CodiceFiscale'][0];
$autenticationData = $autenticationData . ";" . $attributesArray['nome'][0];
$autenticationData = $autenticationData . ";" . $attributesArray['cognome'][0];
$autenticationData = $autenticationData . ";" . $attributesArray['dataNascita'][0];
$autenticationData = $autenticationData . ";" . $attributesArray['luogoNascita'][0];
$autenticationData = $autenticationData . ";" . $attributesArray['statoNascita'][0];


/*
(
            [authenticationMethod] => Array
                (
                    [0] => password
                )

            [dataNascita] => Array
                (
                    [0] => 25/05/1970
                )

            [userid] => Array
                (
                    [0] => RGGRGR70E25H294T@federa.it
                )

            [statoNascita] => Array
                (
                    [0] => ITALIA
                )

            [policyLevel] => Array
                (
                    [0] => Basso
                )

            [nome] => Array
                (
                    [0] => RUGGERO
                )

            [CodiceFiscale] => Array
                (
                    [0] => RGGRGR70E25H294T
                )

            [trustLevel] => Array
                (
                    [0] => Medio
                )

            [luogoNascita] => Array
                (
                    [0] => RIMINI
                )

            [authenticatingAuthority] => Array
                (
                    [0] => Utenti Federa
                )

            [cognome] => Array
                (
                    [0] => RUGGERI
                )

        )

*/		
		
print_r($relayStateArray);



echo $autenticationData;
echo "<br>";


$crtFile = $CERT_PATH . $key . '.crt';

echo $crtFile;
echo "<br>";


$fp=fopen($crtFile,"r") or die("R_ERROR3");
$public_key_string=fread($fp,8192);
fclose($fp);

echo $public_key_string;
echo "<br>";

if(!openssl_public_encrypt($autenticationData,$autenticationData_crypted,$public_key_string)) {
	while ($msg = openssl_error_string())  echo $msg . "<br />\n";
	die("R_ERROR4");
}

// openssl_public_encrypt($autenticationData,$autenticationData_crypted,$public_key_string);

// $b64_ts_crypted =  base64_encode($ts_crypted);
$b64_autenticationData_crypted =  $b64url->encode($autenticationData_crypted);
echo $b64_autenticationData_crypted; echo "<br>";


$url2redirect = $landingPage. $JOIN_CHAR . 'authenticatedUser=' . $b64_autenticationData_crypted;


echo $url2redirect;
echo "<br>";

echo "<a href=\"" . $url2redirect . "\">GO</a>";

$Mylog->log($url2redirect);

// header('Location: ' . $url2redirect);




/*
if (!empty($attributes)) {
    echo '<h2>'._('User attributes:').'</h2>';
    echo '<table><thead><th>'._('Name').'</th><th>'._('Values').'</th></thead><tbody>';
    foreach ($attributes as $attributeName => $attributeValues) {
        echo '<tr><td>' . htmlentities($attributeName) . '</td><td><ul>';
        foreach ($attributeValues as $attributeValue) {
            echo '<li>' . htmlentities($attributeValue) . '</li>';
        }
        echo '</ul></td></tr>';
    }
    echo '</tbody></table>';
} else {
    echo _('No attributes found.');
}
*/

?>
