<?php

namespace PlacetoPay\Models;

use Address;
use Cart;
use Configuration;
use Context;
use Country;
use Currency;
use Customer;
use Db;
use Dnetix\Redirection\Entities\PaymentModifier;
use Dnetix\Redirection\Entities\Status;
use Dnetix\Redirection\Message\Notification;
use Dnetix\Redirection\Message\RedirectInformation;
use Dnetix\Redirection\PlacetoPay;
use Exception;
use HelperForm;
use Language;
use Order;
use OrderHistory;
use OrderState;
use PaymentModule;
use PlacetoPay\Constants\Client;
use PlacetoPay\Constants\CountryCode;
use PlacetoPay\Constants\Discount;
use PlacetoPay\Constants\Environment;
use PlacetoPay\Constants\PaymentStatus;
use PlacetoPay\Constants\PaymentUrl;
use PlacetoPay\Countries\CountryConfigInterface;
use PlacetoPay\Exceptions\PaymentException;
use PlacetoPay\Loggers\PaymentLogger;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use PrestaShopDatabaseException;
use Shop;
use State;
use Tools;
use Validate;

/**
 * Class PlaceToPayPaymentMethod
 * @property string currentOrderReference
 * @property string name
 * @property string version
 * @property bool active
 * @property int id
 * @property mixed context
 * @property mixed currentOrder
 * @property int identifier
 * @property string table
 * @property mixed smarty
 * @property string warning
 * @property string confirmUninstall
 * @property string description
 * @property string displayName
 * @property bool bootstrap
 * @property string currencies_mode
 * @property bool currencies
 * @property int is_eu_compatible
 * @property array controllers
 * @property array ps_versions_compliancy
 * @property array limited_countries
 * @property string tab
 * @property string author
 *
 * @see https://devdocs.prestashop.com/1.7/modules/creation/tutorial/
 */
class PlacetoPayPayment extends PaymentModule
{
    const COMPANY_DOCUMENT = 'PLACETOPAY_COMPANYDOCUMENT';
    const COMPANY_NAME = 'PLACETOPAY_COMPANYNAME';
    const EMAIL_CONTACT = 'PLACETOPAY_EMAILCONTACT';
    const TELEPHONE_CONTACT = 'PLACETOPAY_TELEPHONECONTACT';
    const DESCRIPTION = 'PLACETOPAY_DESCRIPTION';
    const EXPIRATION_TIME_MINUTES = 'PLACETOPAY_EXPIRATION_TIME_MINUTES';
    const SHOW_ON_RETURN = 'PLACETOPAY_SHOWONRETURN';
    const CIFIN_MESSAGE = 'PLACETOPAY_CIFINMESSAGE';
    const ALLOW_BUY_WITH_PENDING_PAYMENTS = 'PLACETOPAY_ALLOWBUYWITHPENDINGPAYMENTS';
    const FILL_TAX_INFORMATION = 'PLACETOPAY_FILL_TAX_INFORMATION';
    const FILL_BUYER_INFORMATION = 'PLACETOPAY_FILL_BUYER_INFORMATION';
    const SKIP_RESULT = 'PLACETOPAY_SKIP_RESULT';
    const CLIENT = 'PLACETOPAY_CLIENT';
    const DISCOUNT = 'PLACETOPAY_DISCOUNT';
    const INVOICE = 'PLACETOPAY_INVOICE';
    const ENVIRONMENT = 'PLACETOPAY_ENVIRONMENT';
    const CUSTOM_CONNECTION_URL = 'PLACETOPAY_CUSTOM_CONNECTION_URL';
    const PAYMENT_BUTTON_IMAGE = 'PLACETOPAY_PAYMENT_BUTTON_IMAGE';
    const LOGIN = 'PLACETOPAY_LOGIN';
    const TRAN_KEY = 'PLACETOPAY_TRANKEY';
    const EXPIRATION_TIME_MINUTES_DEFAULT = 120; // 2 Hours
    const EXPIRATION_TIME_MINUTES_MIN = 10; // 10 Minutes

    const LIGHTBOX = 'PLACETOPAY_LIGHTBOX';

    const SHOW_ON_RETURN_DEFAULT = 'default';
    const SHOW_ON_RETURN_PSE_LIST = 'pse_list';
    const SHOW_ON_RETURN_DETAILS = 'details';
    const SHOW_ON_RETURN_HOME = 'home';
    const OPTION_ENABLED = '1';
    const OPTION_DISABLED = '0';
    const ORDER_STATE = 'PS_OS_PLACETOPAY';

    const PAGE_ORDER_CONFIRMATION = 'order-confirmation.php';
    const PAGE_ORDER_HISTORY = 'history.php';
    const PAGE_ORDER_DETAILS = 'index.php?controller=order-detail';
    const PAGE_HOME = '';

    const MIN_VERSION_PS = '1.6.1.0';
    const MAX_VERSION_PS = '8.1.2';

    /**
     * @var string
     */
    private $tablePayment = _DB_PREFIX_ . 'payment_placetopay';

    /**
     * @var string
     */
    private $tableOrder = _DB_PREFIX_ . 'orders';

    /**
     * @var string
     */
    private $tableLanguage = _DB_PREFIX_ . 'order_state_lang';

    public function __construct()
    {
        $this->name = getModuleName();
        $this->version = '4.0.8';

        $this->tab = 'payments_gateways';

        $this->limited_countries = [];

        $this->ps_versions_compliancy = [
            'min' => self::MIN_VERSION_PS,
            'max' => _PS_VERSION_,
        ];

        $this->controllers = ['validation'];
        $this->is_eu_compatible = 1;

        $modulePath = _PS_MODULE_DIR_ . $this->name . '/';

        $currentLogoPath = $modulePath . 'logo.png';
        $newLogoPath = $modulePath . 'logos/' . $this->getClient() . '.png';

        if (file_exists($newLogoPath) && (!file_exists($currentLogoPath) || md5_file($currentLogoPath) !== md5_file($newLogoPath))) {
            copy($newLogoPath, $currentLogoPath);
        }

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;

        parent::__construct();

        $this->author = $this->getClient() === unmaskString(Client::PTP)
            ? $this->ll('Evertec PlacetoPay S.A.S.') : $this->getClient();
        $this->displayName = $this->getClient();
        $this->description = $this->ll('Accept payments by credit cards and debits account.');

        $this->confirmUninstall = $this->ll('Are you sure you want to uninstall?');

        if (!$this->isCompliancy()) {
            $this->description .= '<br>'
                . '<span style="font-style: italic;" class="text-info">'
                . $this->getCompliancyMessage()
                . '</span>';

            $this->warning .= '<br> - ' . $this->getCompliancyMessage();
        }

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning .= '<br> - ' . $this->ll('No currency has been set for this module.');
        }

        if (!$this->isSetCredentials()) {
            $this->warning .= '<br> - '
                . $this->lll('You need to configure your %s account before using this module.');
        }

        @date_default_timezone_set(Configuration::get('PS_TIMEZONE'));
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        $error = '';

        if (!$this->tableExists($this->tablePayment) && !$this->createPaymentTable()) {
            $error = 'Error creating payment table';
        }

        if (!Configuration::get(self::ORDER_STATE) && !$this->createOrderState()) {
            $error = 'Error creating order state';
        }

        if (!$this->columnExists($this->tablePayment, 'ip_address') && !$this->alterColumnIpAddress()) {
            $error = 'Error altering ip_address column';
        }

        if (!$this->columnExists($this->tablePayment, 'payer_email') && !$this->addColumnEmail()) {
            $error = 'Error adding payer_email column';
        }

        if (!$this->columnExists($this->tablePayment, 'id_request') && !$this->addColumnRequestId()) {
            $error = 'Error adding id_request column';
        }

        if (!$this->columnExists($this->tablePayment, 'reference') && !$this->addColumnReference()) {
            $error = 'Error adding reference column';
        }

        if (!$this->columnExists($this->tablePayment, 'installments') && !$this->addInstallmentLastDigitsColumns()) {
            $error = 'Error adding installments column';
        }

        if (empty($error) && !$this->registerHook('paymentReturn')) {
            $error = 'Error registering paymentReturn hook';
        }

        $hookPaymentName = versionComparePlaceToPay('1.7.0.0', '>=') ? 'paymentOptions' : 'payment';

        if (empty($error) && !$this->registerHook($hookPaymentName)) {
            $error = sprintf('Error on install registering %s hook', $hookPaymentName);
        }

        $hookOrderName = versionComparePlaceToPay('1.7.0.0', '>=') ? 'displayAdminOrderMainBottom' : 'displayAdminOrderLeft';

        if (empty($error) && !$this->registerHook($hookOrderName)) {
            $error = sprintf('Error on install registering %s hook', $hookOrderName);
        }

        if (empty($error)) {
            $this->setDefaultConfigurations();
            return true;
        }

