<?php

namespace PlacetoPay\Constants;

abstract class PaymentUrl
{
    public static function getEndpointsTo(string $countryCode): array
    {
        switch ($countryCode) {
            case CountryCode::ECUADOR:
                $endpoints = [
                    Environment::PRODUCTION => 'https://checkout.placetopay.ec',
                    Environment::TEST => 'https://checkout-test.placetopay.ec',
                    Environment::DEVELOPMENT => 'https://checkout-ec.placetopay.dev',
                ];

                break;
            case CountryCode::CHILE:
                $endpoints = [
                    Environment::PRODUCTION => unmaskString('uggcf://purpxbhg.trgarg.py'),
                    Environment::TEST => unmaskString('uggcf://purpxbhg.grfg.trgarg.py'),
                    Environment::DEVELOPMENT => 'https://checkout-cl.placetopay.dev',
                ];

                break;
            case CountryCode::PUERTO_RICO:
            case CountryCode::PANAMA:
            case CountryCode::MEXICO:
            case CountryCode::PERU:
            case CountryCode::COLOMBIA:
            case CountryCode::COSTA_RICA:
            default:
                $endpoints = [
                    Environment::PRODUCTION => 'https://checkout.placetopay.com',
                    Environment::TEST => 'https://checkout-test.placetopay.com',
                    Environment::DEVELOPMENT => 'https://checkout-co.placetopay.dev',
                ];
                break;
        }

        return $endpoints;
    }
}
