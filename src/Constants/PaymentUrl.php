<?php

namespace PlacetoPay\Constants;

/**
 * Class PaymentUrl
 * @package PlacetoPay\Constants
 */
abstract class PaymentUrl
{
    /**
     * @return array
     */
    public static function getDefaultEndpoints(): array
    {
        return [
            Environment::PRODUCTION => 'https://checkout.placetopay.com',
            Environment::TEST => 'https://checkout-test.placetopay.com',
            Environment::DEVELOPMENT => 'https://checkout-co.placetopay.dev',
        ];
    }

    /**
     * @param string $countryCode Value of Constants\CountryCode
     * @return array
     */
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
                    Environment::PRODUCTION => 'https://checkout.getnet.cl',
                    Environment::TEST => 'https://checkout.uat.getnet.cl',
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
                $endpoints = self::getDefaultEndpoints();
                break;
        }

        return $endpoints;
    }
}
