<?php

namespace PlacetoPay;

use PlacetoPay\Constants\Environment;

abstract class CountryConfig
{
    public const CLIENT_ID = 'getnet-chile';
    public const CLIENT = 'Getnet';
    public const IMAGE = 'https://banco.santander.cl/uploads/000/015/050/0affa7e1-10e5-45ff-b0e3-7616aee7d686/original/getnet_logo.svg';
    public const COUNTRY_CODE = 'CL';
    public const COUNTRY_NAME = 'Chile';

    public static function getEndpoints(): array
    {
        return [
            Environment::TEST => 'https://checkout.test.getnet.cl',
            Environment::UAT => 'https://checkout.uat.getnet.cl',
            Environment::PRODUCTION => 'https://checkout.getnet.cl',
        ];
    }
}

