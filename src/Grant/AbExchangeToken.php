<?php

namespace League\OAuth2\Client\Grant;

class AbExchangeToken extends AbstractGrant
{
    public function __toString(): string
    {
        return 'ab_exchange_token';
    }

    protected function getRequiredRequestParameters(): array
    {
        return [
            'ab_exchange_token',
        ];
    }

    protected function getName(): string
    {
        return 'ab_exchange_token';
    }
}
