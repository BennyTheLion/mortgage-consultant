<?php
// One-time utility: generates a VAPID key pair for Web Push and prints them so you can
// paste them into config.php (VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY) and assets/admin.js
// (VAPID_PUBLIC_KEY). Run from the command line:
//   php bin/gen_vapid.php
// On Windows/XAMPP, openssl_pkey_new() can't find OpenSSL's config file under the CLI's
// default environment either — if this fails with "Unable to create the key", run it as:
//   OPENSSL_CONF="C:\xampp\php\extras\openssl\openssl.cnf" php bin/gen_vapid.php   (Git Bash)
//   set OPENSSL_CONF=C:\xampp\php\extras\openssl\openssl.cnf && php bin\gen_vapid.php   (cmd)
require __DIR__ . '/../vendor/autoload.php';

$keys = \Minishlink\WebPush\VAPID::createVapidKeys();
echo "Public key:  {$keys['publicKey']}\n";
echo "Private key: {$keys['privateKey']}\n";
