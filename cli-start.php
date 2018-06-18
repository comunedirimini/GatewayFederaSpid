<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/vendor/autoload.php';

use Base64Url\Base64Url;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;


// PAGINA DI DEFAULT PER IL CONSUMO DELLE CREDENZIALI
$landingPage = "https://autenticazione-test.comune.rimini.it/cli-landing.php";


echo "<pre>";

echo "<h1>Client test gateway per autenticazione verso FEDERA TEST</h1>";
echo "<br>";

$uuid4 = Uuid::uuid4();
$uuid4String = $uuid4->toString();
// $uuid4String = 'FAKEUUIDV4';

echo "UUID4 transaction id: " . $uuid4String;
echo "<br>";

$method = "aes-256-cbc";
echo "Cypher method: " . $method;
echo "<br>";

// prepara i dati da cifrare appId e uuidv4
$appId = "appdemo";
$ts = $appId . ";" . $uuid4String;


$fp=fopen("./cli-key.txt","r") or die('ERROR: key not found!');
$key_string=fread($fp,8192);
fclose($fp);

echo "Key string : " .$key_string;
echo "<br>";

$iv_length = openssl_cipher_iv_length($method); // len 16
echo "iv_length : " .$iv_length; echo "<br>";

// genera iv 16
$iv = random_str($iv_length);
// $iv = 'FAKEIV1234567890';
echo "iv : " .$iv; echo "<br>";

echo "<br>";

echo "<b>dati da cifrare: </b>";
echo $ts;
echo "<br>";


// cifra il messaggio con la chiave
$ts_crypted = openssl_encrypt($ts, $method, $key_string, $options=0, $iv);

echo "<b>dati cifrati   : </b>";
echo $ts_crypted; echo "<br>";

// echo $ts_crypted;echo "<br>";

// encoding base64 del messaggio cifrato
$b64_ts_crypted =  Base64Url::encode($ts_crypted);
echo "<b>dati cifrati b64: </b>"; 
echo $b64_ts_crypted; echo "<br>";
                    
echo "<h4>decrypt per verifica:</h4>"; echo "<br>";

// $ts_crypted_out = base64_decode($b64_ts_crypted);
$ts_crypted_out = Base64Url::decode($b64_ts_crypted);

echo "openssl_decrypt: ";
$ts_out = openssl_decrypt($ts_crypted_out, $method, $key_string, $options=0, $iv);
echo $ts_out; echo "<br>";

// preparo la url con i parametri appId e data

$url  = "https://autenticazione-test.comune.rimini.it/gw-auth.php?appId=appdemo&data=" . $iv .$b64_ts_crypted; 
$url_fake = "https://autenticazione-test.comune.rimini.it/gw-authFAKE.php?appId=appdemo&data=" . $iv .$b64_ts_crypted;

?>
<b><?php echo $url ?></b>
<h1><a href="<?php echo $url ?>">login con FEDERA TEST</a></h1>

<b><?php echo $url_fake ?></b>
<h1><a href="<?php echo $url_fake ?>">login FAKE non passa da FEDERA (debug gateway)</a></h1>

<h1><a href="https://autenticazione-test.comune.rimini.it/metadata.php">metadata</a></h1>

<?php

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