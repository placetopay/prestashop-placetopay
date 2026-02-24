<?php

namespace PlacetoPay;

use PlacetoPay\Constants\Environment;

abstract class CountryConfig
{
    public const CLIENT_ID = 'placetopay-ecuador';

    public const CLIENT = 'Placetopay';

    public const IMAGE = 'https://static.placetopay.com/placetopay-logo.svg';

    public const COUNTRY_CODE = 'EC';

    public const COUNTRY_NAME = 'Ecuador';

    public static function getEndpoints(): array
    {
        return [
            Environment::DEVELOPMENT => 'https://checkout-ec.placetopay.dev',
            Environment::TEST => 'https://checkout-test.placetopay.ec',
            Environment::PRODUCTION => 'https://checkout.placetopay.ec',
        ];
    }
}

