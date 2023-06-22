<?php

namespace PlacetoPay\Constants;

use PlacetoPay\Countries\ChileCountryConfig;
use PlacetoPay\Countries\ColombiaCountryConfig;
use PlacetoPay\Countries\CountryConfig;

/**
 * @see https://en.wikipedia.org/wiki/List_of_ISO_3166_country_codes
 */
interface CountryCode
{
    public const BELIZE = 'BZ';

    public const CHILE = 'CL';

    public const COLOMBIA = 'CO';

    public const COSTA_RICA = 'CR';

    public const ECUADOR = 'EC';

    public const HONDURAS = 'HN';

    public const PANAMA = 'PA';

    public const PUERTO_RICO = 'PR';

    public const URUGUAY = 'UY';

    public const COUNTRIES_CLIENT = [
        ChileCountryConfig::class,
        ColombiaCountryConfig::class,
        CountryConfig::class
    ];
}
