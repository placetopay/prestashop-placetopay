<?php

// @see https://devdocs.prestashop-project.org/8/modules/concepts/controllers/front-controllers/
class PlacetoPayPaymentSondaModuleFrontController extends ModuleFrontController
{
    public $auth = false;

    public function display()
    {
        if (!isConsole()) {
            Tools::redirect('index');
        }

        resolvePendingPaymentsPlacetoPay();
    }
}
