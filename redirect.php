<?php
/**
 * Redirect to PlacetoPay
 */

use PlacetoPay\Loggers\PaymentLogger;

try {
    require_once 'helpers.php';

    $pathCMS = getPathCMS('redirect.php');

    require fixPath($pathCMS . '/config/config.inc.php');
    require fixPath($pathCMS . '/init.php');
    require fixPath(sprintf('%s/%2$s/%2$s.php', _PS_MODULE_DIR_, getModuleName()));

    if (!Context::getContext()->customer->isLogged() && !Context::getContext()->customer->is_guest) {
        PaymentLogger::log('Access not allowed to: ' . __FILE__, PaymentLogger::WARNING, 17);
        Tools::redirect('authentication.php?back=order.php');
    }

    (new PlacetoPayPayment())->redirect($cart);
} catch (Exception $e) {
    PaymentLogger::log($e->getMessage(), PaymentLogger::ERROR, 999);
    die($e->getMessage());
}
