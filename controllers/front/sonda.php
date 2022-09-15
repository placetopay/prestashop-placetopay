<?php

// @see https://devdocs.prestashop.com/1.7/development/architecture/legacy/legacy-controllers/
// @see https://devdocs.prestashop.com/1.7/modules/concepts/controllers/front-controllers/#using-a-front-controller-as-a-cron-task
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
