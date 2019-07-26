<link rel="stylesheet" href="https://cdn.rawgit.com/Chalarangelo/mini.css/v3.0.1/dist/mini-default.min.css">
<h1>Client test gateway per autenticazione verso FEDERA SPID</h1>

<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/vendor/autoload.php';

use Base64Url\Base64Url;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;

echo "<pre>";

print_r($_SERVER);

// generazione uuid4 per tracciare il flusso di autenticazione
// il client lo dovrà memorizzare per riconoscere l'autenticità
// del messaggio di ritorno dell'autenticazione dal gateway

$uuid4 = Uuid::uuid4();
$uuid4String = $uuid4->toString();
// $uuid4String = 'FAKEUUIDV4';

echo "UUID4 transaction id: " . $uuid4String;
echo "<br>";

$method = "aes-256-cbc";
echo "Cypher method: " . $method;
echo "<br>";

// usa la key che è conosciuta dal client e dal gateway
$key_string='__CHIAVESUPERSEGRETA__CHIAVESU__';
echo "Key string : " .$key_string;
echo "<br>";

// prepara i dati da cifrare appId e uuidv4 separati da ;
$appId = "appdemo";
$ts = $appId . ";" . $uuid4String;

$iv_length = openssl_cipher_iv_length($method); // len 16
echo "iv_length : " .$iv_length; echo "<br>";

// genera iv 16 per la randomizzazione della cifratura
$iv = random_str($iv_length);
// $iv = 'FAKEIV1234567890';
echo "iv : " .$iv; echo "<br>";

echo "<br>";

echo "<b>dati da cifrare: </b>";
echo $ts;
echo "<br>";

// cifra il messaggio con la chiave e con il metodo impostati
$ts_crypted = openssl_encrypt($ts, $method, $key_string, $options=0, $iv);

echo "<b>dati cifrati   : </b>";
echo $ts_crypted; echo "<br>";

// echo $ts_crypted;echo "<br>";

// encoding base64 del messaggio cifrato
$b64_ts_crypted =  Base64Url::encode($ts_crypted);
echo "<b>dati cifrati b64: </b>"; 
echo $b64_ts_crypted; echo "<br>";
                    
echo "<b>decrypt per verifica:</b>"; echo "<br>";

// $ts_crypted_out = base64_decode($b64_ts_crypted);
$ts_crypted_out = Base64Url::decode($b64_ts_crypted);

echo "openssl_decrypt: ";
$ts_out = openssl_decrypt($ts_crypted_out, $method, $key_string, $options=0, $iv);
echo $ts_out; echo "<br>";

// preparo la url con i parametri appId e data
// il parametro data è composto per la prima parte di $iv e la parte cifrata $b64_ts_crypted

$url  = "gw-auth.php?appId=appdemo&data=" . $iv . $b64_ts_crypted; 
$url_fake = "gw-authFAKE.php?appId=appdemo&data=" . $iv . $b64_ts_crypted;
$url_log = "gw-getlog.php?appId=appdemo&data=" . $iv . $b64_ts_crypted;

?>
</pre>


<a  class="button primary" href="<?php echo $url ?>">login con FEDERA</a>
<pre><?php echo $url ?></pre>

<a class="button primary" href="<?php echo $url_fake ?>">login FAKE non passa da FEDERA (debug gateway)</a>
<pre><?php echo $url_fake ?></pre>

<a class="button primary" href="<?php echo $url_log ?>">log relativi a appdemo</a>
<pre><?php echo $url_log ?></pre>



<a class="button primary" href="metadata.php">metadata</a>

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