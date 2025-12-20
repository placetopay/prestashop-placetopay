<?php

require_once 'helpers.php';

if (versionComparePlaceToPay('1.7.0.0', '<')) {
    if (is_file(__DIR__ . '/vendor/autoload.php')) {
        return require_once __DIR__ . '/vendor/autoload.php';
    }

    die('You need run: composer install, before to continue');
} else {
    /**
     * TODO IMPORTANT: This autoload was create because PS 1.7 has a composer GuzzleHttp INCOMPATIBLE.
     * This autoload load PlacetoPay and Dnetix class
     * @link http://forge.prestashop.com/browse/BOOM-2427
     */
    spl_autoload_register(function ($className) {
        switch (true) {
            case substr($className, 0, 10) === 'PlacetoPay':
                $src = __DIR__ . '/src';
                $class = str_replace('PlacetoPay\\', '', $className);
                break;
            case substr($className, 0, 6) === 'Dnetix':
                $src = __DIR__ . '/vendor/dnetix/redirection/src';

                if (is_dir($src)) {
                    $src = __DIR__ . '/vendor/alejociro/redirection/src';
                }

                $class = str_replace('Dnetix\\Redirection\\', '', $className);
                break;
            default:
                // Another class are ignore
                return true;
        }

        $filename = fixPath(sprintf('%s/%s.php', $src, $class));

        if (!file_exists($filename)) {
            throw new Exception(sprintf('File %s with class [%s] not found', $filename, $className));
        }

        return require_once $filename;
    });
}
