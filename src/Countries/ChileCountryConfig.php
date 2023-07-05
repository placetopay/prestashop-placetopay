<?php

namespace PlacetoPay\Countries;

use PlacetoPay\Constants\Client;
use PlacetoPay\Constants\CountryCode;
use PlacetoPay\Constants\Environment;

abstract class ChileCountryConfig extends CountryConfig
{
    public static function resolve(string $countryCode): bool
    {
        return CountryCode::CHILE === $countryCode;
    }

    public static function getEndpoints(string $client): array
    {
        return array_merge(parent::getEndpoints($client), [
            Environment::PRODUCTION => unmaskString('uggcf://purpxbhg.trgarg.py'),
            Environment::TEST => unmaskString('uggcf://purpxbhg.grfg.trgarg.py'),
            Environment::DEVELOPMENT => 'https://checkout-cl.placetopay.dev',
        ]);
    }

    public static function getClient(): array
    {
        return [
            unmaskString(Client::GNT) => unmaskString(Client::GNT)
        ];
    }
}
