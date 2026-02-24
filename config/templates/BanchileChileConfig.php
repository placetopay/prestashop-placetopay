<?php

namespace PlacetoPay;

use PlacetoPay\Constants\Environment;

abstract class CountryConfig
{
    public const CLIENT_ID = 'banchile-chile';

    public const CLIENT = 'Banchile Pagos';

    public const IMAGE = 'https://placetopay-static-prod-bucket.s3.us-east-2.amazonaws.com/banchile/logos/Logotipo_superior.png';

    public const COUNTRY_CODE = 'CL';

    public const COUNTRY_NAME = 'Chile';

    public static function getEndpoints(): array
    {
        return [
            Environment::TEST => 'https://checkout.test.banchilepagos.cl',
            Environment::UAT => 'https://checkout.uat.banchilepagos.cl',
            Environment::PRODUCTION => 'https://checkout.banchilepagos.cl',
        ];
    }
}
