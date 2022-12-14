<?php

namespace abenevaut\OAuth2\Provider;

class AppSecretProof
{
    /**
     * The app secret proof to sign requests made to the Graph API
     * @see https://developers.Abenevaut.com/docs/graph-api/securing-requests#appsecret_proof
     *
     * @param string $appSecret
     * @param string $accessToken
     * @return string
     */
    public static function create(string $appSecret, string $accessToken): string
    {
        return hash_hmac('sha256', $accessToken, $appSecret);
    }
}
