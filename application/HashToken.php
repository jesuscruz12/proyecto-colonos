<?php
//clase para encriptar toke y desencriptar
class HashToken
{
    public static function encryptHashToken($token)
    {
        $key = hash('sha256', SECRET_KEY_FOR_ENCRYPT_TOKEN); //
        $iv = substr(hash('sha256', '32f9b0600c7387de2f6f180945a41dd4'), 0, 16);

        $token_encriptado = openssl_encrypt($token, 'AES-256-CBC', $key, 0, $iv);

        return $token_encriptado;
    }

    public static function decryptHashToken($token)
    {
        $key = hash('sha256', SECRET_KEY_FOR_ENCRYPT_TOKEN); //
        $iv = substr(hash('sha256', '32f9b0600c7387de2f6f180945a41dd4'), 0, 16);

        $token_desencriptado = openssl_decrypt($token, 'AES-256-CBC', $key, 0, $iv);

        return $token_desencriptado;
    }
}
