<?php
/**
 * Payments pending
 */

use PlacetoPay\Loggers\PaymentLogger;

try {
    require_once 'helpers.php';

    $pathCMS = getPathCMS('sonda.php');

    require fixPath($pathCMS . '/config/config.inc.php');
    require fixPath(sprintf('%s/%2$s/%2$s.php', _PS_MODULE_DIR_, getModuleName()));

    global $kernel;

    if(!$kernel){
        require_once _PS_ROOT_DIR_.'/app/AppKernel.php';
        $kernel = new \AppKernel('prod', false);
        $kernel->boot();
    }

    (new PlacetoPayPayment())->resolvePendingPayments();
} catch (Exception $e) {
    PaymentLogger::log($e->getMessage(), PaymentLogger::ERROR, 999, __FILE__, __LINE__);
    die($e->getMessage());
}
