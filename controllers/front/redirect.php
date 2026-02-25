<?php

use PlacetoPay\Loggers\PaymentLogger;

class PlacetoPayPaymentRedirectModuleFrontController extends ModuleFrontController
{
    public $auth = false;
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();

        try {
            if (!Context::getContext()->customer->isLogged() && !Context::getContext()->customer->is_guest) {
                PaymentLogger::log('Access not allowed', PaymentLogger::WARNING, 17, __FILE__, __LINE__);

                Tools::redirect(Context::getContext()->link->getPageLink('order', true, null, 'step=1'));
            }

            $cart = Context::getContext()->cart;

            if (!Validate::isLoadedObject($cart)) {
                PaymentLogger::log('Cart not found', PaymentLogger::ERROR, 18, __FILE__, __LINE__);

                Tools::redirect(Context::getContext()->link->getPageLink('order', true, null, 'step=1'));
            }

            $this->module->redirect($cart);
        } catch (Throwable $e) {
            PaymentLogger::log($e->getMessage(), PaymentLogger::ERROR, 999, __FILE__, __LINE__);

            die($e->getMessage());
        }
    }
}
