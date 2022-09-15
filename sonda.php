<?php
/**
 * Pending payments
 */

use PlacetoPay\Loggers\PaymentLogger;

require_once 'helpers.php';

$_SERVER['REQUEST_METHOD'] = 'POST';

$_GET['fc'] = 'module';
$_GET['module'] = getModuleName();
$_GET['controller'] = 'sonda';

require_once dirname(__FILE__) . '/../../index.php';

function resolvePendingPaymentsPlacetoPay() {
    try {
        if (isDebugEnable()) {
            PaymentLogger::log('Starting Resolve Pending Payments', PaymentLogger::DEBUG, 0, __FILE__, __LINE__);
        }

        (new PlacetoPayPayment())->resolvePendingPayments();

        if (isDebugEnable()) {
            PaymentLogger::log('Finished Resolve Pending Payments', PaymentLogger::DEBUG, 0, __FILE__, __LINE__);
        }
    } catch (Throwable $exception) {
        PaymentLogger::log($exception->getMessage(), PaymentLogger::ERROR, 999, __FILE__, __LINE__);

        die($exception->getMessage());
    }
}
