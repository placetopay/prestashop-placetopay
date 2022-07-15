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

    $pathKernel =  _PS_ROOT_DIR_ . '/app/AppKernel.php';

    global $kernel;

    if(!$kernel && is_file($pathKernel)){
        // PS >= 1.7
        require_once $pathKernel;

        $kernel = new \AppKernel('prod', false);
        $kernel->boot();
    }

    if (isDebugEnable()) {
        PaymentLogger::log('Starting Resolve Pending Payments', PaymentLogger::DEBUG, 0, __FILE__, __LINE__);
    }

    (new PlacetoPayPayment())->resolvePendingPayments();

    if (isDebugEnable()) {
        PaymentLogger::log('Finished Resolve Pending Payments', PaymentLogger::DEBUG, 0, __FILE__, __LINE__);
    }
} catch (Exception $e) {
    PaymentLogger::log($e->getMessage(), PaymentLogger::ERROR, 999, __FILE__, __LINE__);

    die($e->getMessage());
}
