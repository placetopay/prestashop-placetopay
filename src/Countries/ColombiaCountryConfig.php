<?php

namespace PlacetoPay\Countries;

use PlacetoPay\Constants\Client;
use PlacetoPay\Constants\CountryCode;
use PlacetoPay\Constants\Environment;

class ColombiaCountryConfig extends CountryConfig
{
    public static function resolve(string $countryCode): bool
    {
        return CountryCode::COLOMBIA === $countryCode;
    }

    public static function getEndpoints(string $client = ''): array
    {
        if ($client === unmaskString(Client::GO)) {
            return array_merge(parent::getEndpoints(), [
                Environment::PRODUCTION => unmaskString('uggcf://purpxbhg.tbhcntbf.pbz.pb'),
                Environment::TEST => unmaskString('uggcf://purpxbhg.grfg.tbhcntbf.pbz.pb'),
            ]);
        }

        return parent::getEndpoints();
    }

    public static function getClient(): array
    {
        return [
            Client::PT => Client::PT,
            unmaskString(Client::GO) => unmaskString(Client::GO),
        ];
    }
}