<?php

namespace PlacetoPay\Countries;

use PlacetoPay\Constants\Client;
use PlacetoPay\Constants\Environment;
use PlacetoPay\Payments\Model\Adminhtml\Source\Mode;

class CountryConfig implements CountryConfigInterface
{
    public static function resolve(string $countryCode): bool
    {
        return true;
    }

    public static function getEndpoints(): array
    {
        return [
            Environment::PRODUCTION => 'https://checkout.placetopay.com',
            Environment::TEST => 'https://checkout-test.placetopay.com',
            Environment::DEVELOPMENT => 'https://checkout-co.placetopay.dev',
        ];
    }

    public static function getClient(): array
    {
        return [
            Client::PTP => Client::PTP
        ];
    }
}
