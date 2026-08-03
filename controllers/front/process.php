<?php

use PlacetoPay\Loggers\PaymentLogger;

class PlacetoPayPaymentProcessModuleFrontController extends ModuleFrontController
{
    public $auth = false;
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();

        try {
            $reference = Tools::getValue('_');
            $reference = is_string($reference) ? $reference : '';
            $customer = Context::getContext()->customer;
            $hasCustomerSession = $customer->isLogged() || $customer->is_guest;
            $isNotification = !$reference && !empty(file_get_contents('php://input'));

            if (!$hasCustomerSession && !$isNotification) {
                PaymentLogger::log('Access not allowed', PaymentLogger::WARNING, 17, __FILE__, __LINE__);

                Tools::redirect(Context::getContext()->link->getPageLink('order', true, null, 'step=1'));
            }

            $this->module->process($reference ?: null);
        } catch (Throwable $e) {
            PaymentLogger::log($e->getMessage(), PaymentLogger::ERROR, 999, __FILE__, __LINE__);

            die('An error occurred while processing the payment, contact the store administrator');
        }
    }
}
