<?php

namespace PlacetoPay\Constants;

use PlacetoPay\Countries\BelizeCountryConfig;
use PlacetoPay\Countries\ChileCountryConfig;
use PlacetoPay\Countries\ColombiaCountryConfig;
use PlacetoPay\Countries\CountryConfig;
use PlacetoPay\Countries\CountryConfigInterface;
use PlacetoPay\Countries\EcuadorCountryConfig;
use PlacetoPay\Countries\HondurasCountryConfig;
use PlacetoPay\Countries\UruguayCountryConfig;

abstract class PaymentUrl
{
    public const COUNTRIES_CONFIG = [
        EcuadorCountryConfig::class,
        ChileCountryConfig::class,
        HondurasCountryConfig::class,
        BelizeCountryConfig::class,
        UruguayCountryConfig::class,
        ColombiaCountryConfig::class,
        CountryConfig::class
    ];
    public static function getEndpointsTo(string $countryCode): array
    {
        /** @var CountryConfigInterface $config */
        foreach (self::COUNTRIES_CONFIG as $config) {
            if (!$config::resolve($countryCode)) {
                continue;
            }

            return $config::getEndpoints($countryCode);
        }

        return [];
    }
}
