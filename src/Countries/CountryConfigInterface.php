<?php

namespace PlacetoPay\Countries;

interface CountryConfigInterface
{
    public static function resolve(string $countryCode): bool;
    public static function getEndpoints(): array;
    public static function getClient(): array;

}
