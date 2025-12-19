<?php

namespace PlacetoPay\Constants;

abstract class BanchilePaymentUrl
{
    public static function getEndpointsTo(string $countryCode): array
    {
        switch ($countryCode) {
            case CountryCode::CHILE:
                $endpoints = [
                    Environment::PRODUCTION => 'https://checkout.getnet.cl',
                    Environment::TEST => 'https://checkout.test.getnet.cl',
                    Environment::DEVELOPMENT => 'https://checkout-cl.placetopay.dev',
                ];

                break;
        }

        return array_merge([
            Environment::PRODUCTION => 'https://checkout.placetopay.com',
            Environment::TEST => 'https://checkout-test.placetopay.com',
            Environment::DEVELOPMENT => 'https://checkout-co.placetopay.dev',
        ], $endpoints ?? []);
    }
}