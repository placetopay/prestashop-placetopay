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

    public static function getEndpoints(string $client): array
    {
        if ($client === unmaskString(Client::GOU)) {
            return array_merge(parent::getEndpoints($client), [
                Environment::PRODUCTION => unmaskString('uggcf://purpxbhg.ninycnlpragre.pbz'),
                Environment::TEST => unmaskString('uggcf://purpxbhg.grfg.ninycnlpragre.pbz'),
            ]);
        }

        return parent::getEndpoints($client);
    }

    public static function getClient(): array
    {
        return [
            unmaskString(Client::PTP) => unmaskString(Client::PTP),
            unmaskString(Client::GOU) => unmaskString('NinyCnl'),
        ];
    }
}
