<?php

$data = "Beeeeer is really good.. hic...";

// You can get a simple private/public key pair using:
// openssl genrsa 512 >private_key.txt
// openssl rsa -pubout <private_key.txt >public_key.txt

// IMPORTANT: The key pair below is provided for testing only. 
// For security reasons you must get a new key pair
// for production use, obviously.

$private_key = <<<EOD
-----BEGIN RSA PRIVATE KEY-----
MIIBOgIBAAJBANDiE2+Xi/WnO+s120NiiJhNyIButVu6zxqlVzz0wy2j4kQVUC4Z
RZD80IY+4wIiX2YxKBZKGnd2TtPkcJ/ljkUCAwEAAQJAL151ZeMKHEU2c1qdRKS9
sTxCcc2pVwoAGVzRccNX16tfmCf8FjxuM3WmLdsPxYoHrwb1LFNxiNk1MXrxjH3R
6QIhAPB7edmcjH4bhMaJBztcbNE1VRCEi/bisAwiPPMq9/2nAiEA3lyc5+f6DEIJ
h1y6BWkdVULDSM+jpi1XiV/DevxuijMCIQCAEPGqHsF+4v7Jj+3HAgh9PU6otj2n
Y79nJtCYmvhoHwIgNDePaS4inApN7omp7WdXyhPZhBmulnGDYvEoGJN66d0CIHra
I2SvDkQ5CmrzkW5qPaE2oO7BSqAhRZxiYpZFb5CI
-----END RSA PRIVATE KEY-----
EOD;
$public_key = <<<EOD
-----BEGIN PUBLIC KEY-----
MFwwDQYJKoZIhvcNAQEBBQADSwAwSAJBANDiE2+Xi/WnO+s120NiiJhNyIButVu6
zxqlVzz0wy2j4kQVUC4ZRZD80IY+4wIiX2YxKBZKGnd2TtPkcJ/ljkUCAwEAAQ==
-----END PUBLIC KEY-----
EOD;

$binary_signature = "";

// At least with PHP 5.2.2 / OpenSSL 0.9.8b (Fedora 7)
// there seems to be no need to call openssl_get_privatekey or similar.
// Just pass the key as defined above
openssl_sign($data, $binary_signature, $private_key, OPENSSL_ALGO_SHA1);

print_r($binary_signature . "\n");

// Check signature
$ok = openssl_verify($data, $binary_signature, $public_key, OPENSSL_ALGO_SHA1);
echo "check #1: ";
if ($ok == 1) {
    echo "signature ok (as it should be)\n";
} elseif ($ok == 0) {
    echo "bad (there's something wrong)\n";
} else {
    echo "ugly, error checking signature\n";
}

$ok = openssl_verify('tampered'.$data, $binary_signature, $public_key, OPENSSL_ALGO_SHA1);
echo "check #2: ";
if ($ok == 1) {
    echo "ERROR: Data has been tampered, but signature is still valid! Argh!\n";
} elseif ($ok == 0) {
    echo "bad signature (as it should be, since data has beent tampered)\n";
} else {
    echo "ugly, error checking signature\n";
}

?>
