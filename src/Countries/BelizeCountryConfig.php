<?php

namespace PlacetoPay\Countries;

use PlacetoPay\Constants\CountryCode;
use PlacetoPay\Constants\Environment;

abstract class BelizeCountryConfig extends CountryConfig
{
    public static function resolve(string $countryCode): bool
    {
        return CountryCode::BELIZE === $countryCode;
    }

    public static function getEndpoints(string $client): array
    {
        return array_merge(parent::getEndpoints($client), [
            Environment::PRODUCTION => 'https://abgateway.atlabank.com'
        ]);
    }
}
