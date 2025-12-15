<?php

namespace PlacetoPay;

use PlacetoPay\Constants\Environment;

abstract class CountryConfig
{
    public const CLIENT = 'Placetopay';
    public const IMAGE = 'https://static.placetopay.com/placetopay-logo.svg';
    public const COUNTRY_CODE = 'CO';
    public const COUNTRY_NAME = 'Colombia';

    public static function getEndpoints(): array
    {
        return [
            Environment::DEVELOPMENT => 'https://checkout-co.placetopay.dev',
            Environment::TEST => 'https://checkout-test.placetopay.com',
            Environment::PRODUCTION => 'https://checkout.placetopay.com',
        ];
    }
}