        PaymentLogger::log($error, PaymentLogger::ERROR, 100, __FILE__, __LINE__);
        return false;
    }

    private function setDefaultConfigurations()
    {
        Configuration::updateValue(self::COMPANY_DOCUMENT, '');
        Configuration::updateValue(self::COMPANY_NAME, '');
        Configuration::updateValue(self::EMAIL_CONTACT, '');
        Configuration::updateValue(self::TELEPHONE_CONTACT, '');

        Configuration::updateValue(self::EXPIRATION_TIME_MINUTES, self::EXPIRATION_TIME_MINUTES_DEFAULT);
        Configuration::updateValue(self::SHOW_ON_RETURN, self::SHOW_ON_RETURN_PSE_LIST);
        Configuration::updateValue(self::CIFIN_MESSAGE, self::OPTION_DISABLED);
        Configuration::updateValue(self::ALLOW_BUY_WITH_PENDING_PAYMENTS, self::OPTION_ENABLED);
        Configuration::updateValue(self::FILL_TAX_INFORMATION, self::OPTION_ENABLED);
        Configuration::updateValue(self::FILL_BUYER_INFORMATION, self::OPTION_ENABLED);
        Configuration::updateValue(self::SKIP_RESULT, self::OPTION_DISABLED);
        Configuration::updateValue(self::DISCOUNT, Discount::UY_NONE);
        Configuration::updateValue(self::INVOICE, '');

        Configuration::updateValue(self::CLIENT, $this->getClient());
        Configuration::updateValue(self::ENVIRONMENT, Environment::TEST);
        Configuration::updateValue(self::CUSTOM_CONNECTION_URL, '');
        Configuration::updateValue(self::LOGIN, '');
        Configuration::updateValue(self::TRAN_KEY, '');
        Configuration::updateValue(self::PAYMENT_BUTTON_IMAGE, '');
        Configuration::updateValue(self::LIGHTBOX, self::OPTION_DISABLED);
    }

    private function tableExists($tableName)
    {
        $sql = "SHOW TABLES LIKE '$tableName'";
        return Db::getInstance()->executeS($sql);
    }

    private function columnExists($tableName, $columnName)
    {
        $sql = "SHOW COLUMNS FROM `$tableName` LIKE '$columnName'";
        return Db::getInstance()->executeS($sql);
    }

    public function uninstall()
    {
        $tableExists = $this->tableExists($this->tablePayment);
        if ($tableExists) {
            $sqlCheckRecords = "SELECT COUNT(*) FROM `{$this->tablePayment}`";
            $recordsCount = (int)Db::getInstance()->getValue($sqlCheckRecords);

            if ($recordsCount === 0) {
                $sqlDropTable = "DROP TABLE IF EXISTS `{$this->tablePayment}`;";
                Db::getInstance()->execute($sqlDropTable);
            }
        }

        $orderState = new OrderState((int)Configuration::get(self::ORDER_STATE));
        if (Validate::isLoadedObject($orderState)) {
            $orderState->delete();
        }

        $configurations = [
            self::COMPANY_DOCUMENT,
            self::COMPANY_NAME,
            self::EMAIL_CONTACT,
            self::TELEPHONE_CONTACT,
            self::EXPIRATION_TIME_MINUTES,
            self::SHOW_ON_RETURN,
            self::CIFIN_MESSAGE,
            self::ALLOW_BUY_WITH_PENDING_PAYMENTS,
            self::FILL_TAX_INFORMATION,
            self::FILL_BUYER_INFORMATION,
            self::SKIP_RESULT,
            self::DISCOUNT,
            self::INVOICE,
            self::CLIENT,
            self::ENVIRONMENT,
            self::CUSTOM_CONNECTION_URL,
            self::LOGIN,
            self::TRAN_KEY,
            self::PAYMENT_BUTTON_IMAGE,
            self::LIGHTBOX,
            self::ORDER_STATE,
        ];

        foreach ($configurations as $config) {
            Configuration::deleteByName($config);
        }

        if (!parent::uninstall()) {
            return false;
        }

        return true;
    }

    /**
     * Show and save configuration page
     * @return string
     */
    public function getContent()
    {
        $contentExtra = '';

        if (Tools::isSubmit('submitPlacetoPayConfiguration')) {
            $formErrors = $this->formValidation();

            if (count($formErrors) == 0) {
                $this->formProcess();

                $contentExtra = $this->displayConfirmation($this->lll('%s settings updated'));
            } else {
                $contentExtra = $this->showError($formErrors);
            }
        }

        return $contentExtra . $this->displayConfiguration() . $this->renderForm();
    }

    /**
     * PrestaShop 1.6
     *
     * @param array $params
     * @return string
     * @throws PaymentException
     */
    public function hookPayment($params)
    {
        if (isDebugEnable()) {
            PaymentLogger::log(
                'Trigger ' . __METHOD__ . ' in PS vr. ' . _PS_VERSION_,
                PaymentLogger::DEBUG,
                0,
                __FILE__,
                __LINE__
            );
        }

        if (!$this->active) {
            return null;
        }

        if (!$this->isSetCredentials()) {
            PaymentLogger::log(
                $this->lll('You need to configure your %s account before using this module.'),
                PaymentLogger::WARNING,
                6,
                __FILE__,
                __LINE__
            );

            return null;
        }

        $lastPendingTransaction = $this->getLastPendingTransaction($params['cart']->id_customer);

        if (!empty($lastPendingTransaction)) {
            $hasPendingTransaction = true;

            $this->context->smarty->assign([
                'last_order' => $lastPendingTransaction['reference'],
                'last_authorization' => (string)$lastPendingTransaction['authcode'],
                'store_email' => $this->getEmailContact(),
                'store_phone' => $this->getTelephoneContact()
            ]);

            $paymentUrl = $this->getAllowBuyWithPendingPayments() == self::OPTION_ENABLED
                ? $this->getUrl('redirect.php')
                : 'javascript:;';

            $this->context->smarty->assign('payment_url', $paymentUrl);
        } else {
            $hasPendingTransaction = false;

            $this->context->smarty->assign('payment_url', $this->getUrl('redirect.php'));
        }

        $allowPayment = $this->getAllowBuyWithPendingPayments() == self::OPTION_ENABLED || !$hasPendingTransaction;

        $this->context->smarty->assign('has_pending', $hasPendingTransaction);
        $this->context->smarty->assign('site_name', Configuration::get('PS_SHOP_NAME'));
        $this->context->smarty->assign('cifin_message', $this->getTransUnionMessage());
        $this->context->smarty->assign('company_name', $this->getCompanyName());
        $this->context->smarty->assign('allow_payment', $allowPayment);
        $this->context->smarty->assign('url', $this->getImage());

        return $this->display($this->getThisModulePath(), fixPath('/views/templates/hook_1_6/payment.tpl'));
    }

    /**
     * PrestaShop 1.7
     * @param $params
     * @return array
     * @throws PaymentException
     */
    public function hookPaymentOptions($params)
    {
        if (isDebugEnable()) {
            PaymentLogger::log(
                'Trigger ' . __METHOD__ . ' in PS vr. ' . _PS_VERSION_,
                PaymentLogger::DEBUG,
                0,
                __FILE__,
                __LINE__
            );
        }

        if (!$this->active) {
            return null;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return null;
        }

        if (!$this->isSetCredentials()) {
            PaymentLogger::log(
                $this->lll('You need to configure your %s account before using this module.'),
                PaymentLogger::WARNING,
                6,
                __FILE__,
                __LINE__
            );

            return null;
        }

        $this->createOrderState();

        $content = $this->displayBrandMessage();
        $action = $this->getUrl('redirect.php');
        $lastPendingTransaction = $this->getLastPendingTransaction($params['cart']->id_customer);

        if (!empty($lastPendingTransaction)) {
            $content .= $this->displayPendingPaymentMessage($lastPendingTransaction);
            $action = $this->getAllowBuyWithPendingPayments() == self::OPTION_ENABLED
                ? $this->getUrl('redirect.php')
                : null;
        }

        if ($action && $this->getTransUnionMessage() == self::OPTION_ENABLED) {
            $content .= $this->displayTransUnionMessage();
        }

        $form = $this->generateForm($action, $content);

        $newOption = new PaymentOption();

        $newOption->setCallToActionText($this->lll('Pay by %s'))
            ->setLogo($this->getImage())
            ->setAdditionalInformation('')
            ->setForm($form);

        return [$newOption];
    }

    /**
     * Show information response payment to customer
     *
     * @param $params
     * @return bool|mixed
     * @throws PaymentException
     */
    public function hookPaymentReturn($params)
    {
        if (isDebugEnable()) {
            PaymentLogger::log(
                'Trigger ' . __METHOD__ . ' in PS vr. ' . _PS_VERSION_,
                PaymentLogger::DEBUG,
                0,
                __FILE__,
                __LINE__
            );
        }

        if (!$this->active) {
            return null;
        }

        $order = $params['objOrder'] ?? $params['order'];

        if ($order->module != getModuleName()) {
            return null;
        }

        switch ($this->getShowOnReturn()) {
            case self::SHOW_ON_RETURN_PSE_LIST:
                $viewOnReturn = $this->getPaymentPSEList($order->id_customer);
                break;
            case self::SHOW_ON_RETURN_DETAILS:
            case self::SHOW_ON_RETURN_DEFAULT:
            default:
                $viewOnReturn = $this->getPaymentDetails($order);
                break;
        }

        return $viewOnReturn;
    }

    /**
     * @throws PaymentException
     */
    public function redirect(Cart $cart): void
    {
        if (empty($cart->id)) {
            $message = 'Cart cannot be loaded or an order has already been placed using this cart';
            PaymentLogger::log($message, PaymentLogger::ERROR, 18, __FILE__, __LINE__);
            Tools::redirect('authentication.php?back=order.php');
        }

        $lastPendingTransaction = $this->getLastPendingTransaction($cart->id_customer);

        if (!empty($lastPendingTransaction) && $this->getAllowBuyWithPendingPayments() == self::OPTION_DISABLED) {
            // @codingStandardsIgnoreLine
            $message = 'Payment not allowed, customer has payment pending and not allowed but with payment pending is disable';
            PaymentLogger::log($message, PaymentLogger::ERROR, 7, __FILE__, __LINE__);
            Tools::redirect('authentication.php?back=order.php');
        }

        $customer = new Customer($cart->id_customer);
        $currency = new Currency($cart->id_currency);
        $invoiceAddress = new Address($cart->id_address_invoice);
        $deliveryAddress = new Address($cart->id_address_delivery);
        $totalAmount = (float)$cart->getOrderTotal(true);
        $totalAmountWithoutTaxes = (float)$cart->getOrderTotal(false);

        $taxAmount = $totalAmount - $totalAmountWithoutTaxes;

        if (!Validate::isLoadedObject($customer)) {
            throw new PaymentException('Invalid customer', 301);
        }

        if (!Validate::isLoadedObject($invoiceAddress)
            || !Validate::isLoadedObject($deliveryAddress)) {
            throw new PaymentException('Invalid address', 302);
        }

        if (!Validate::isLoadedObject($currency)) {
            throw new PaymentException('Invalid currency', 303);
        }

        $deliveryCountry = new Country((int)($deliveryAddress->id_country));
        $deliveryState = null;
        if ($deliveryAddress->id_state) {
            $deliveryState = new State((int)($deliveryAddress->id_state));
        }

        $urlOrderStatus = __PS_BASE_URI__
            . $this->getRedirectPageFromStatus(PaymentStatus::PENDING)
            . '?id_cart=' . $cart->id
            . '&id_module=' . $this->id
            . '&id_order=' . $this->currentOrder;

        try {
            $orderMessage = 'Success';
            $orderStatus = $this->getOrderState();
            $requestId = 0;
            $expiration = date('c', strtotime($this->getExpirationTimeMinutes() . ' minutes'));
            $ipAddress = (new RemoteAddress())->getIpAddress();

            // Create order in PrestaShop
            $this->validateOrder(
                $cart->id,
                $orderStatus,
                $totalAmount,
                $this->displayName,
                $orderMessage,
                null,
                null,
                false,
                $cart->secure_key
            );

            // After order create in validateOrder
            $reference = $this->currentOrderReference;
            $returnUrl = $this->getUrl('process.php', '?_=' . $this->reference($reference));

            // Request payment
            $request = [
                'locale' => $this->getLocale($cart),
                'returnUrl' => $returnUrl,
                'noBuyerFill' => !(bool)$this->getFillBuyerInformation(),
                'skipResult' => (bool)$this->getSkipResult(),
                'ipAddress' => $ipAddress,
                'expiration' => $expiration,
                'userAgent' => $_SERVER['HTTP_USER_AGENT'],
                'buyer' => [
                    'name' => $deliveryAddress->firstname,
                    'surname' => $deliveryAddress->lastname,
                    'email' => $customer->email,
                    'mobile' => (!empty($deliveryAddress->phone_mobile)
                        ? $deliveryAddress->phone_mobile
                        : $deliveryAddress->phone),
                    'address' => [
                        'country' => $deliveryCountry->iso_code,
                        'state' => (empty($deliveryState) ? null : $deliveryState->name),
                        'city' => $deliveryAddress->city,
                        'street' => $deliveryAddress->address1 . " " . $deliveryAddress->address2,
                    ]
                ],
                'payment' => [
                    'reference' => $reference,
                    'description' => sprintf($this->getDescription(), $reference),
                    'amount' => [
                        'currency' => $currency->iso_code,
                        'total' => $totalAmount,
                    ]
                ]
            ];

            if ($this->getDefaultPrestashopCountry() === CountryCode::URUGUAY) {
                $discountCode = $this->getDiscount();

                if ($discountCode != Discount::UY_NONE) {
                    $request['payment']['modifiers'] = [
                        new PaymentModifier([
                            'type' => PaymentModifier::TYPE_FEDERAL_GOVERNMENT,
                            'code' => $discountCode,
                            'additional' => [
                                'invoice' => $this->getInvoice()
                            ]
                        ])
                    ];
                }
            }

            if ($this->getFillTaxInformation() == self::OPTION_ENABLED && $taxAmount > 0) {
                // Add taxes
                $request['payment']['amount']['taxes'] = [
                    [
                        'kind' => 'valueAddedTax',
                        'amount' => $taxAmount,
                        'base' => $totalAmountWithoutTaxes,
                    ]
                ];
            }

            if (isDebugEnable()) {
                PaymentLogger::log('URI: ' . $this->getUri(), PaymentLogger::DEBUG, 0, __FILE__, __LINE__);
                PaymentLogger::log(print_r($request, true), PaymentLogger::DEBUG, 0, __FILE__, __LINE__);
            }

            $paymentRedirection = $this->instanceRedirection()->request($request);

            if (isDebugEnable()) {
                PaymentLogger::log(print_r($paymentRedirection, true), PaymentLogger::DEBUG, 0, __FILE__, __LINE__);
            }

            $orderMessage = $paymentRedirection->status()->message();

            if ($paymentRedirection->isSuccessful()) {
                $requestId = $paymentRedirection->requestId();
                $status = PaymentStatus::PENDING;
                // Redirect to payment:
                $redirectTo = $paymentRedirection->processUrl();
            } else {
                $totalAmount = 0;
                $status = PaymentStatus::FAILED;
                // Redirect to error:
                $redirectTo = $urlOrderStatus;

                $this->updateCurrentOrderWithError();

                PaymentLogger::log($orderMessage, PaymentLogger::WARNING, 0, __FILE__, __LINE__);
            }

            // Register payment request
            $this->insertPaymentPlaceToPay(
                $requestId,
                $cart->id,
                $cart->id_currency,
                $totalAmount,
                $status,
                $orderMessage,
                $ipAddress,
                $reference
            );

            if (isDebugEnable()) {
                $message = sprintf('[%d => %s] Redirecting flow to: %s', $status, $orderMessage, $redirectTo);
                PaymentLogger::log($message, PaymentLogger::DEBUG, 0, __FILE__, __LINE__);
            }

            if ($this->getLightbox()) {
                echo $this->display($this->getThisModulePath(), fixPath('/views/templates/front/redirect.tpl'));
                echo $this->resolveLightbox($redirectTo, $returnUrl);
            } else {
                Tools::redirectLink($redirectTo);
            }
        } catch (\Throwable $e) {
            $this->updateCurrentOrderWithError();

            PaymentLogger::log($e->getMessage(), PaymentLogger::WARNING, 8, $e->getFile(), $e->getLine());

            Tools::redirect($urlOrderStatus);
        }
    }

    private function resolveLightbox(string $processUrl, string $returnUrl)
    {
        return "
         <script src='https://checkout.placetopay.com/lightbox.min.js'></script>
         <script>
            P.init('" . $processUrl . "', { opacity: 0.4 });
            P.on('response', function() {
                window.location = '" . $returnUrl . "';
            });
         </script>
        ";
    }

    /**
     * @throws PaymentException
     * @throws \Dnetix\Redirection\Exceptions\PlacetoPayException
     */
    public function process(?string $_reference = null): void
    {
        $paymentPlaceToPay = [];

        if (!is_null($_reference)) {
            // On returnUrl from redirection process
            $reference = $this->reference($_reference, true);
            $paymentPlaceToPay = $this->getPaymentPlaceToPayBy('reference', $reference);
        } elseif (!empty($inputStream = Tools::file_get_contents("php://input"))) {
            // On resolve function called process
            sleep(5);
            $input = json_decode($inputStream, 1);

            $notification = new Notification((array)$input, $this->getTranKey());

            if (!$notification->isValidNotification()) {
                if (isDebugEnable()) {
                    die('Change signature value in your request to: ' . $notification->makeSignature());
                }

                $message = 'Notification is not valid, process canceled. Input request:' . PHP_EOL . print_r($input, true);

                throw new PaymentException($message, 501);
            }

            $requestId = (int)$input['requestId'];
            $paymentPlaceToPay = $this->getPaymentPlaceToPayBy('id_request', $requestId);
        }

        if (empty($paymentPlaceToPay)) {
            $error = 9;
            $message = sprintf('Payment _reference: [%s] not found', $_reference);

            if (isset($reference)) {
                $error = 10;
                $message = sprintf('Payment with reference: [%s] not found', $reference);
            } elseif (isset($requestId)) {
                $error = 11;
                $message = sprintf('Payment with id_request: [%s] not found', $requestId);
            }

            PaymentLogger::log($message, PaymentLogger::WARNING, $error, __FILE__, __LINE__);

            if (!empty($input)) {
                // Show status to reference in console
                die($message . PHP_EOL);
            }

            Tools::redirect('authentication.php?back=order.php');
        }

        $paymentId = $paymentPlaceToPay['id_payment'];
        $cartId = $paymentPlaceToPay['id_order'];
        $requestId = $paymentPlaceToPay['id_request'];
        $reference = $paymentPlaceToPay['reference'];
        $oldStatus = $paymentPlaceToPay['status'];
        $order = $this->getOrderByCartId($cartId);

        if (isDebugEnable()) {
            PaymentLogger::log(json_encode($paymentPlaceToPay), PaymentLogger::DEBUG, 0, __FILE__, __LINE__);
        }

        if ($oldStatus != PaymentStatus::PENDING) {
            $message = sprintf(
                'Payment with reference: [%s] not is pending, current status is [%d=%s]',
                $reference,
                $oldStatus,
                implode('->', $this->getStatusDescription($oldStatus))
            );

            PaymentLogger::log($message, PaymentLogger::WARNING, 12, __FILE__, __LINE__);

            if (!empty($input)) {
                die($message . PHP_EOL);
            }

            Tools::redirect($this->resolveRedirectUrl($cartId, $order));
        }

        $paymentRedirection = $this->instanceRedirection()->query($requestId);

        if (isDebugEnable()) {
            PaymentLogger::log(print_r($paymentRedirection, true), PaymentLogger::DEBUG, 0, __FILE__, __LINE__);
        }

        if ($paymentRedirection->isSuccessful() && $order) {
            $newStatus = $this->getStatusPayment($paymentRedirection);

            if (isDebugEnable()) {
                $message = sprintf(
                    'Updating status to payment with reference: [%s] from [%d=%s] to [%d=%s]',
                    $order->reference,
                    $oldStatus,
                    implode('->', $this->getStatusDescription($oldStatus)),
                    $newStatus,
                    implode('->', $this->getStatusDescription($newStatus))
                );

                PaymentLogger::log($message, PaymentLogger::DEBUG, 0, __FILE__, __LINE__);
            }

            // Set status order in PrestaShop
            $this->settleTransaction($paymentId, $newStatus, $order, $paymentRedirection);

            if (!empty($input)) {
                // Show status to reference in console
                die(sprintf(
                    'Payment with reference: [%s] status change from [%d=%s] to [%d=%s]',
                    $order->reference,
                    $oldStatus,
                    implode('->', $this->getStatusDescription($oldStatus)),
                    $newStatus,
                    implode('->', $this->getStatusDescription($newStatus))
                ));
            }

            $redirectTo = $this->resolveRedirectUrl($cartId, $order);

            if (isDebugEnable()) {
                $message = sprintf('Redirecting flow to: [%s]', $redirectTo);
                PaymentLogger::log($message, PaymentLogger::DEBUG, 0, __FILE__, __LINE__);
            }

            // Redirect to confirmation page
            Tools::redirectLink($redirectTo);
        } elseif (!$paymentRedirection->isSuccessful()) {
            throw new PaymentException($paymentRedirection->status()->message(), 13);
        } elseif (!$order) {
            throw new PaymentException('Order not found: ' . $cartId, 14);
        } else {
            throw new PaymentException('Un-know error in process payment', 99);
        }
    }

    public function resolveRedirectUrl($cartId, $order): string
    {
        return __PS_BASE_URI__
            . $this->getRedirectPageFromStatus()
            . '?id_cart=' . $cartId
            . '&id_module=' . $this->id
            . '&id_order=' . $order->id
            . '&key=' . $order->secure_key;
    }

    public function resolvePendingPayments(): void
    {
        if ($this->isEnableShowSetup()) {
            echo $this->getSetup();
        }

        if (!isConsole() && !isDebugEnable()) {
            $message = sprintf(
                'Only from CLI (used SAPI: %s) is available execute this command: %s, aborted',
                php_sapi_name(),
                __FUNCTION__
            );

            PaymentLogger::log($message, PaymentLogger::WARNING, 16, __FILE__, __LINE__);

            Tools::redirect('authentication.php?back=order.php');
        }

        echo 'Begins ' . date('Ymd H:i:s') . '.' . breakLine();

        $sql = "SELECT *
            FROM `{$this->tablePayment}`
            WHERE `status` = " . PaymentStatus::PENDING;

        if (isDebugEnable()) {
            PaymentLogger::log($sql, PaymentLogger::DEBUG, 0, __FILE__, __LINE__);
        }

        try {
            if ($result = Db::getInstance()->ExecuteS($sql)) {
                echo "Found (" . count($result) . ") payments pending." . breakLine(2);

                $paymentRedirection = $this->instanceRedirection();

                foreach ($result as $row) {
                    $reference = $row['reference'];
                    $requestId = (int)$row['id_request'];
                    $paymentId = (int)$row['id_payment'];
                    $cartId = (int)$row['id_order'];

                    echo "Processing order: [{$cartId}] with reference: [{$reference}] "
                        . " (Request ID: {$requestId})." . breakLine();

                    if (!$requestId) {
                        echo 'Request ID payment is not valid.' . breakLine(2);
                        continue;
                    }

                    $response = $paymentRedirection->query($requestId);
                    $status = $this->getStatusPayment($response);
                    $order = $this->getOrderByCartId($cartId);
                    $cart = Cart::getCartByOrderId($order->id);
                    $currency = new Currency(($cart->id_currency));
                    Context::getContext()->currency = $currency;

                    if (!$order) {
                        echo 'Order not found: ' . $cartId . breakLine(2);
                        continue;
                    }

                    $this->settleTransaction($paymentId, $status, $order, $response);

                    echo sprintf(
                        'Payment with reference: [%s] is [%d=%s]' . breakLine(2),
                        $reference,
                        $status,
                        implode('->', $this->getStatusDescription($status))
                    );
                }
            } else {
                echo 'Not exists payments pending.' . breakLine();
            }
        } catch (PrestaShopDatabaseException $e) {
            PaymentLogger::log($e->getMessage(), PaymentLogger::ERROR, 99, $e->getFile(), $e->getLine());
            echo 'Error: Module not installed' . breakLine();
        } catch (Exception $e) {
            PaymentLogger::log($e->getMessage(), PaymentLogger::ERROR, 99, $e->getFile(), $e->getLine());
            echo 'Error: ' . $e->getMessage() . breakLine();
        }

        echo 'Finished ' . date('Ymd H:i:s') . '.' . breakLine();
    }

    final private function getLocale($cart): string
    {
        if (versionComparePlaceToPay('1.7.8.0', '>=') && $locale = Language::getLocaleById((int)($cart->id_lang))) {
            return str_replace('-', '_', $locale);
        }

        return $this->getLocaleById((int)($cart->id_lang));
    }

    /**
     * @see https://github.com/PrestaShop/PrestaShop/blob/1.7.8.7/classes/Language.php#L772
     */
    private function getLocaleById(int $langId): string
    {
        try {
            $locale = Db::getInstance()->getValue('
                SELECT `language_code` FROM `' . _DB_PREFIX_ . 'lang` WHERE `id_lang` = ' . $langId
            );

            $lang = explode('-', $locale);

            $locale = $lang[0] . '_' . strtoupper($lang[1]);
        } catch (Exception $e) {
            $locale = 'es_CO';
        }

        return $locale;
    }

    /**
     * Register payment
     *
     * @param $requestId
     * @param $orderId
     * @param $currencyId
     * @param $amount
     * @param $status
     * @param $message
     * @param $ipAddress
     * @param $reference
     * @return bool
     * @throws PaymentException
     */
    final private function insertPaymentPlaceToPay(
        $requestId,
        $cardId,
        $currencyId,
        $amount,
        $status,
        $message,
        $ipAddress,
        $reference
    )
    {
        // Default values
        $reason = '';
        $date = date('Y-m-d H:i:s');
        $reasonDescription = pSQL($message);
        $conversion = 1;
        $authCode = '000000';

        $sql = "
            INSERT INTO {$this->tablePayment} (
                id_order,
                id_currency,
                date,
                amount,
                status,
                reason,
                reason_description,
                conversion,
                ip_address,
                id_request,
                authcode,
                reference
            ) VALUES (
                '$cardId',
                '$currencyId',
                '$date',
                '$amount',
                '$status',
                '$reason',
                '$reasonDescription',
                '$conversion',
                '$ipAddress',
                '$requestId',
                '$authCode',
                '$reference'
            )
        ";

        try {
            Db::getInstance()->Execute($sql);
        } catch (Exception $e) {
            throw new PaymentException($e->getMessage(), 401);
        }

        return true;
    }

    /**
     * @throws PaymentException
     */
    final private function settleTransaction(int $paymentId, string $status, Order $order, RedirectInformation $response)
    {
        // Order not has been processed
        if ($order->getCurrentState() != (int)Configuration::get('PS_OS_PAYMENT')) {
            switch ($status) {
                case PaymentStatus::FAILED:
                case PaymentStatus::REJECTED:
                    if (in_array($order->getCurrentState(), [
                        Configuration::get('PS_OS_ERROR'),
                        Configuration::get('PS_OS_CANCELED')
                    ])) {
                        break;
                    }

                    // Update status order
                    $history = new OrderHistory();
                    $history->id_order = (int)($order->id);

                    if ($status == PaymentStatus::FAILED) {
                        $history->changeIdOrderState(Configuration::get('PS_OS_ERROR'), $history->id_order);
                    } elseif ($status == PaymentStatus::REJECTED) {
                        $history->changeIdOrderState(Configuration::get('PS_OS_CANCELED'), $history->id_order);
                    }

                    $history->addWithemail();
                    $history->save();

                    break;
                case PaymentStatus::DUPLICATE:
                case PaymentStatus::APPROVED:
                    // Order approved, change state
                    $history = new OrderHistory();
                    $history->id_order = (int)($order->id);
                    $history->changeIdOrderState(Configuration::get('PS_OS_PAYMENT'), $history->id_order);
                    $history->addWithemail();
                    $history->save();
                    break;
                case PaymentStatus::PENDING:
                    break;
            }
        }

        // Update status in payment table
        $this->updateTransaction($paymentId, $status, $response);
    }

    /**
     * @throws PaymentException
     */
    final private function updateTransaction(int $paymentId, string $status, RedirectInformation $response): bool
    {
        $date = pSQL($response->status()->date());
        $reason = pSQL($response->status()->reason());
        $reasonDescription = pSQL($response->status()->message());

        $bank = '';
        $franchise = '';
        $franchiseName = '';
        $authCode = '';
        $receipt = '';
        $conversion = '';
        $payerEmail = '';
        $installments = '';
        $lastDigits = '';

        if (!empty($payment = $response->lastTransaction())
            && !empty($paymentStatus = $payment->status())
            && ($payment->isSuccessful())
        ) {
            $date = pSQL($paymentStatus->date());
            $reason = pSQL($paymentStatus->reason());
            $reasonDescription = pSQL($paymentStatus->message());

            $bank = pSQL($payment->issuerName());
            $franchise = pSQL($payment->franchise());
            $franchiseName = pSQL($payment->paymentMethodName());
            $authCode = pSQL($payment->authorization());
            $receipt = pSQL($payment->receipt());
            $conversion = pSQL($payment->amount()->factor());
            $installments = $this->getInstallments($payment->additionalData());
            $lastDigits = pSQL(str_replace('*', '', $payment->additionalData()['lastDigits']));
        }

        if (!empty($request = $response->request())
            && !empty($payer = $request->payer())
            && !empty($email = $payer->email())) {
            $payerEmail = pSQL($email);
        }

        $sql = "
            UPDATE `{$this->tablePayment}` SET
                `status` = {$status},
                `date` = '{$date}',
                `reason` = '{$reason}',
                `reason_description` = '{$reasonDescription}',
                `franchise` = '{$franchise}',
                `franchise_name` = '{$franchiseName}',
                `bank` = '{$bank}',
                `authcode` = '{$authCode}',
                `receipt` = '{$receipt}',
                `conversion` = '{$conversion}',
                `payer_email` = '{$payerEmail}',
                `installments` = '{$installments}',
                `card_last_digits` = '{$lastDigits}'
            WHERE `id_payment` = {$paymentId}
        ";

        try {
            Db::getInstance()->Execute($sql);
        } catch (Exception $e) {
            throw new PaymentException($e->getMessage(), 601);
        }

        return true;
    }

    final private function getInstallments(array $additionalData): int
    {
        $installmentKeys = ['installments', 'installment'];

        foreach ($installmentKeys as $key) {
            if (isset($additionalData[$key]) && is_numeric($additionalData[$key])) {
                return (int) $additionalData[$key];
            }
        }

        if (isset($additionalData['processorFields']) && is_array($additionalData['processorFields'])) {
            foreach ($additionalData['processorFields'] as $field) {
                if (isset($field['value']) && is_array($field['value'])) {
                    foreach ($installmentKeys as $key) {
                        if (isset($field['value'][$key]) && is_numeric($field['value'][$key])) {
                            return (int) $field['value'][$key];
                        }
                    }
                }
            }
        }

        foreach ($additionalData as $value) {
            if (is_array($value)) {
                foreach ($installmentKeys as $key) {
                    if (isset($value[$key]) && is_numeric($value[$key])) {
                        return (int) $value[$key];
                    }
                }
            }
        }

        return 0;
    }

    final private function updateCurrentOrderWithError()
    {
        $history = new OrderHistory();
        $history->id_order = $this->currentOrder;
        $history->changeIdOrderState(Configuration::get('PS_OS_ERROR'), $history->id_order);
        $history->save();
    }

    final private function updateOrderState(): bool
    {
        $sql = "UPDATE `{$this->tableLanguage}` SET `name` = '" .
            pSQL($this->resolveStateMessage($this->getCurrentValueOf('PS_LOCALE_LANGUAGE'))) .
            "' WHERE `id_order_state` = " . $this->getOrderState();

        try {
            Db::getInstance()->execute($sql);
        } catch (PrestaShopDatabaseException $e) {
            PaymentLogger::log($e->getMessage(), PaymentLogger::INFO, 0, $e->getFile(), $e->getLine());
        } catch (Exception $e) {
            PaymentLogger::log($e->getMessage(), PaymentLogger::WARNING, 19, $e->getFile(), $e->getLine());

            return false;
        }

        return true;
    }

    final private function createOrderState(): bool
    {
        if (!$this->getOrderState()) {
            $orderState = new OrderState();
            $orderState->name = [];

            foreach (Language::getLanguages() as $language) {
                $lang = $language['id_lang'];

                $orderState->name[$lang] = $this->resolveStateMessage(Tools::strtolower($language['iso_code']));
            }

            $orderState->color = 'lightblue';
            $orderState->hidden = false;
            $orderState->logable = false;
            $orderState->invoice = false;
            $orderState->delivery = false;
            $orderState->send_email = false;
            $orderState->unremovable = true;

            if ($orderState->save()) {
                // This is for multiples stores
                Configuration::updateValue(self::ORDER_STATE, $orderState->id);
                copy(
                    fixPath($this->getThisModulePath() . '/logo.png'),
                    fixPath(_PS_IMG_DIR_ . 'os/' . $orderState->id . '.gif')
                );
            } else {
                return false;
            }
        }

        return true;
    }

    final private function resolveStateMessage(string $langCode): string
    {
        switch ($langCode) {
            case 'en':
                $message = 'Awaiting ' . $this->getClient() . ' payment confirmation';
                break;
            case 'fr':
                $message = 'En attente du paiement par ' . $this->getClient();
                break;
            case 'es':
            default:
                $message = 'En espera de confirmación de pago por ' . $this->getClient();
                break;
        }

        return $message;
    }

    final private function createPaymentTable(): bool
    {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->tablePayment}` (
                `id_payment` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `id_order` INT UNSIGNED NOT NULL,
                `id_currency` INT UNSIGNED NOT NULL,
                `date` DATETIME NULL,
                `amount` DECIMAL(10,2) NOT NULL,
                `status` TINYINT NOT NULL,
                `reason` VARCHAR(2) NULL,
                `reason_description` VARCHAR(255) NULL,
                `franchise` VARCHAR(5) NULL,
                `franchise_name` VARCHAR(128) NULL,
                `bank` VARCHAR(128) NULL,
                `authcode` VARCHAR(12) NULL,
                `receipt` VARCHAR(12) NULL,
                `conversion` DOUBLE,
                `ipaddress` VARCHAR(30) NULL,
                INDEX `id_orderIX` (`id_order`)
            ) ENGINE = " . _MYSQL_ENGINE_;

        try {
            Db::getInstance()->Execute($sql);
        } catch (Exception $e) {
            PaymentLogger::log($e->getMessage(), PaymentLogger::WARNING, 1, $e->getFile(), $e->getLine());

            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    final private function addColumnEmail(): bool
    {
        $sql = "ALTER TABLE `{$this->tablePayment}` ADD `payer_email` VARCHAR(80) NULL;";

        try {
            Db::getInstance()->Execute($sql);
        } catch (PrestaShopDatabaseException $e) {
            // Column had been to change before
            PaymentLogger::log($e->getMessage(), PaymentLogger::INFO, 0, $e->getFile(), $e->getLine());
        } catch (Exception $e) {
            PaymentLogger::log($e->getMessage(), PaymentLogger::WARNING, 2, $e->getFile(), $e->getLine());

            return false;
        }

        return true;
    }

    final private function addColumnRequestId(): bool
    {
        $sql = "ALTER TABLE `{$this->tablePayment}` ADD `id_request` INT NULL;";

        try {
            Db::getInstance()->Execute($sql);
        } catch (PrestaShopDatabaseException $e) {
            // Column had been to change before
            PaymentLogger::log($e->getMessage(), PaymentLogger::INFO, 0, $e->getFile(), $e->getLine());
        } catch (Exception $e) {
            PaymentLogger::log($e->getMessage(), PaymentLogger::WARNING, 3, $e->getFile(), $e->getLine());

            return false;
        }

        return true;
    }

    final private function addColumnReference(): bool
    {
        $sql = "ALTER TABLE `{$this->tablePayment}` ADD `reference` VARCHAR(60) NULL;";

        try {
            Db::getInstance()->Execute($sql);
        } catch (PrestaShopDatabaseException $e) {
            // Column had been to change before
            PaymentLogger::log($e->getMessage(), PaymentLogger::INFO, 0, $e->getFile(), $e->getLine());
        } catch (Exception $e) {
            PaymentLogger::log($e->getMessage(), PaymentLogger::WARNING, 4, $e->getFile(), $e->getLine());

            return false;
        }

        return true;
    }

    final private function alterColumnIpAddress(): bool
    {
        // In all version < 2.0 this columns is bad name ipaddress => ip_address
        $sql = "ALTER TABLE `{$this->tablePayment}` CHANGE COLUMN `ipaddress` `ip_address` VARCHAR(30) NULL;";

        try {
            Db::getInstance()->Execute($sql);
        } catch (PrestaShopDatabaseException $e) {
            // Column had been to change before
            PaymentLogger::log($e->getMessage(), PaymentLogger::INFO, 0, $e->getFile(), $e->getLine());
        } catch (Exception $e) {
            PaymentLogger::log($e->getMessage(), PaymentLogger::WARNING, 5, $e->getFile(), $e->getLine());

            return false;
        }

        return true;
    }

    final private function addInstallmentLastDigitsColumns(): bool
    {
        $sql = "ALTER TABLE `{$this->tablePayment}` ADD `installments` VARCHAR(10) NULL, ADD `card_last_digits` VARCHAR(4) NULL;";

        try {
            Db::getInstance()->Execute($sql);
        } catch (PrestaShopDatabaseException $e) {
            PaymentLogger::log($e->getMessage(), PaymentLogger::INFO, 0, $e->getFile(), $e->getLine());
        } catch (Exception $e) {
            PaymentLogger::log($e->getMessage(), PaymentLogger::WARNING, 20, $e->getFile(), $e->getLine());

            return false;
        }

        return true;
    }

    /**
     * Validation data from post settings form
     * @return array
     */
    final private function formValidation()
    {
        $formErrors = [];

        if (Tools::isSubmit('submitPlacetoPayConfiguration')) {
            // Company data
            if (!Tools::getValue(self::COMPANY_DOCUMENT)) {
                $formErrors[] = sprintf('%s %s', $this->ll('Merchant ID'), $this->ll('is required.'));
            }

            if (!Tools::getValue(self::COMPANY_NAME)) {
                $formErrors[] = sprintf('%s %s', $this->ll('Legal Name'), $this->ll('is required.'));
            }

            if (!Tools::getValue(self::EMAIL_CONTACT)) {
                $formErrors[] = sprintf('%s %s', $this->ll('Email contact'), $this->ll('is required.'));
            } elseif (filter_var(Tools::getValue(self::EMAIL_CONTACT), FILTER_VALIDATE_EMAIL) === false) {
                $formErrors[] = sprintf('%s %s', $this->ll('Email contact'), $this->ll('is not valid.'));
            }

            if (!Tools::getValue(self::TELEPHONE_CONTACT)) {
                $formErrors[] = sprintf('%s %s', $this->ll('Telephone contact'), $this->ll('is required.'));
            }

            // Connection Configuration
            if (!Tools::getValue(self::EXPIRATION_TIME_MINUTES)) {
                $formErrors[] = sprintf('%s %s', $this->ll('Expiration time to pay'), $this->ll('is required.'));
            } elseif (filter_var(Tools::getValue(self::EXPIRATION_TIME_MINUTES), FILTER_VALIDATE_INT) === false
                || Tools::getValue(self::EXPIRATION_TIME_MINUTES) < self::EXPIRATION_TIME_MINUTES_MIN) {
                $formErrors[] = sprintf(
                    '%s %s (min %d)',
                    $this->ll('Expiration time to pay'),
                    $this->ll('is not valid.'),
                    self::EXPIRATION_TIME_MINUTES_MIN
                );
            }

            if (!Tools::getValue(self::CLIENT)) {
                $formErrors[] = sprintf('%s %s', $this->ll('Client'), $this->ll('is required.'));
            }

            if (!Tools::getValue(self::ENVIRONMENT)) {
                $formErrors[] = sprintf('%s %s', $this->ll('Environment'), $this->ll('is required.'));
            } elseif (Tools::getValue(self::ENVIRONMENT) === Environment::CUSTOM
                && filter_var(Tools::getValue(self::CUSTOM_CONNECTION_URL), FILTER_VALIDATE_URL) === false) {
                $formErrors[] = sprintf('%s %s', $this->ll('Custom connection URL'), $this->ll('is not valid.'));
            }

            if (!Tools::getValue(self::LOGIN)) {
                $formErrors[] = sprintf('%s %s', $this->ll('Login'), $this->ll('is required.'));
            }

            if (empty($this->getCurrentValueOf(self::TRAN_KEY)) && !Tools::getValue(self::TRAN_KEY)) {
                $formErrors[] = sprintf('%s %s', $this->ll('Trankey'), $this->ll('is required.'));
            }
        }

        return $formErrors;
    }

    /**
     * Update configuration vars
     */
    final private function formProcess()
    {
        if (Tools::isSubmit('submitPlacetoPayConfiguration')) {
            if (Configuration::get(self::CLIENT) !== Tools::getValue(self::CLIENT)) {
                $this->updateOrderState();
            }
            // Company data
            Configuration::updateValue(self::COMPANY_DOCUMENT, Tools::getValue(self::COMPANY_DOCUMENT));
            Configuration::updateValue(self::COMPANY_NAME, Tools::getValue(self::COMPANY_NAME));
            Configuration::updateValue(self::EMAIL_CONTACT, Tools::getValue(self::EMAIL_CONTACT));
            Configuration::updateValue(self::TELEPHONE_CONTACT, Tools::getValue(self::TELEPHONE_CONTACT));
            // Configuration
            Configuration::updateValue(self::EXPIRATION_TIME_MINUTES, Tools::getValue(self::EXPIRATION_TIME_MINUTES));
            Configuration::updateValue(self::SHOW_ON_RETURN, Tools::getValue(self::SHOW_ON_RETURN));
            Configuration::updateValue(self::CIFIN_MESSAGE, Tools::getValue(self::CIFIN_MESSAGE));
            Configuration::updateValue(
                self::ALLOW_BUY_WITH_PENDING_PAYMENTS,
                Tools::getValue(self::ALLOW_BUY_WITH_PENDING_PAYMENTS)
            );
            Configuration::updateValue(self::FILL_TAX_INFORMATION, Tools::getValue(self::FILL_TAX_INFORMATION));
            Configuration::updateValue(self::FILL_BUYER_INFORMATION, Tools::getValue(self::FILL_BUYER_INFORMATION));
            Configuration::updateValue(self::SKIP_RESULT, Tools::getValue(self::SKIP_RESULT));
            Configuration::updateValue(self::DISCOUNT, Tools::getValue(self::DISCOUNT));
            Configuration::updateValue(self::INVOICE, Tools::getValue(self::INVOICE));

            // Connection Configuration
            Configuration::updateValue(self::CLIENT, Tools::getValue(self::CLIENT));
            Configuration::updateValue(self::ENVIRONMENT, Tools::getValue(self::ENVIRONMENT));
            // Set or clean custom URL
            $this->isCustomEnvironment()
                ? Configuration::updateValue(self::CUSTOM_CONNECTION_URL, Tools::getValue(self::CUSTOM_CONNECTION_URL))
                : Configuration::updateValue(self::CUSTOM_CONNECTION_URL, '');
            Configuration::updateValue(self::LOGIN, Tools::getValue(self::LOGIN));

            if (Tools::getValue(self::TRAN_KEY)) {
                // Value changed
                Configuration::updateValue(self::TRAN_KEY, Tools::getValue(self::TRAN_KEY));
            }

            Configuration::updateValue(self::PAYMENT_BUTTON_IMAGE, Tools::getValue(self::PAYMENT_BUTTON_IMAGE));
            Configuration::updateValue(self::LIGHTBOX, Tools::getValue(self::LIGHTBOX));
        }
    }

    /**
     * Show configuration form
     *
     * @return string
     */
    final private function displayConfiguration()
    {
        $this->smarty->assign(
            [
                'is_set_credentials' => $this->isSetCredentials(),
                'warning_compliancy' => (!$this->isCompliancy() ? $this->getCompliancyMessage() : ''),
                'version' => $this->getPluginVersion(),
                'url_notification' => $this->getUrl('process.php'),
                'schedule_task' => $this->getScheduleTaskPath(),
                'log_file' => $this->getLogFilePath(),
                'log_database' => $this->context->link->getAdminLink('AdminLogs'),
                'url_logo' => $this->getImage(),
                'client' => $this->getClient(),
                'isset_credentials' => $this->lll('You need to configure your %s account before using this module.'),
                'notify_translation' => $this->lll('URL used by %s to report the status of payments.')
            ]
        );

        return $this->display($this->getThisModulePath(), fixPath('/views/templates/front/setting.tpl'));
    }

    /**
     * Show warning pending payment
     *
     * @param $lastPendingTransaction
     * @return string
     */
    final private function displayPendingPaymentMessage($lastPendingTransaction)
    {
        $this->smarty->assign([
            'last_order' => $lastPendingTransaction['reference'] ?? '########',
            'last_authorization' => $lastPendingTransaction['authcode'] ?? null,
            'telephone_contact' => $this->getTelephoneContact(),
            'email_contact' => $this->getEmailContact(),
            'allow_payment' => $this->getAllowBuyWithPendingPayments(),
        ]);

        return $this->display($this->getThisModulePath(), fixPath('/views/templates/hook/pending_payment.tpl'));
    }

    /**
     * Show warning pending payment
     *
     * @return string
     */
    final private function displayTransUnionMessage()
    {
        $this->smarty->assign([
            'site_name' => Configuration::get('PS_SHOP_NAME'),
            'company_name' => $this->getCompanyName(),
        ]);

        return $this->display($this->getThisModulePath(), fixPath('/views/templates/hook/message_payment.tpl'));
    }

    public function hookDisplayAdminOrderLeft($params)
    {
        return $this->orderDetails($params);
    }

    public function hookDisplayAdminOrderMainBottom($params)
    {
        return $this->orderDetails($params);
    }

    public function orderDetails($params)
    {
        if (!$this->active) {
            return null;
        }

        $orderId = $params['id_order'];
        $bsOrder = new Order((int)$orderId);

        if ($bsOrder->module !== 'placetopaypayment') {
            return null;
        }

        $result = $this->getTransactionInformation($bsOrder->id_cart);

        if (!empty($result)) {
            $installmentType = $result['installments'] > 0
                ? sprintf($this->ll('%s installments'), $result['installments'])
                : $this->ll('No installments');

            $details = [
                [
                    'key' => $this->ll('Buying order'),
                    'value' => $bsOrder->reference,
                ],
                [
                    'key' => $this->ll('Transaction Date'),
                    'value' => $result['date'],
                ],
                [
                    'key' => $this->ll('Payment Type'),
                    'value' => $result['franchise_name'],
                ],
                [
                    'key' => $this->ll('Installments Type'),
                    'value' => $installmentType,
                ],
                [
                    'key' => $this->ll('Installments'),
                    'value' => $result['installments'] ?? 0,
                ],
                [
                    'key' => $this->ll('Card last Digits'),
                    'value' => $result['card_last_digits'],
                ],
                [
                    'key' => $this->ll('Amount'),
                    'value' => '$' . number_format($result['amount'], 0, ',', '.'),
                ],
                [
                    'key' => $this->ll('Authorization Code'),
                    'value' => $result['authcode'],
                ],
                [
                    'key' => $this->ll('Status'),
                    'value' => PaymentStatus::STATUS[$result['status']],
                ],
                [
                    'key' => $this->ll('Receipt'),
                    'value' => $result['receipt'],
                ],
                [
                    'key' => $this->ll('Reason'),
                    'value' => $result['reason'],
                ],
            ];

            $this->context->smarty->assign([
                'icon' => $this->getImage(),
                'title' => $this->getClient(),
                'details' => $details,
            ]);

            return $this->display($this->getThisModulePath(), fixPath('/views/templates/admin/admin_order.tpl'));
        }

        return null;
    }

    final private function getImage(): string
    {
        $url = $this->getImageUrl();

        if (empty($url)) {
            $image = $this->getImageByCountry($this->getClient());
        } elseif ($this->checkValidUrl($url)) {
            $image = $url;
        } elseif ($this->checkDirectory($url)) {
            $image = $this->context->shop->getBaseURL(true) . $url;
        } else {
            $image = 'https://static.placetopay.com/' . $url . '.svg';
        }

        return $image;
    }

    final private function getImageByCountry(string $client): string
    {
        $clientImage = [
            Client::GNT => 'uggcf://onapb.fnagnaqre.py/hcybnqf/000/029/870/0620s532-9sp9-4248-o99r-78onr9s13r1q/bevtvany/Ybtb_JroPurpxbhg_Trgarg.fit',
            Client::GOU => 'uggcf://cynprgbcnl-fgngvp-hng-ohpxrg.f3.hf-rnfg-2.nznmbanjf.pbz/ninycnlpragre-pbz/ybtbf/Urnqre+Pbeerb+-+Ybtb+Ninycnl.fit',
            Client::PTP => 'uggcf://fgngvp.cynprgbcnl.pbz/cynprgbcnl-ybtb.fit'
        ];

        return $clientImage[unmaskString($client ?? '')] ? unmaskString($clientImage[unmaskString($client)]) :
            unmaskString('uggcf://fgngvp.cynprgbcnl.pbz/cynprgbcnl-ybtb.fit');
    }

    final private function checkDirectory(string $path): bool
    {
        return substr($path, 0, 1) === '/';
    }

    final private function checkValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL);
    }

    /**
     * Show warning pending payment
     *
     * @return string
     */
    final private function displayBrandMessage(): string
    {
        $this->context->smarty->assign('url', $this->getImage());
        $this->context->smarty->assign('client_message', $this->lll('Pay by %s'));
        $this->context->smarty->assign('secure_message',
            $this->lll('%s secure web site will be displayed when you select this payment method.')
        );
        return $this->display($this->getThisModulePath(), fixPath('/views/templates/hook/brand_payment.tpl'));
    }

    final private function getConfigFieldsValues(): array
    {
        return [
            self::COMPANY_DOCUMENT => $this->getCompanyDocument(),
            self::COMPANY_NAME => $this->getCompanyName(),
            self::EMAIL_CONTACT => $this->getEmailContact(),
            self::TELEPHONE_CONTACT => $this->getTelephoneContact(),

            self::EXPIRATION_TIME_MINUTES => $this->getExpirationTimeMinutes(),
            self::SHOW_ON_RETURN => $this->getShowOnReturn(),
            self::CIFIN_MESSAGE => $this->getTransUnionMessage(),
            self::ALLOW_BUY_WITH_PENDING_PAYMENTS => $this->getAllowBuyWithPendingPayments(),
            self::FILL_TAX_INFORMATION => $this->getFillTaxInformation(),
            self::FILL_BUYER_INFORMATION => $this->getFillBuyerInformation(),
            self::SKIP_RESULT => $this->getSkipResult(),
            self::DISCOUNT => $this->getDiscount(),
            self::INVOICE => $this->getInvoice(),

            self::CLIENT => $this->getClient(),
            self::ENVIRONMENT => $this->getEnvironment(),
            self::CUSTOM_CONNECTION_URL => $this->isCustomEnvironment() ? $this->getCustomConnectionUrl() : '',
            self::LOGIN => $this->getLogin(),
            self::TRAN_KEY => $this->getTranKey(),
            self::PAYMENT_BUTTON_IMAGE => $this->getImageUrl(),
            self::LIGHTBOX => $this->getLightbox()
        ];
    }

    final private function renderForm(): string
    {
        $formCompany = [
            'form' => [
                'legend' => $this->getLegendTo('Company data', 'icon-building'),
                'input' => $this->getFieldsCompany(),
                'submit' => $this->getSubmitButton()
            ],
        ];

        $formConfiguration = [
            'form' => [
                'legend' => $this->getLegendTo('Configuration', 'icon-cogs'),
                'input' => $this->getFieldsConfiguration(),
                'submit' => $this->getSubmitButton()
            ],
        ];

        $formConnection = [
            'form' => [
                'legend' => $this->getLegendTo('Connection Configuration', 'icon-rocket'),
                'input' => $this->getFieldsConnection(),
                'submit' => $this->getSubmitButton()
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->default_form_language = (new Language((int)Configuration::get('PS_LANG_DEFAULT')))->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ?: 0;
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitPlacetoPayConfiguration';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure='
            . getModuleName() . '&tab_module=' . $this->tab . '&module_name=' . getModuleName();
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        ];

        return $helper->generateForm([$formCompany, $formConfiguration, $formConnection]);
    }

    final private function generateForm(?string $action, string $content)
    {
        $action = is_null($action)
            ? "onsubmit='return false;'"
            : "action='{$action}'";

        $content = mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8');

        return "<form accept-charset='UTF-8' {$action} id='payment-form'>{$content}</form>";
    }

    final private function getPluginVersion(): string
    {
        return $this->version;
    }

    final private function isCompliancy(): bool
    {
        return versionComparePlaceToPay(self::MAX_VERSION_PS, '<=');
    }

    final private function getCompliancyMessage(): string
    {
        return sprintf(
            $this->ll('This plugin don\'t has been tested with your PrestaShop version [%s].'),
            _PS_VERSION_
        );
    }

    /**
     * @return mixed|string
     */
    final private function getCurrentValueOf(string $name)
    {
        return Tools::getValue($name)
            ? Tools::getValue($name)
            : Configuration::get($name);
    }

    final private function getUrl($page, $params = ''): string
    {
        $baseUrl = Context::getContext()->shop->getBaseURL(true);

        return $baseUrl . 'modules/' . getModuleName() . '/' . $page . $params;
    }

    final private function getScheduleTaskPath(): string
    {
        return fixPath($this->getThisModulePath() . '/sonda.php');
    }

    final private function getLogFilePath(): string
    {
        return PaymentLogger::getLogFilename();
    }

    final private function getCompanyDocument(): ?string
    {
        return $this->getCurrentValueOf(self::COMPANY_DOCUMENT);
    }

    final private function getCompanyName(): ?string
    {
        return $this->getCurrentValueOf(self::COMPANY_NAME);
    }

    final private function getEmailContact(): ?string
    {
        $emailContact = $this->getCurrentValueOf(self::EMAIL_CONTACT);

        return empty($emailContact)
            ? Configuration::get('PS_SHOP_EMAIL')
            : $emailContact;
    }

    final private function getTelephoneContact(): ?string
    {
        $telephoneContact = $this->getCurrentValueOf(self::TELEPHONE_CONTACT);

        return empty($telephoneContact)
            ? Configuration::get('PS_SHOP_PHONE')
            : $telephoneContact;
    }

    final private function getDescription(): string
    {
        return 'Pago en ' . $this->getClient() . ' No: %s';
    }

    final private function getExpirationTimeMinutes(): int
    {
        $minutes = $this->getCurrentValueOf(self::EXPIRATION_TIME_MINUTES);

        return !is_numeric($minutes) || $minutes < self::EXPIRATION_TIME_MINUTES_MIN
            ? self::EXPIRATION_TIME_MINUTES_DEFAULT
            : $minutes;
    }

    final private function getShowOnReturn(): string
    {
        return $this->getCurrentValueOf(self::SHOW_ON_RETURN);
    }

    final private function getTransUnionMessage(): string
    {
        return $this->getCurrentValueOf(self::CIFIN_MESSAGE);
    }

    final private function getAllowBuyWithPendingPayments(): bool
    {
        return (bool)$this->getCurrentValueOf(self::ALLOW_BUY_WITH_PENDING_PAYMENTS);
    }

    final private function getFillTaxInformation(): bool
    {
        return (bool)$this->getCurrentValueOf(self::FILL_TAX_INFORMATION);
    }

    final private function getFillBuyerInformation(): bool
    {
        return (bool)$this->getCurrentValueOf(self::FILL_BUYER_INFORMATION);
    }

    final private function getSkipResult(): bool
    {
        return (bool)$this->getCurrentValueOf(self::SKIP_RESULT);
    }

    final private function getDiscount(): string
    {
        return $this->getCurrentValueOf(self::DISCOUNT);
    }

    final private function getInvoice(): string
    {
        return $this->getCurrentValueOf(self::INVOICE);
    }

    final private function getEnvironment(): string
    {
        $environment = $this->getCurrentValueOf(self::ENVIRONMENT);

        return empty($environment)
            ? Environment::TEST
            : $environment;
    }

    final private function getCustomConnectionUrl(): ?string
    {
        $customEnvironment = $this->getCurrentValueOf(self::CUSTOM_CONNECTION_URL);

        return empty($customEnvironment)
            ? null
            : $customEnvironment;
    }

    final private function getLogin(): ?string
    {
        return $this->getCurrentValueOf(self::LOGIN);
    }

    final private function getTranKey(): ?string
    {
        return $this->getCurrentValueOf(self::TRAN_KEY);
    }

    final private function getImageUrl(): ?string
    {
        return $this->getCurrentValueOf(self::PAYMENT_BUTTON_IMAGE);
    }

    final private function getLightbox(): bool
    {
        return (bool)$this->getCurrentValueOf(self::LIGHTBOX);
    }

    final private function getUri(): ?string
    {
        $uri = null;
        $endpoints = PaymentUrl::getEndpointsTo($this->getDefaultPrestashopCountry(), $this->getClient());

        if ($this->isCustomEnvironment()) {
            $uri = $this->getCustomConnectionUrl();
        } elseif (!empty($endpoints[$this->getEnvironment()])) {
            $uri = $endpoints[$this->getEnvironment()];
        }

        return $uri;
    }

    /**
     * @return mixed
     */
    final private function getOrderState()
    {
        return Configuration::get(self::ORDER_STATE);
    }

    final private function getThisModulePath(): string
    {
        return _PS_MODULE_DIR_ . getModuleName();
    }

    final private function getRedirectPageFromStatus(): string
    {
        if (!Context::getContext()->customer->isLogged()) {
            return self::PAGE_ORDER_CONFIRMATION;
        }

        switch ($this->getShowOnReturn()) {
            case self::SHOW_ON_RETURN_DETAILS:
                $redirectTo = self::PAGE_ORDER_DETAILS;
                break;
            case self::SHOW_ON_RETURN_PSE_LIST:
                $redirectTo = self::PAGE_ORDER_HISTORY;
                break;
            case self:: SHOW_ON_RETURN_HOME:
                $redirectTo = self::PAGE_HOME;
                break;
            case self::SHOW_ON_RETURN_DEFAULT:
            default:
                $redirectTo = self::PAGE_ORDER_CONFIRMATION;
                break;
        }

        return $redirectTo;
    }

    /**
     * Get any column from $this->tablePayment table
     * @return array|bool
     */
    final private function getPaymentPlaceToPayBy(string $column, $value)
    {
        try {
            if (!empty($column) && !empty($value)) {
                $rows = Db::getInstance()->ExecuteS("
                    SELECT *
                    FROM  `{$this->tablePayment}`
                    WHERE {$column} = '{$value}'
                ");
            }
        } catch (Exception $e) {
            PaymentLogger::log($e->getMessage(), PaymentLogger::WARNING, 15, $e->getFile(), $e->getLine());
        }

        return !empty($rows[0]) ? $rows[0] : false;
    }

    final private function getIdByCartId($id_cart): ?int
    {
        $sql = 'SELECT `id_order`
            FROM `' . _DB_PREFIX_ . 'orders`
            WHERE `id_cart` = ' . (int)$id_cart;

        $result = Db::getInstance()->getValue($sql);

        return !empty($result) ? (int)$result : false;
    }

    /**
     * @param null $cartId
     * @return Order|null
     * @throws PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    final private function getOrderByCartId($cartId = null)
    {
        if (versionComparePlaceToPay('1.7.1.0', '>=')) {
            if (!is_null(Shop::getTotalShops()) && Shop::getTotalShops() > 1) {
                $orderId = $this->getIdByCartId($cartId);
            } else {
                $orderId = Order::getIdByCartId($cartId);
            }
        } else {
            $orderId = Order::getOrderByCartId($cartId);
        }

        if (!$orderId) {
            PaymentLogger::log(
                sprintf('Order ID from Cart [%s] not found', $cartId),
                PaymentLogger::WARNING,
                201,
                __FILE__,
                __LINE__
            );

            return null;
        }

        $order = new Order($orderId);

        if (!Validate::isLoadedObject($order)) {
            PaymentLogger::log(
                sprintf('Order [%s] from Cart [%s] not loaded', $orderId, $cartId),
                PaymentLogger::WARNING,
                202,
                __FILE__,
                __LINE__
            );

            return null;
        }

        return $order;
    }

    /**
     * Last transaction pending to current costumer
     *
     * @param $customerId
     * @return mixed
     * @throws PaymentException
     */
    final private function getLastPendingTransaction($customerId)
    {
        $status = PaymentStatus::PENDING;

        try {
            $result = Db::getInstance()->ExecuteS("
                SELECT p.*
                FROM `{$this->tablePayment}` p
                    INNER JOIN `{$this->tableOrder}` o ON o.id_cart = p.id_order
                WHERE o.`id_customer` = {$customerId}
                    AND p.`status` = {$status}
                LIMIT 1
            ");
        } catch (Exception $e) {
            throw new PaymentException($e->getMessage(), 901);
        }

        if (!empty($result)) {
            $result = $result[0];
        }

        return $result;
    }

    /**
     * Get customer orders
     *
     * @param $id_customer Customer id
     * @param bool $show_hidden_status Display or not hidden order statuses
     * @param Context|null $context
     * @return array
     */
    final private function getCustomerOrders($id_customer, $show_hidden_status = false, Context $context = null)
    {
        if (!$context) {
            $context = Context::getContext();
        }

        $sql = 'SELECT o.`id_order`, o.`id_currency`, o.`payment`, o.`invoice_number`, pp.`date` date_add,
                      pp.`reference`, pp.`amount` total_paid, pp.`authcode` cus,
                      (SELECT SUM(od.`product_quantity`)
                      FROM `' . _DB_PREFIX_ . 'order_detail` od
                      WHERE od.`id_order` = o.`id_order`) nb_products
        FROM `' . $this->tableOrder . '` o
            JOIN `' . $this->tablePayment . '` pp ON pp.id_order = o.id_cart
        WHERE o.`id_customer` = ' . (int)$id_customer .
            Shop::addSqlRestriction(Shop::SHARE_ORDER) . '
        GROUP BY o.`id_order`
        ORDER BY o.`date_add` DESC';

        $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        if (!$res) {
            return [];
        }

        foreach ($res as $key => $val) {
            $res2 = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
                SELECT os.`id_order_state`, osl.`name` AS order_state, os.`invoice`, os.`color` AS order_state_color
                FROM `' . _DB_PREFIX_ . 'order_history` oh
                LEFT JOIN `' . _DB_PREFIX_ . 'order_state` os ON (os.`id_order_state` = oh.`id_order_state`)
                INNER JOIN `' . _DB_PREFIX_ . 'order_state_lang` osl ON (
                    os.`id_order_state` = osl.`id_order_state` AND osl.`id_lang` = ' . (int)$context->language->id . '
                )
            WHERE oh.`id_order` = ' . (int)$val['id_order'] . (!$show_hidden_status ? ' AND os.`hidden` != 1' : '') . '
            ORDER BY oh.`date_add` DESC, oh.`id_order_history` DESC
            LIMIT 1');

            if ($res2) {
                $res[$key] = array_merge($res[$key], $res2[0]);
            }
        }

        return $res;
    }

    /**
     * @throws PaymentException
     */
    final private function getTransactionInformation(int $cartId): array
    {
        try {
            $result = Db::getInstance()->ExecuteS(
                "SELECT * FROM `{$this->tablePayment}` WHERE `id_order` = {$cartId}"
            );
        } catch (Exception $e) {
            throw new PaymentException($e->getMessage(), 801);
        }

        if (!empty($result)) {
            $result = $result[0];

            if (empty($result['reason_description'])) {
                $result['reason_description'] = ($result['reason'] == '?-')
                    ? $this->ll('Processing transaction')
                    : $this->ll('No information');
            }

            if (empty($result['status'])) {
                $result['status_description'] = ($result['status'] == '')
                    ? $this->ll('Processing transaction')
                    : $this->ll('No information');
            }
        }

        return $result;
    }

    /**
     * @param Order $order
     * @return mixed
     * @throws PaymentException
     */
    final private function getPaymentDetails(Order $order)
    {
        // Get information
        $transaction = $this->getTransactionInformation($order->id_cart);
        $cart = new Cart((int)$order->id_cart);
        $invoiceAddress = new Address((int)($cart->id_address_invoice));
        $totalAmount = (float)($cart->getOrderTotal(true, Cart::BOTH));
        $taxAmount = $totalAmount - (float)($cart->getOrderTotal(false, Cart::BOTH));
        $payerName = '';
        $payerEmail = !empty($transaction['payer_email']) ? $transaction['payer_email'] : null;
        $transaction['tax'] = $taxAmount;

        // Customer data
        $customer = new Customer((int)($order->id_customer));

        if (Validate::isLoadedObject($customer)) {
            $payerName = empty($invoiceAddress)
                ? $customer->firstname . ' ' . $customer->lastname
                : $invoiceAddress->firstname . ' ' . $invoiceAddress->lastname;
            $payerEmail = isset($payerEmail) ? $payerEmail : $customer->email;
        }

        $attributes = [
                'url' => $this->getImage(),
                'company_document' => $this->getCompanyDocument(),
                'company_name' => $this->getCompanyName(),
                'payment_description' => sprintf($this->getDescription(), $transaction['reference']),
                'store_email' => $this->getEmailContact(),
                'store_phone' => $this->getTelephoneContact(),
                'transaction' => $transaction,
                'payer_name' => $payerName,
                'payer_email' => $payerEmail,
                'customer_id' => $cart->id_customer,
                'orderId' => $order->id,
                'logged' => (Context::getContext()->customer->isLogged() ? true : false),
            ] + $this->getStatusDescription($transaction['status']);

        $this->context->smarty->assign($attributes);

        return $this->display($this->getThisModulePath(), fixPath('/views/templates/front/response.tpl'));
    }

    final private function getStatusDescription(string $status): array
    {
        switch ($status) {
            case PaymentStatus::APPROVED:
            case PaymentStatus::DUPLICATE:
                $description = [
                    'status' => 'ok',
                    'status_description' => $this->ll('Completed payment')
                ];

                break;
            case PaymentStatus::FAILED:
                $description = [
                    'status' => 'fail',
                    'status_description' => $this->ll('Failed payment')
                ];

                break;
            case PaymentStatus::REJECTED:
                $description = [
                    'status' => 'rejected',
                    'status_description' => $this->ll('Rejected payment')
                ];

                break;
            default:
                $description = [
                    'status' => 'pending',
                    'status_description' => $this->ll('Pending payment')
                ];

                break;
        }

        return $description;
    }

    final private function getStatusPayment(RedirectInformation $response): int
    {
        $status = PaymentStatus::PENDING;

        if ($response->isSuccessful()) {
            if ($response->status()->isApproved()) {
                $status = PaymentStatus::APPROVED;
            } elseif ($response->status()->isRejected()) {
                $status = PaymentStatus::REJECTED;
            }
        } elseif ($response->status()->isRejected()) {
            $status = PaymentStatus::REJECTED;
        }

        return $status;
    }

    /**
     * @param $customerId
     * @return string
     */
    final private function getPaymentPSEList($customerId)
    {
        $orders = self::getCustomerOrders($customerId);
        $isPaid = false;

        if ($orders) {
            foreach ($orders as &$order) {
                $myOrder = new Order((int)$order['id_order']);
                if (Validate::isLoadedObject($myOrder)) {
                    $order['virtual'] = $myOrder->isVirtual(false);
                }
            }

            $lastOrder = new Order((int)$orders[0]['id_order']);
            $isPaid = $lastOrder->getCurrentOrderState()->paid;
        }

        $this->context->smarty->assign([
            'orders' => $orders,
            'invoiceAllowed' => (int)Configuration::get('PS_INVOICE'),
            'reorderingAllowed' => !(bool)Configuration::get('PS_DISALLOW_HISTORY_REORDERING'),
            'slowValidation' => Tools::isSubmit('slowvalidation'),
            'isPaid' => $isPaid
        ]);

        return $this->display($this->getThisModulePath(), fixPath('/views/templates/front/history.tpl'));
    }

    final private function getSetup(): string
    {
        $setup = $this->ll('Configuration') . breakLine();
        $setup .= sprintf('PHP [%s]', PHP_VERSION) . breakLine();
        $setup .= sprintf(
            'PrestaShop [%s] in %s mode' . breakLine(),
            _PS_VERSION_,
            isDebugEnable() ? 'DEBUG' : 'PRODUCTION'
        );
        $setup .= sprintf('Plugin [%s]', $this->getPluginVersion()) . breakLine();
        $setup .= sprintf('URL Base [%s]', $this->getUrl('')) . breakLine();
        $setup .= sprintf('Logs [%s]', $this->getLogFilePath()) . breakLine();
        $setup .= sprintf('%s [%s]', $this->ll('Country'), $this->getDefaultPrestashopCountry()) . breakLine();
        $setup .= sprintf('%s [%s]', $this->ll('Environment'), $this->getEnvironment()) . breakLine();

        if ($this->isCustomEnvironment()) {
            $setup .= sprintf(
                '%s [%s]' . breakLine(),
                $this->ll('Custom connection URL'),
                $this->getCustomConnectionUrl()
            );
        }

        $setup .= sprintf(
            '%s [%s]' . breakLine(),
            $this->ll('Expiration time to pay'),
            $this->getExpirationTimeMinutes()
        );
        $setup .= sprintf(
            '%s [%s]' . breakLine(),
            $this->ll('Allow buy with pending payments?'),
            $this->getAllowBuyWithPendingPayments() ? $this->ll('Yes') : $this->ll('No')
        );
        $setup .= sprintf(
            '%s [%s]' . breakLine(),
            $this->ll('Fill TAX information?'),
            $this->getFillTaxInformation() ? $this->ll('Yes') : $this->ll('No')
        );
        $setup .= sprintf(
            '%s [%s]' . breakLine(),
            $this->ll('Fill buyer information?'),
            $this->getFillTaxInformation() ? $this->ll('Yes') : $this->ll('No')
        );
        $setup .= sprintf(
            '%s [%s]' . breakLine(),
            $this->ll('Skip result?'),
            $this->getSkipResult() ? $this->ll('Yes') : $this->ll('No')
        );

        $setup .= breakLine();

        return $setup;
    }

    final private function getOptionSwitch(): array
    {
        return [
            [
                'id' => 'active_on',
                'value' => self::OPTION_ENABLED,
                'label' => $this->ll('Yes'),
            ],
            [
                'id' => 'active_off',
                'value' => self::OPTION_DISABLED,
                'label' => $this->ll('No'),
            ]
        ];
    }

    final private function getOptionList(array $options): array
    {
        $listOption = [];

        if (!is_array($options)) {
            return $listOption;
        }

        foreach ($options as $value => $label) {
            $listOption[] = compact('value', 'label');
        }

        return $listOption;
    }

    final private function getOptionListShowOnReturn(): array
    {
        $options = [
            self::SHOW_ON_RETURN_DEFAULT => $this->ll('PrestaShop View'),
            self::SHOW_ON_RETURN_DETAILS => $this->ll('Payment Details'),
            self::SHOW_ON_RETURN_PSE_LIST => $this->ll('PSE List'),
            self::SHOW_ON_RETURN_HOME => $this->ll('Home'),
        ];

        return $this->getOptionList($options);
    }

    final private function getDefaultPrestashopCountry(): string
    {
        return Country::getIsoById((int)Configuration::get('PS_COUNTRY_DEFAULT'));
    }

    final private function getDefaultClient(): string
    {
        return $this->getDefaultPrestashopCountry() === CountryCode::CHILE ? unmaskString(Client::GNT) : unmaskString(Client::PTP);
    }

    final private function getClient(): string
    {
        $client = $this->getCurrentValueOf(self::CLIENT);

        if (!$client) {
            $client = $this->getDefaultClient();
        }

        return $client;
    }

    final private function getOptionListCountries(): array
    {
        /** @var CountryConfigInterface $config */
        foreach (CountryCode::COUNTRIES_CLIENT as $config) {
            if (!$config::resolve($this->getDefaultPrestashopCountry())) {
                continue;
            }

            return $this->getOptionList($config::getClient());
        }

        return [];
    }

    final private function getOptionListEnvironments(): array
    {
        $options = [
            Environment::PRODUCTION => $this->ll('Production'),
            Environment::TEST => $this->ll('Test'),
            Environment::DEVELOPMENT => $this->ll('Development'),
            Environment::CUSTOM => $this->ll('Custom'),
        ];

        return $this->getOptionList($options);
    }

    final private function getOptionsDiscounts(): array
    {
        return $this->getOptionList([
            Discount::UY_IVA_REFUND => $this->ll(Discount::UY_IVA_REFUND),
            Discount::UY_IMESI_REFUND => $this->ll(Discount::UY_IMESI_REFUND),
            Discount::UY_FINANCIAL_INCLUSION => $this->ll(Discount::UY_FINANCIAL_INCLUSION),
            Discount::UY_AFAM_REFUND => $this->ll(Discount::UY_AFAM_REFUND),
            Discount::UY_TAX_REFUND => $this->ll(Discount::UY_AFAM_REFUND),
            Discount::UY_NONE => $this->ll('None'),
        ]);
    }

    final private function getFieldsCompany(): array
    {
        return [
            [
                'type' => 'text',
                'label' => $this->ll('Merchant ID'),
                'name' => self::COMPANY_DOCUMENT,
                'required' => true,
                'autocomplete' => 'off',
            ],
            [
                'type' => 'text',
                'label' => $this->ll('Legal Name'),
                'name' => self::COMPANY_NAME,
                'required' => true,
                'autocomplete' => 'off',
            ],
            [
                'type' => 'text',
                'label' => $this->ll('Email contact'),
                'name' => self::EMAIL_CONTACT,
                'required' => true,
                'autocomplete' => 'off',
            ],
            [
                'type' => 'text',
                'label' => $this->ll('Telephone contact'),
                'name' => self::TELEPHONE_CONTACT,
                'required' => true,
                'autocomplete' => 'off',
            ],
        ];
    }

    final private function getFieldsConfiguration(): array
    {
        $fields = [
            [
                'type' => 'text',
                'label' => $this->ll('Expiration time to pay'),
                'name' => self::EXPIRATION_TIME_MINUTES,
                'required' => true,
                'autocomplete' => 'off',
            ],
            [
                'type' => 'select',
                'label' => $this->lll('Returning from %s show'),
                'desc' => $this->ll('If you has PSE method payment in your commerce, set it in: PSE List.'),
                'name' => self::SHOW_ON_RETURN,
                'options' => [
                    'id' => 'value',
                    'name' => 'label',
                    'query' => $this->getOptionListShowOnReturn(),
                ],
            ],
            [
                'type' => 'switch',
                'label' => $this->ll('Enable TransUnion message?'),
                'name' => self::CIFIN_MESSAGE,
                'is_bool' => true,
                'values' => $this->getOptionSwitch(),
            ],
            [
                'type' => 'switch',
                'label' => $this->ll('Allow buy with pending payments?'),
                'name' => self::ALLOW_BUY_WITH_PENDING_PAYMENTS,
                'is_bool' => true,
                'values' => $this->getOptionSwitch(),
            ],
            [
                'type' => 'switch',
                'label' => $this->ll('Fill TAX information?'),
                'name' => self::FILL_TAX_INFORMATION,
                'is_bool' => true,
                'values' => $this->getOptionSwitch(),
            ],
            [
                'type' => 'switch',
                'label' => $this->ll('Fill buyer information?'),
                'name' => self::FILL_BUYER_INFORMATION,
                'is_bool' => true,
                'values' => $this->getOptionSwitch(),
            ],
            [
                'type' => 'switch',
                'label' => $this->ll('Skip result?'),
                'name' => self::SKIP_RESULT,
                'is_bool' => true,
                'values' => $this->getOptionSwitch(),
            ],
        ];

        if ($this->getDefaultPrestashopCountry() === CountryCode::URUGUAY) {
            $fields = array_merge($fields, [
                [
                    'type' => 'select',
                    'label' => 'Descuentos',
                    'name' => self::DISCOUNT,
                    'options' => [
                        'id' => 'value',
                        'name' => 'label',
                        'query' => $this->getOptionsDiscounts(),
                    ]
                ],
                [
                    'type' => 'text',
                    'label' => 'Invoice',
                    'name' => self::INVOICE,
                    'required' => false,
                    'autocomplete' => 'off',
                ],
            ]);
        }

        return $fields;
    }

    final private function getFieldsConnection(): array
    {
        $fields = [
            [
                'type' => 'select',
                'label' => $this->ll('Client'),
                'name' => self::CLIENT,
                'required' => true,
                'options' => [
                    'id' => 'value',
                    'name' => 'label',
                    'query' => $this->getOptionListCountries(),
                ],
            ],
            [
                'type' => 'select',
                'label' => $this->ll('Environment'),
                'name' => self::ENVIRONMENT,
                'required' => true,
                'options' => [
                    'id' => 'value',
                    'name' => 'label',
                    'query' => $this->getOptionListEnvironments(),
                ],
            ],
            [
                'type' => 'text',
                'label' => $this->ll('Custom connection URL'),
                'desc' => sprintf(
                    '%s %s: %s',
                    // @codingStandardsIgnoreLine
                    $this->ll('By example: "https://alternative.placetopay.com/redirection". This value only is required when you select'),
                    $this->ll('Environment'),
                    $this->ll('Custom')
                ),
                'name' => self::CUSTOM_CONNECTION_URL,
                'required' => $this->isCustomEnvironment(),
                'autocomplete' => 'off',
            ],
            [
                'type' => 'text',
                'label' => $this->ll('Login'),
                'name' => self::LOGIN,
                'required' => true,
                'autocomplete' => 'off',
            ],
            [
                'type' => 'password',
                'label' => $this->ll('Trankey'),
                'name' => self::TRAN_KEY,
                'required' => true,
                'autocomplete' => 'off',
            ],
            [
                'type' => 'text',
                'label' => $this->ll('Payment button image'),
                'desc' => $this->ll('It can be a URL, an image name (provide the image to the placetopay team as svg format for this to work) or a local path (save the image to the img folder).'),
                'name' => self::PAYMENT_BUTTON_IMAGE,
                'autocomplete' => 'off',
            ],
        ];

        if ($this->getDefaultPrestashopCountry() !== CountryCode::CHILE) {
            $fields[] = [
                'type' => 'switch',
                'label' => $this->ll('Lightbox'),
                'name' => self::LIGHTBOX,
                'is_bool' => true,
                'values' => $this->getOptionSwitch(),
            ];
        }

        return $fields;
    }

    final private function getLegendTo(string $title, string $icon): array
    {
        return [
            'title' => $this->ll($title),
            'icon' => $icon
        ];
    }

    final private function getSubmitButton(): array
    {
        return [
            'title' => $this->ll('Save'),
        ];
    }

    final private function isProduction(): bool
    {
        return $this->getEnvironment() === Environment::PRODUCTION;
    }

    final private function isCustomEnvironment(): bool
    {
        return $this->getEnvironment() === Environment::CUSTOM;
    }

    final private function isSetCredentials(): bool
    {
        return !empty($this->getLogin()) && !empty($this->getTranKey());
    }

    final private function isEnableShowSetup(): bool
    {
        $force = Tools::getValue('f', null);

        return !$this->isProduction()
            && !empty($force)
            && Tools::strlen($force) === 5
            && Tools::substr($this->getLogin(), -5) === $force;
    }

    /**
     * Manage translations in Plugin
     */
    final private function ll(string $string): string
    {
        return $this->l($string, getModuleName());
    }

    final private function lll(string $translation): string
    {
        return sprintf($this->ll($translation), $this->getClient());
    }

    final private function showError(array $errors): string
    {
        if (versionComparePlaceToPay('1.7.0.0', '<')) {
            $errors = implode('<br>', $errors);
        }

        return $this->displayError($errors);
    }

    final private function checkCurrency($cart): bool
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param $string
     * @param bool $rollBack
     * @return string
     */
    final private function reference(string $string, bool $rollBack = false): string
    {
        return !$rollBack
            ? base64_encode($string)
            : base64_decode($string);
    }

    final private function getHeaders(): array
    {
        $domain = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'];
        $userAgent = "$this->name/{$this->getPluginVersion()} (origin:$domain; vr:" . _PS_VERSION_ . ')';

        return [
            'User-Agent' => $userAgent,
            'X-Source-Platform' => 'prestashop',
        ];
    }

    final private function instanceRedirection(): PlacetoPay
    {
        $settings = [
            'login' => $this->getLogin(),
            'tranKey' => $this->getTranKey(),
            'baseUrl' => $this->getUri(),
            'headers' => $this->getHeaders(),
        ];

        return new PlacetoPay($settings);
    }
}
