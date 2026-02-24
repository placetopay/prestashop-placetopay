<?php

namespace PlacetoPay;

use PlacetoPay\Constants\Environment;

abstract class CountryConfig
{
    public const CLIENT_ID = 'placetopay-honduras';

    public const CLIENT = 'Placetopay';

    public const IMAGE = 'https://static.placetopay.com/placetopay-logo.svg';

    public const COUNTRY_CODE = 'HN';

    public const COUNTRY_NAME = 'Honduras';

    public static function getEndpoints(): array
    {
        return [
            Environment::PRODUCTION => 'https://pagoenlinea.bancatlan.hn',
        ];
    }
}

