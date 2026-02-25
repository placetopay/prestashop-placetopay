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
            if (!Context::getContext()->customer->isLogged()
                && !Context::getContext()->customer->is_guest
                && empty(file_get_contents('php://input'))
            ) {
                PaymentLogger::log('Access not allowed', PaymentLogger::WARNING, 17, __FILE__, __LINE__);

                Tools::redirect(Context::getContext()->link->getPageLink('order', true, null, 'step=1'));
            }

            $reference = Tools::getValue('_');

            $this->module->process($reference ?: null);
        } catch (Throwable $e) {
            PaymentLogger::log($e->getMessage(), PaymentLogger::ERROR, 999, __FILE__, __LINE__);

            die($e->getMessage());
        }
    }
}
