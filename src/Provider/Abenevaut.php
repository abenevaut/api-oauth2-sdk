<?php

namespace abenevaut\OAuth2\Provider;

use abenevaut\OAuth2\Provider\Exception\AbenevautProviderException;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @method AbenevautUser getResourceOwner(AccessToken $token)
 */
class Abenevaut extends AbstractProvider
{
    /**
     * Production Graph API URL.
     *
     * @const string
     */
    protected const BASE_ABENEVAUT_URL = 'https://api.benevaut.fr/';

    /**
     * Beta tier URL of the Graph API.
     *
     * @const string
     */
    protected const BASE_ABENEVAUT_URL_BETA = 'https://beta.api.benevaut.fr/';

    /**
     * Production Graph API URL.
     *
     * @const string
     */
    protected const BASE_GRAPH_URL = 'https://graph.benevaut.fr/';

    /**
     * Beta tier URL of the Graph API.
     *
     * @const string
     */
    protected const BASE_GRAPH_URL_BETA = 'https://beta.graph.benevaut.fr/';

    /**
     * Regular expression used to check for graph API version format
     *
     * @const string
     */
    protected const GRAPH_API_VERSION_REGEX = '~^v\d+\.\d+$~';

    /**
     * The Graph API version to use for requests.
     *
     * @var string
     */
    protected $graphApiVersion;

    /**
     * A toggle to enable the beta tier URL's.
     *
     * @var boolean
     */
    private $enableBetaMode = false;

    /**
     * The fields to look up when requesting the resource owner
     *
     * @var string[]
     */
    protected $fields;

    /**
     * @param array $options
     * @param array $collaborators
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($options = [], array $collaborators = [])
    {
        parent::__construct($options, $collaborators);

        if (empty($options['graphApiVersion'])) {
            $message = 'The "graphApiVersion" option not set. Please set a default Graph API version.';
            throw new \InvalidArgumentException($message);
        }

        if (!preg_match(self::GRAPH_API_VERSION_REGEX, $options['graphApiVersion'])) {
            $message = 'The "graphApiVersion" must start with letter "v" followed by version number, ie: "v2.4".';
            throw new \InvalidArgumentException($message);
        }

        $this->graphApiVersion = $options['graphApiVersion'];

        if (!empty($options['enableBetaTier']) && $options['enableBetaTier'] === true) {
            $this->enableBetaMode = true;
        }

        if (!empty($options['fields']) && is_array($options['fields'])) {
            $this->fields = $options['fields'];
        } else {
            $this->fields = [
                'id', 'name', 'first_name', 'last_name',
                'email', 'hometown', 'picture.type(large){url,is_silhouette}',
                'gender', 'age_range'
            ];

            // backwards compatibility less than 2.8
            if (version_compare(substr($this->graphApiVersion, 1), '2.8') < 0) {
                $this->fields[] = 'bio';
            }
        }
    }

    public function getBaseAuthorizationUrl(): string
    {
        return $this->getBaseAbenevautUrl() . $this->graphApiVersion . '/dialog/oauth';
    }

    public function getBaseAccessTokenUrl(array $params): string
    {
        return $this->getBaseGraphUrl() . $this->graphApiVersion . '/oauth/access_token';
    }

    public function getDefaultScopes(): array
    {
        return ['public_profile', 'email'];
    }

    public function getResourceOwnerDetailsUrl(AccessToken $token): string
    {
        $appSecretProof = AppSecretProof::create($this->clientSecret, $token->getToken());

        return $this->getBaseGraphUrl()
            . $this->graphApiVersion
            . '/me?fields=' . implode(',', $this->fields)
            . '&access_token=' . $token . '&appsecret_proof=' . $appSecretProof;
    }

    public function getAccessToken($grant = 'authorization_code', array $params = []): AccessTokenInterface
    {
        if (isset($params['refresh_token'])) {
            throw new AbenevautProviderException('Abenevaut does not support token refreshing.');
        }

        return parent::getAccessToken($grant, $params);
    }

    /**
     * Exchanges a short-lived access token with a long-lived access-token.
     */
    public function getLongLivedAccessToken(string $accessToken): AccessTokenInterface
    {
        $params = [
            'ab_exchange_token' => $accessToken,
        ];

        return $this->getAccessToken('ab_exchange_token', $params);
    }

    protected function createResourceOwner(array $response, AccessToken $token): AbenevautUser
    {
        return new AbenevautUser($response);
    }

    protected function checkResponse(ResponseInterface $response, $data): void
    {
        if (empty($data['error'])) {
            return;
        }

        $message = $data['error']['type'] . ': ' . $data['error']['message'];
        throw new IdentityProviderException($message, $data['error']['code'], $data);
    }

    /**
     * @inheritdoc
     */
    protected function getContentType(ResponseInterface $response): string
    {
        $type = parent::getContentType($response);

        // Fix for Abenevaut's pseudo-JSONP support
        if (strpos($type, 'javascript') !== false) {
            return 'application/json';
        }

        // Fix for Abenevaut's pseudo-urlencoded support
        if (strpos($type, 'plain') !== false) {
            return 'application/x-www-form-urlencoded';
        }

        return $type;
    }

    /**
     * Get the base Abenevaut URL.
     */
    protected function getBaseAbenevautUrl(): string
    {
        return $this->enableBetaMode
            ? static::BASE_ABENEVAUT_URL_BETA
            : static::BASE_ABENEVAUT_URL;
    }

    /**
     * Get the base Graph API URL.
     */
    protected function getBaseGraphUrl(): string
    {
        return $this->enableBetaMode
            ? static::BASE_GRAPH_URL_BETA
            : static::BASE_GRAPH_URL;
    }
}
