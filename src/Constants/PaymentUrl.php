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
    public static function getDefaultEndpoints()
    {
        return [
            Environment::PRODUCTION => 'https://checkout.placetopay.com',
            Environment::TEST => 'https://test.placetopay.com/redirection',
            Environment::DEVELOPMENT => 'https://dev.placetopay.com/redirection',
        ];
    }

    /**
     * @param string $countryCode Value of Constants\CountryCode
     * @return array
     */
    public static function getEndpointsTo($countryCode)
    {
        switch ($countryCode) {
            case CountryCode::ECUADOR:
                $endpoints = [
                    Environment::PRODUCTION => 'https://checkout.placetopay.ec',
                    Environment::TEST => 'https://test.placetopay.ec/redirection',
                    Environment::DEVELOPMENT => 'https://dev.placetopay.ec/redirection',
                ];
                break;
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
