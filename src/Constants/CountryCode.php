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
    public const CHILE = 'CL';

    public const URUGUAY = 'UY';

    public const COLOMBIA = 'CO';
}
