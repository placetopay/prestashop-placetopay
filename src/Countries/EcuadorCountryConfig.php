<?php

namespace PlacetoPay\Countries;

use PlacetoPay\Constants\CountryCode;
use PlacetoPay\Constants\Environment;

abstract class EcuadorCountryConfig extends CountryConfig
{
    public static function resolve(string $countryCode): bool
    {
        return CountryCode::ECUADOR === $countryCode;
    }

    public static function getEndpoints(): array
    {
        return array_merge(parent::getEndpoints(), [
            Environment::PRODUCTION => 'https://checkout.placetopay.ec',
            Environment::TEST => 'https://checkout-test.placetopay.ec',
            Environment::DEVELOPMENT => 'https://checkout-ec.placetopay.dev',
        ]);
    }
}
