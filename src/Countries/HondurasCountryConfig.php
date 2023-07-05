<?php

namespace PlacetoPay\Countries;

use PlacetoPay\Constants\CountryCode;
use PlacetoPay\Constants\Environment;

abstract class HondurasCountryConfig extends CountryConfig
{
    public static function resolve(string $countryCode): bool
    {
        return CountryCode::HONDURAS === $countryCode;
    }

    public static function getEndpoints(string $client): array
    {
        return array_merge(parent::getEndpoints($client), [
            Environment::TEST => 'https://uy-uat-checkout.placetopay.com',
            Environment::PRODUCTION => 'https://checkout.placetopay.uy',
        ]);
    }
}
