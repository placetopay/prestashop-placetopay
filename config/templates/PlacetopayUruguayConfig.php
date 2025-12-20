<?php

namespace PlacetoPay;

use PlacetoPay\Constants\Environment;

abstract class CountryConfig
{
    public const CLIENT_ID = 'placetopay-uruguay';
    public const CLIENT = 'Placetopay';
    public const IMAGE = 'https://static.placetopay.com/placetopay-logo.svg';
    public const COUNTRY_CODE = 'UY';
    public const COUNTRY_NAME = 'Uruguay';

    public static function getEndpoints(): array
    {
        return [
            Environment::DEVELOPMENT => 'https://checkout-uy.placetopay.dev',
            Environment::TEST => 'https://checkout-test.placetopay.com',
            Environment::PRODUCTION => 'https://checkout.placetopay.com',
        ];
    }
}

