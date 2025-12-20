<?php

namespace PlacetoPay;

use PlacetoPay\Constants\Environment;

abstract class CountryConfig
{
    public const CLIENT_ID = 'avalpay-colombia';
    public const CLIENT = 'AvalPay';
    public const IMAGE = 'https://placetopay-static-uat-bucket.s3.us-east-2.amazonaws.com/avalpaycenter-com/logos/Header+Correo+-+Logo+Avalpay.svg';
    public const COUNTRY_CODE = 'CO';
    public const COUNTRY_NAME = 'Colombia';

    public static function getEndpoints(): array
    {
        return [
            Environment::TEST => 'https://checkout.test.avalpaycenter.com',
            Environment::PRODUCTION => 'https://checkout.avalpaycenter.com',
        ];
    }
}

