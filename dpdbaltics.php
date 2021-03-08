<?php

class DPDBaltics extends CarrierModule
{
    /**
     * Controller for displaying dpd links in prestashop's menu
     */
    const ADMIN_DPDBALTICS_MODULE_CONTROLLER = 'AdminDPDBalticsModule';

    const ADMIN_ZONES_CONTROLLER = 'AdminDPDBalticsZones';
    const ADMIN_PRODUCT_AVAILABILITY_CONTROLLER = 'AdminDPDBalticsProductsAvailability';
    const ADMIN_PRODUCTS_CONTROLLER = 'AdminDPDBalticsProducts';
    const ADMIN_SETTINGS_CONTROLLER = 'AdminDPDBalticsSettings';
    const ADMIN_SHIPMENT_SETTINGS_CONTROLLER = 'AdminDPDBalticsShipmentSettings';
    const ADMIN_IMPORT_EXPORT_CONTROLLER = 'AdminDPDBalticsImportExport';
    const ADMIN_PRICE_RULES_CONTROLLER = 'AdminDPDBalticsPriceRules';
    const ADMIN_ADDRESS_TEMPLATE_CONTROLLER = 'AdminDPDBalticsAddressTemplate';
    const ADMIN_AJAX_CONTROLLER = 'AdminDPDBalticsAjax';
    const ADMIN_PUDO_AJAX_CONTROLLER = 'AdminDPDBalticsPudoAjax';
    const ADMIN_REQUEST_SUPPORT_CONTROLLER = 'AdminDPDBalticsRequestSupport';
    const ADMIN_AJAX_SHIPMENTS_CONTROLLER = 'AdminDPDBalticsAjaxShipments';
    const ADMIN_LOGS_CONTROLLER = 'AdminDPDBalticsLogs';
    const ADMIN_SHIPMENT_CONTROLLER = 'AdminDPDBalticsShipment';
    const ADMIN_ORDER_RETURN_CONTROLLER = 'AdminDPDBalticsOrderReturn';
    const ADMIN_COLLECTION_REQUEST_CONTROLLER = 'AdminDPDBalticsCollectionRequest';
    const ADMIN_COURIER_REQUEST_CONTROLLER = 'AdminDPDBalticsCourierRequest';
    const ADMIN_AJAX_ON_BOARD_CONTROLLER = 'AdminDPDAjaxOnBoard';

    const DISABLE_CACHE = true;

    /**
     * Symfony DI Container
     **/
    private $moduleContainer;

    /**
     * Prestashop fills this property automatically with selected carrier ID in FO checkout
     *
     * @var int $id_carrier
     */
    public $id_carrier;


    public function __construct()
    {
        $this->name = 'dpdbaltics';
        $this->displayName = $this->l('DPDBaltics');
        $this->author = 'Invertus';
        $this->tab = 'shipping_logistics';
        $this->version = '3.1.5';
        $this->need_instance = 0;
        parent::__construct();

        $this->autoLoad();
        $this->compile();
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        /** @var Invertus\dpdBaltics\Install\Installer $installer */
        $installer = $this->getContainer()->get(Invertus\dpdBaltics\Install\Installer::class);
        if (!$installer->install()) {
            $this->_errors += $installer->getErrors();
            $this->uninstall();

            return false;
        }

        return true;
    }

    public function uninstall()
    {
        /** @var Invertus\dpdBaltics\Install\Installer $installer */
        $installer = $this->moduleContainer->get(Invertus\dpdBaltics\Install\Installer::class);
        if (!$installer->uninstall()) {
            $this->_errors += $installer->getErrors();
            return false;
        }

        if (!parent::uninstall()) {
            return false;
        }

        return true;
    }

    /**
     * @return array
     */
    public function getTabs()
    {
        /** @var Invertus\dpdBaltics\Service\TabService $tabsService */
        $tabsService = $this->getContainer()->get(Invertus\dpdBaltics\Service\TabService::class);

        return $tabsService->getTabsArray();
    }

    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink(self::ADMIN_SETTINGS_CONTROLLER));
    }

    /**
     * @return mixed
     */
    public function getContainer($id = false)
    {
        if ($id) {
            return $this->moduleContainer->get($id);
        }

        return $this->moduleContainer;
    }

    public function hookActionFrontControllerSetMedia()
    {
        $currentController = $this->context->controller->php_self !== null ?
            $this->context->controller->php_self :
            Tools::getValue('controller');

        if ('product' === $currentController) {
            $this->context->controller->addCSS($this->getPathUri() . 'views/css/front/product-carriers.css');
        }

        if (in_array($currentController, ['order', 'order-opc', 'ShipmentReturn'], true)) {
            $this->context->controller->addJS($this->getPathUri() . 'views/js/front/order.js');
            $this->context->controller->addJS($this->getPathUri() . 'views/js/front/order-input.js');
            $this->context->controller->addCSS($this->getPathUri() . 'views/css/front/order-input.css');
            /** @var Invertus\dpdBaltics\Service\Payment\PaymentService $paymentService */
            $paymentService = $this->getContainer(Invertus\dpdBaltics\Service\Payment\PaymentService::class);
            $cart = Context::getContext()->cart;
            $paymentService->filterPaymentMethods($cart);
            $paymentService->filterPaymentMethodsByCod($cart);

            /** @var Invertus\dpdBaltics\Repository\ProductRepository $productRepo */
            $productRepo = $this->getContainer(Invertus\dpdBaltics\Repository\ProductRepository::class);
            Media::addJsDef([
                'pudoCarriers' => Tools::jsonEncode($productRepo->getPudoProducts()),
                'currentController' => $currentController,
                'id_language' => $this->context->language->id,
                'id_shop' => $this->context->shop->id,
                'dpdAjaxLoaderPath' => $this->getPathUri() . 'views/img/ajax-loader-big.gif',
                'dpdPickupMarkerPath' => $this->getPathUri() . 'views/img/dpd-pick-up.png',
                'dpdLockerMarkerPath' => $this->getPathUri() . 'views/img/locker.png',
                'dpdHookAjaxUrl' => $this->context->link->getModuleLink($this->name, 'Ajax'),
                'pudoSelectSuccess' => $this->l('Pick-up point selected'),
            ]);

            $this->context->controller->addCSS($this->getPathUri() . 'views/css/front/pudo-shipment.css');
            /** @var Invertus\dpdBaltics\Service\GoogleApiService $googleApiService */
            $googleApiService = $this->getContainer(Invertus\dpdBaltics\Service\GoogleApiService::class);
            $this->context->controller->addJS($googleApiService->getFormattedGoogleMapsUrl());

            $this->context->controller->addJS($this->getPathUri() . 'views/js/front/pudo.js');
            $this->context->controller->addJS($this->getPathUri() . 'views/js/front/pudo-search.js');
        }
    }

    public function hookActionCarrierProcess(&$params)
    {
        /** @var Cart $cart */
        $cart = $params['cart'];
        $carrier = new Carrier($cart->id_carrier);
        $idShop = $this->context->shop->id;

        /** @var Invertus\dpdBaltics\Repository\CarrierRepository $carrierRepo */
        /** @var Invertus\dpdBaltics\Repository\ProductRepository $productRepo */
        $carrierRepo = $this->getContainer()->get(Invertus\dpdBaltics\Repository\CarrierRepository::class);
        $productRepo = $this->getContainer()->get(Invertus\dpdBaltics\Repository\ProductRepository::class);

        $carrierReference = $carrier->id_reference;
        $dpdCarriers = $carrierRepo->getDpdCarriers($idShop);
        $isDpdCarrier = false;
        foreach ($dpdCarriers as $dpdCarrier) {
            if ($carrierReference == $dpdCarrier['id_reference']) {
                $isDpdCarrier = true;
                $productId = $productRepo->getProductIdByCarrierReference($carrier->id_reference);
                $product = new DPDProduct($productId);
                $isSameDayDelivery = $product->product_reference === \Invertus\dpdBaltics\Config\Config::PRODUCT_TYPE_SAME_DAY_DELIVERY;
                break;
            }
        }
        if (!$isDpdCarrier) {
            return true;
        }

        if ($isSameDayDelivery) {
            /** @var \Invertus\dpdBaltics\Repository\PudoRepository $pudoRepo */
            $pudoRepo = $this->getContainer(\Invertus\dpdBaltics\Repository\PudoRepository::class);
            $pudoId = $pudoRepo->getIdByCart($cart->id);
            $selectedPudo = new DPDPudo($pudoId);
            if ($selectedPudo->city !== \Invertus\dpdBaltics\Config\Config::SAME_DAY_DELIVERY_CITY) {
                $this->context->controller->errors[] =
                    $this->l('This carrier can\'t deliver to your selected city');
                $params['completed'] = false;
                $selectedPudo->delete();

                return;
            }
        }
        if (!Tools::getValue('dpd-phone')) {
            $this->context->controller->errors[] =
                $this->l('In order to use DPD Carrier you need to enter phone number');
            $params['completed'] = false;

            return;
        }

        if (!Tools::getValue('dpd-phone-area')) {
            $this->context->controller->errors[] =
                $this->l('In order to use DPD Carrier you need to enter phone area');
            $params['completed'] = false;

            return;
        }

        /** @var Invertus\dpdBaltics\Service\CarrierPhoneService $carrierPhoneService */
        $carrierPhoneService = $this->getContainer()->get(Invertus\dpdBaltics\Service\CarrierPhoneService::class);

        if (!$carrierPhoneService->saveCarrierPhone(
            $this->context->cart->id,
            Tools::getValue('dpd-phone'),
            Tools::getValue('dpd-phone-area')
        )
        ) {
            $this->context->controller->errors[] = $this->l('Phone data is not saved');
            $params['completed'] = false;
        };

        /** @var \Invertus\dpdBaltics\Service\OrderDeliveryTimeService $orderDeliveryService */
        $orderDeliveryService = $this->getContainer()->get(\Invertus\dpdBaltics\Service\OrderDeliveryTimeService::class);

        $deliveryTime = Tools::getValue('dpd-delivery-time');
        if ($deliveryTime) {
            if (!$orderDeliveryService->saveDeliveryTime(
                $this->context->cart->id,
                $deliveryTime
            )) {
                $this->context->controller->errors[] = $this->l('Delivery time data is not saved');
                $params['completed'] = false;
            };
        }

        /** @var Cart $cart */
        $cart = $params['cart'];
        $carrier = new Carrier($cart->id_carrier);
        /** @var Invertus\dpdBaltics\Validate\Carrier\PudoValidate $pudoValidator */
        $pudoValidator = $this->getContainer(Invertus\dpdBaltics\Validate\Carrier\PudoValidate::class);
        if (!$pudoValidator->validatePickupPoints($cart->id, $carrier->id)) {
            $carrier = new Carrier($cart->id_carrier, $this->context->language->id);
            $this->context->controller->errors[] =
                sprintf($this->l('Please select pickup point for carrier: %s.'), $carrier->name);

            $params['completed'] = false;
        }
    }

    public function hookDisplayProductPriceBlock($params)
    {
        if ($params['type'] == 'after_price') {
            /** @var use Invertus\dpdBaltics\Builder\Template\Front\CarrierOptionsBuilder $carrierOptionsBuilder */
            $carrierOptionsBuilder = $this->getContainer()->get(Invertus\dpdBaltics\Builder\Template\Front\CarrierOptionsBuilder::class);
            return $carrierOptionsBuilder->renderCarrierOptionsInProductPage();
        }
    }

    public function hookDisplayBackOfficeTop()
    {
        if ($this->context->controller instanceof Invertus\dpdBaltics\Controller\AbstractAdminController &&
            Configuration::get(Invertus\dpdBaltics\Config\Config::ON_BOARD_TURNED_ON) &&
            Configuration::get(Invertus\dpdBaltics\Config\Config::ON_BOARD_STEP)
        ) {
            /** @var OnBoardService $onBoardService */
            $onBoardService = $this->getContainer(Invertus\dpdBaltics\OnBoard\Service\OnBoardService::class);
            return $onBoardService->makeStepActionWithTemplateReturn();
        }
    }

    public function getOrderShippingCost($cart, $shippingCost)
    {
        return $this->getOrderShippingCostExternal($cart);
    }

    /**
     * @param $cart Cart
     * @return bool|float
     */
    public function getOrderShippingCostExternal($cart)
    {
        // This method is still called when module is disabled so we need to do a manual check here
        if (!$this->active) {
            return false;
        }

        $carrier = new Carrier($this->id_carrier);
        if ($this->context->controller->ajax && Tools::getValue('id_address_delivery')) {
            $cart->id_address_delivery = (int)Tools::getValue('id_address_delivery');
        }

        $deliveryAddress = new Address($cart->id_address_delivery);

        /** @var Invertus\dpdBaltics\Repository\ProductRepository $productRepo */
        /** @var Invertus\dpdBaltics\Repository\ZoneRepository $zoneRepo */
        /** @var \Invertus\dpdBaltics\Service\Product\ProductAvailabilityService $productAvailabilityService */
        /** @var \Invertus\dpdBaltics\Validate\Weight\CartWeightValidator $cartWeightValidator */
        $productRepo = $this->getContainer()->get(Invertus\dpdBaltics\Repository\ProductRepository::class);
        $zoneRepo = $this->getContainer()->get(Invertus\dpdBaltics\Repository\ZoneRepository::class);

        $productAvailabilityService = $this->getContainer(\Invertus\dpdBaltics\Service\Product\ProductAvailabilityService::class);
        $cartWeightValidator = $this->getContainer(\Invertus\dpdBaltics\Validate\Weight\CartWeightValidator::class);

        /** @var \Invertus\dpdBaltics\Provider\CurrentCountryProvider $currentCountryProvider */
        $currentCountryProvider = $this->getContainer(\Invertus\dpdBaltics\Provider\CurrentCountryProvider::class);
        $countryCode = $currentCountryProvider->getCurrentCountryIsoCode($cart);

        if (!$productAvailabilityService->checkIfCarrierIsAvailable($carrier->id_reference)) {
            return false;
        }

        try {
            $carrierZones = $zoneRepo->findZonesIdsByCarrierReference($carrier->id_reference);
            $isShopAvailable = $productRepo->checkIfCarrierIsAvailableInShop($carrier->id_reference, $this->context->shop->id);
            $serviceCarrier = $productRepo->findProductByCarrierReference($carrier->id_reference);
        } catch (Exception $e) {
            $tplVars = [
                'errorMessage' => $this->l('Something went wrong while collecting DPD carrier data'),
            ];
            $this->context->smarty->assign($tplVars);

            return $this->context->smarty->fetch(
                $this->getLocalPath() . 'views/templates/admin/dpd-shipment-fatal-error.tpl'
            );
        }

        if (!$cartWeightValidator->validate($cart->getTotalWeight(), $countryCode, $serviceCarrier['product_reference'])) {
            return false;
        }

        if ((bool)$serviceCarrier['is_home_collection']) {
            return false;
        }

        if (!DPDZone::checkAddressInZones($deliveryAddress, $carrierZones)) {
            return false;
        }

        if (empty($isShopAvailable)) {
            return false;
        }

        if ($serviceCarrier['product_reference'] === \Invertus\dpdBaltics\Config\Config::PRODUCT_TYPE_SAME_DAY_DELIVERY) {
            $isSameDayAvailable = \Invertus\dpdBaltics\Util\ProductUtility::validateSameDayDelivery(
                $countryCode,
                $deliveryAddress->city
            );

            if (!$isSameDayAvailable) {
                return false;
            }
        }

        if ($serviceCarrier['is_pudo']) {
            /** @var \Invertus\dpdBaltics\Service\Parcel\ParcelShopService $parcelShopsService */
            $parcelShopsService = $this->getContainer()->get(\Invertus\dpdBaltics\Service\Parcel\ParcelShopService::class);
            $shops = $parcelShopsService->getParcelShopsByCountryAndCity($countryCode, $deliveryAddress->city);
            if (!$shops) {
                return false;
            }
        }

        /** @var Invertus\dpdBaltics\Repository\PriceRuleRepository $priceRuleRepository */
        $priceRuleRepository = $this->getContainer()->get(Invertus\dpdBaltics\Repository\PriceRuleRepository::class);

        // Get all price rules for current carrier
        $priceRulesIds =
            $priceRuleRepository->getByCarrierReference(
                $deliveryAddress,
                $carrier->id_reference
            );
        /** @var Invertus\dpdBaltics\Service\PriceRuleService $priceRuleService */
        $priceRuleService = $this->getContainer()->get(Invertus\dpdBaltics\Service\PriceRuleService::class);

        return $priceRuleService->applyPriceRuleForCarrier($cart, $priceRulesIds, $this->context->shop->id);
    }

    public function hookExtraCarrier($params)
    {
        /** @var Cart $cart */
        $cart = $params['cart'];
        $carrier = new Carrier($cart->id_carrier, $this->context->language->id);

        /** @var \Invertus\dpdBaltics\Provider\CurrentCountryProvider $currentCountryProvider */
        $currentCountryProvider = $this->getContainer(\Invertus\dpdBaltics\Provider\CurrentCountryProvider::class);
        $countryCode = $currentCountryProvider->getCurrentCountryIsoCode($cart);

        $deliveryAddress = new Address($cart->id_address_delivery);
        $idShop = $this->context->shop->id;

        /**
         * @var $carrierRepo \Invertus\dpdBaltics\Repository\CarrierRepository
         */
        $carrierRepo = $this->getContainer()->get(Invertus\dpdBaltics\Repository\CarrierRepository::class);
        $carrierReference = $carrier->id_reference;
        $dpdCarriers = $carrierRepo->getDpdCarriers($idShop);
        $isDpdCarrier = false;
        foreach ($dpdCarriers as $dpdCarrier) {
            if ($carrierReference == $dpdCarrier['id_reference']) {
                $isDpdCarrier = true;
                break;
            }
        }
        if (!$isDpdCarrier) {
            return;
        }

        /** @var \Invertus\dpdBaltics\Service\CarrierPhoneService $carrierPhoneService */
        /** @var \Invertus\dpdBaltics\Presenter\DeliveryTimePresenter $deliveryTimePresenter */
        /** @var \Invertus\dpdBaltics\Repository\ProductRepository $productRepo */
        $carrierPhoneService = $this->getContainer()->get(\Invertus\dpdBaltics\Service\CarrierPhoneService::class);
        $deliveryTimePresenter = $this->getContainer()->get(\Invertus\dpdBaltics\Presenter\DeliveryTimePresenter::class);
        $productRepo = $this->getContainer()->get(\Invertus\dpdBaltics\Repository\ProductRepository::class);

        $productId = $productRepo->getProductIdByCarrierReference($carrier->id_reference);
        $dpdProduct = new DPDProduct($productId);
        $return = '';
        if ($dpdProduct->getProductReference() === \Invertus\dpdBaltics\Config\Config::PRODUCT_TYPE_SAME_DAY_DELIVERY) {
            /** @var \Invertus\dpdBaltics\Presenter\SameDayDeliveryMessagePresenter $sameDayDeliveryPresenter */
            $sameDayDeliveryPresenter = $this->getContainer()->get(\Invertus\dpdBaltics\Presenter\SameDayDeliveryMessagePresenter::class);
            $return .= $sameDayDeliveryPresenter->getSameDayDeliveryMessageTemplate();
        }
        $return .= $carrierPhoneService->getCarrierPhoneTemplate($this->context->cart->id);
        if ($dpdProduct->getProductReference() === \Invertus\dpdBaltics\Config\Config::PRODUCT_TYPE_B2B ||
            $dpdProduct->getProductReference() === \Invertus\dpdBaltics\Config\Config::PRODUCT_TYPE_B2B_COD
        ) {
            $return .= $deliveryTimePresenter->getDeliveryTimeTemplate($countryCode, $deliveryAddress->city);
        }

        /** @var \Invertus\dpdBaltics\Repository\ProductRepository $productRep */
        $productRep = $this->getContainer(\Invertus\dpdBaltics\Repository\ProductRepository::class);
        $isPudo = $productRep->isProductPudo($carrier->id_reference);
        if ($isPudo) {
            /** @var \Invertus\dpdBaltics\Repository\PudoRepository $pudoRepo */
            /** @var \Invertus\dpdBaltics\Repository\ParcelShopRepository $parcelShopRepo */
            /** @var \Invertus\dpdBaltics\Repository\ProductRepository $productRepo */
            $pudoRepo = $this->getContainer(\Invertus\dpdBaltics\Repository\PudoRepository::class);
            $parcelShopRepo = $this->getContainer(\Invertus\dpdBaltics\Repository\ParcelShopRepository::class);
            $product = $productRepo->findProductByCarrierReference($carrier->id_reference);
            $isSameDayDelivery = ($product['product_reference'] === \Invertus\dpdBaltics\Config\Config::PRODUCT_TYPE_SAME_DAY_DELIVERY);

            $pudoId = $pudoRepo->getIdByCart($cart->id);
            $selectedPudo = new DPDPudo($pudoId);

            /** @var \Invertus\dpdBaltics\Service\Parcel\ParcelShopService $parcelShopService */
            $parcelShopService= $this->getContainer(\Invertus\dpdBaltics\Service\Parcel\ParcelShopService::class);

            $selectedCity = null;
            $selectedStreet = null;
            try {
                if (Validate::isLoadedObject($selectedPudo) && !$isSameDayDelivery) {
                    $selectedCity = $selectedPudo->city;
                    $selectedStreet = $selectedPudo->street;
                    $parcelShops = $parcelShopService->getParcelShopsByCountryAndCity($countryCode, $selectedCity);
                    $parcelShops = $parcelShopService->moveSelectedShopToFirst($parcelShops, $selectedStreet);
                } else {
                    $selectedCity = $deliveryAddress->city;
                    $selectedStreet = $deliveryAddress->address1;
                    $parcelShops = $parcelShopService->getParcelShopsByCountryAndCity($countryCode, $selectedCity);
                    $parcelShops = $parcelShopService->moveSelectedShopToFirst($parcelShops, $selectedStreet);
                }
            } catch (\Invertus\dpdBalticsApi\Exception\DPDBalticsAPIException $e) {
                /** @var \Invertus\dpdBaltics\Service\Exception\ExceptionService $exceptionService */
                $exceptionService = $this->getContainer(\Invertus\dpdBaltics\Service\Exception\ExceptionService::class);
                $tplVars = [
                    'errorMessage' => $exceptionService->getErrorMessageForException(
                        $e,
                        $exceptionService->getAPIErrorMessages()
                    )
                ];
                $this->context->smarty->assign($tplVars);

                return $this->context->smarty->fetch(
                    $this->getLocalPath() . 'views/templates/admin/dpd-shipment-fatal-error.tpl'
                );
            } catch (Exception $e) {
                $tplVars = [
                    'errorMessage' => $this->l("Something went wrong. We couldn't find parcel shops."),
                ];
                $this->context->smarty->assign($tplVars);

                return $this->context->smarty->fetch(
                    $this->getLocalPath() . 'views/templates/admin/dpd-shipment-fatal-error.tpl'
                );
            }

            /** @var \Invertus\dpdBaltics\Service\PudoService $pudoService */
            $pudoService = $this->getContainer(\Invertus\dpdBaltics\Service\PudoService::class);

            $pudoServices = $pudoService->setPudoServiceTypes($parcelShops);
            $pudoServices = $pudoService->formatPudoServicesWorkHours($pudoServices);

            if (isset($parcelShops[0])) {
                $coordinates = [
                    'lat' => $parcelShops[0]->getLatitude(),
                    'lng' => $parcelShops[0]->getLongitude(),
                ];
                $this->context->smarty->assign(
                    [
                        'coordinates' => $coordinates
                    ]
                );
            }

            if ($isSameDayDelivery) {
                $cityList['Rīga'] = 'Rīga';
            } else {
                $cityList = $parcelShopRepo->getAllCitiesByCountryCode($countryCode);
            }

            if (!in_array($selectedCity, $cityList) && isset($parcelShops[0])) {
                $selectedCity = $parcelShops[0]->getCity();
            }
            $streetList = $parcelShopRepo->getAllAddressesByCountryCodeAndCity($countryCode, $selectedCity);
            $this->context->smarty->assign(
                [
                    'carrierId' => $carrier->id,
                    'pickUpMap' => Configuration::get(\Invertus\dpdBaltics\Config\Config::PICKUP_MAP),
                    'pudoId' => $pudoId,
                    'pudoServices' => $pudoServices,
                    'dpd_pickup_logo' => $this->getPathUri() . 'views/img/pickup.png',
                    'dpd_locker_logo' => $this->getPathUri() . 'views/img/locker.png',
                    'delivery_address' => $deliveryAddress,
                    'saved_pudo_id' => $selectedPudo->pudo_id,
                    'is_pudo' => (bool)$isPudo,
                    'city_list' => $cityList,
                    'selected_city' => $selectedCity,
                    'show_shop_list' => Configuration::get(\Invertus\dpdBaltics\Config\Config::PARCEL_SHOP_DISPLAY),
                    'street_list' => $streetList,
                    'selected_street' => $selectedStreet,
                ]
            );

            $return .= $this->context->smarty->fetch(
                $this->getLocalPath() . '/views/templates/hook/front/pudo-points.tpl'
            );
        }

        return $return;
    }

    /**
     * Includes Vendor Autoload.
     */
    private function autoLoad()
    {
        require_once $this->getLocalPath() . 'vendor/autoload.php';
    }

    private function compile()
    {
        $containerCache = $this->getLocalPath() . 'var/cache/container.php';
        $containerConfigCache = new Symfony\Component\Config\ConfigCache($containerCache, self::DISABLE_CACHE);
        $containerClass = get_class($this) . 'Container';
        if (!$containerConfigCache->isFresh()) {
            $containerBuilder = new Symfony\Component\DependencyInjection\ContainerBuilder();
            $locator = new Symfony\Component\Config\FileLocator($this->getLocalPath() . 'config');
            $loader = new Symfony\Component\DependencyInjection\Loader\YamlFileLoader($containerBuilder, $locator);
            $loader->load('config.yml');
            $containerBuilder->compile();
            $dumper = new Symfony\Component\DependencyInjection\Dumper\PhpDumper($containerBuilder);
            $containerConfigCache->write(
                $dumper->dump(['class' => $containerClass]),
                $containerBuilder->getResources()
            );
        }
        require_once $containerCache;
        $this->moduleContainer = new $containerClass();
    }

    public function hookActionAdminControllerSetMedia()
    {
        if ($this->isVersion161()) {
            $this->context->controller->addCSS($this->getPathUri().'views/css/admin/menu.css');
        }

        $currentController = Tools::getValue('controller');

        if ('AdminOrders' === $currentController) {
            $this->context->controller->addJS($this->getPathUri() . 'views/js/front/order-input.js');
            $this->context->controller->addCSS($this->getPathUri() . 'views/css/admin/order/order-list.css');
            $this->context->controller->addJS($this->getPathUri() . 'views/js/admin/order_list.js');
            Media::addJsDef(
                [
                    'dpdHookAjaxShipmentController' => $this->context->link->getAdminLink(self::ADMIN_AJAX_SHIPMENTS_CONTROLLER),
                    'shipmentIsBeingPrintedMessage' => $this->context->smarty->fetch($this->getLocalPath() . 'views/templates/admin/partials/spinner.tpl') .
                        $this->l('Your labels are being saved please stay on the page'),
                    'noOrdersSelectedMessage' => $this->l('No orders were selected'),
                    'downloadSelectedLabelsButton' => $this->context->smarty->fetch($this->getLocalPath() . 'views/templates/admin/partials/download-selected-labels-button.tpl'),
                ]
            );
            $this->context->controller->addCSS($this->getPathUri() . 'views/css/admin/order/order-list.css');
            $this->context->controller->addJS($this->getPathUri() . 'views/js/admin/order_list.js');
        }

        if ('AdminOrders' === $currentController && Tools::isSubmit('vieworder')) {
            $this->context->controller->addJS($this->getPathUri() . 'views/js/admin/order_expand_form.js');
            $this->context->controller->addJS($this->getPathUri() . 'views/js/admin/shipment.js');
            $this->context->controller->addJS($this->getPathUri() . 'views/js/admin/init-chosen.js');

            Media::addJsDef([
                'expandText' => $this->l('Expand'),
                'collapseText' => $this->l('Collapse'),
                'dpdAjaxShipmentsUrl' =>
                    $this->context->link->getAdminLink(self::ADMIN_AJAX_SHIPMENTS_CONTROLLER),
                'dpdMessages' => [
                    'invalidProductQuantity' => $this->l('Invalid product quantity entered'),
                    'invalidShipment' => $this->l('Invalid shipment selected'),
                    'parcelsLimitReached' => $this->l('Parcels limit reached in shipment'),
                    'successProductMove' => $this->l('Product moved successfully'),
                    'successCreation' => $this->l('Successful creation'),
                    'unexpectedError' => $this->l('Unexpected error appeared.'),
                    'invalidPrintoutFormat' => $this->l('Invalid printout format selected.'),
                    'cannotOpenWindow' => $this->l('Cannot print label, your browser may be blocking it.'),
                    'dpdRecipientAddressError' => $this->l('Please fill required fields')
                ],
                'id_language' => $this->context->language->id,
                'id_shop' => $this->context->shop->id,
                'id_cart' => $this->context->cart->id,
                'currentController' => $currentController,

            ]);

            $this->context->controller->addJS($this->getPathUri() . 'views/js/admin/carrier_phone.js');
            $this->context->controller->addJS($this->getPathUri() . 'views/js/admin/custom_select.js');
            $this->context->controller->addJS($this->getPathUri() . 'views/js/admin/label_position.js');
            $this->context->controller->addJS($this->getPathUri() . 'views/js/admin/pudo.js');
            $this->context->controller->addCSS($this->getPathUri() . 'views/css/admin/customSelect/custom-select.css');
            $this->context->controller->addCSS($this->getPathUri() . 'views/css/admin/order/admin-orders-controller.css');
            $this->context->controller->addCSS($this->getPathUri() . 'views/css/admin/order/quantity-max-value-tip.css');
        }
        if ('AdminOrders' === $currentController && Tools::isSubmit('addorder')) {
            /** @var \Invertus\dpdBaltics\Repository\ProductRepository $productRepo */
            $productRepo = $this->getContainer(\Invertus\dpdBaltics\Repository\ProductRepository::class);
            Media::addJsDef([
                'dpdFrontController' => false,
                'pudoCarriers' => Tools::jsonEncode($productRepo->getPudoProducts()),
                'currentController' => $currentController,
                'dpdAjaxShipmentsUrl' =>
                    $this->context->link->getAdminLink(self::ADMIN_AJAX_SHIPMENTS_CONTROLLER),
                'ignoreAdminController' => true,
                'dpdAjaxPudoUrl' => $this->context->link->getAdminLink(self::ADMIN_PUDO_AJAX_CONTROLLER),
                'id_shop' => $this->context->shop->id,
            ]);
            $this->context->controller->addJS($this->getPathUri() . 'views/js/admin/pudo_list.js');
            $this->context->controller->addJS($this->getPathUri() . 'views/js/admin/carrier_phone.js');
            $this->context->controller->addJS($this->getPathUri() . 'views/js/admin/pudo.js');

            $this->context->controller->addCSS($this->getPathUri() . 'views/css/admin/order/admin-orders-controller.css');

            return;
        }
    }

    public function isVersion161()
    {
        return (bool) version_compare(_PS_VERSION_, '1.6.1.0', '>=');
    }

    public function isVersion16()
    {
        return (bool) version_compare(_PS_VERSION_, '1.6', '>=');
    }

    public function hookDisplayAdminOrder(array $params)
    {
        $order = new Order($params['id_order']);
        $cart = new Cart($params['cart']->id);

        /** @var \Invertus\dpdBaltics\Repository\ProductRepository $productRepo */
        $productRepo = $this->getContainer(\Invertus\dpdBaltics\Repository\ProductRepository::class);
        $carrier = new Carrier($order->id_carrier);
        if (!$productRepo->getProductIdByCarrierReference($carrier->id_reference)) {
            return;
        }

        /** @var \Invertus\dpdBaltics\Repository\ShipmentRepository $shipmentRepository */
        $shipmentRepository = $this->getContainer(\Invertus\dpdBaltics\Repository\ShipmentRepository::class);
        $shipmentId = $shipmentRepository->getIdByOrderId($order->id);
        $shipment = new DPDShipment($shipmentId);

        $dpdCodWarning = false;

        /** @var \Invertus\dpdBaltics\Service\OrderService $orderService */
        $orderService = $this->getContainer(\Invertus\dpdBaltics\Service\OrderService::class);
        $orderDetails = $orderService->getOrderDetails($order, $cart->id_lang);

        $customAddresses = [];

        /** @var \Invertus\dpdBaltics\Repository\ReceiverAddressRepository $receiverAddressRepository */
        $receiverAddressRepository = $this->getContainer(\Invertus\dpdBaltics\Repository\ReceiverAddressRepository::class);
        $customOrderAddressesIds = $receiverAddressRepository->getAddressIdByOrderId($order->id);
        foreach ($customOrderAddressesIds as $customOrderAddressId) {
            $customAddress = new Address($customOrderAddressId);

            $customAddresses[] = [
                'id_address' => $customAddress->id,
                'alias' => $customAddress->alias
            ];
        }
        $combinedCustomerAddresses = array_merge($orderDetails['customer']['addresses'], $customAddresses);

        /** @var \Invertus\dpdBaltics\Repository\PhonePrefixRepository $phonePrefixRepository */
        $phonePrefixRepository = $this->getContainer(\Invertus\dpdBaltics\Repository\PhonePrefixRepository::class);

        $products = $cart->getProducts();

        /** @var \Invertus\dpdBaltics\Repository\ProductRepository $productRepository */
        $productRepository = $this->getContainer(\Invertus\dpdBaltics\Repository\ProductRepository::class);
        $dpdProducts = $productRepository->getAllProducts();
        $dpdProducts->where('active', '=', 1);

        /** @var \Invertus\dpdBaltics\Service\Label\LabelPositionService $labelPositionService */
        $labelPositionService = $this->getContainer(\Invertus\dpdBaltics\Service\Label\LabelPositionService::class);
        $labelPositionService->assignLabelPositions($shipmentId);
        $labelPositionService->assignLabelFormat($shipmentId);

        /** @var \Invertus\dpdBaltics\Service\Payment\PaymentService $paymentService */
        $paymentService = $this->getContainer(\Invertus\dpdBaltics\Service\Payment\PaymentService::class);
        try {
            $isCodPayment = $paymentService->isOrderPaymentCod($order->module);
        } catch (Exception $e) {
            $tplVars = [
                'errorMessage' => $this->l('Something went wrong checking payment method'),
            ];
            $this->context->smarty->assign($tplVars);

            return $this->context->smarty->fetch(
                $this->getLocalPath() . 'views/templates/admin/dpd-shipment-fatal-error.tpl'
            );
        }

        if ($isCodPayment) {
            $dpdProducts->where('is_cod', '=', 1);
        } else {
            $dpdProducts->where('is_cod', '=', 0);
        }
        /** @var \Invertus\dpdBaltics\Repository\PudoRepository $pudoRepo */
        $pudoRepo = $this->getContainer(\Invertus\dpdBaltics\Repository\PudoRepository::class);
        $selectedProduct = new DPDProduct($shipment->id_service);
        $isPudo = $selectedProduct->is_pudo;
        $pudoId = $pudoRepo->getIdByCart($order->id_cart);

        $selectedPudo = new DPDPudo($pudoId);

        /** @var \Invertus\dpdBaltics\Service\Parcel\ParcelShopService $parcelShopService */
        /** @var \Invertus\dpdBaltics\Repository\ParcelShopRepository $parcelShopRepo */
        $parcelShopService= $this->getContainer(\Invertus\dpdBaltics\Service\Parcel\ParcelShopService::class);
        $parcelShopRepo = $this->getContainer(\Invertus\dpdBaltics\Repository\ParcelShopRepository::class);

        /** @var \Invertus\dpdBaltics\Provider\CurrentCountryProvider $currentCountryProvider */
        $currentCountryProvider = $this->getContainer(\Invertus\dpdBaltics\Provider\CurrentCountryProvider::class);
        $countryCode = $currentCountryProvider->getCurrentCountryIsoCode($cart);

        $selectedCity = null;
        try {
            if ($pudoId) {
                $selectedCity = $selectedPudo->city;
                $parcelShops = $parcelShopService->getParcelShopsByCountryAndCity(
                    $countryCode,
                    $selectedCity
                );
            } else {
                $parcelShops = $parcelShopService->getParcelShopsByCountryAndCity(
                    $countryCode,
                    $orderDetails['order_address']['city']
                );
            }
        } catch (Exception $e) {
            $tplVars = [
                'errorMessage' => $this->l('Fatal error while searching for parcel shops: ') . $e->getMessage(),
            ];
            $this->context->smarty->assign($tplVars);

            return $this->context->smarty->fetch(
                $this->getLocalPath() . 'views/templates/admin/dpd-shipment-fatal-error.tpl'
            );
        }

        /** @var null|\Invertus\dpdBalticsApi\Api\DTO\Object\ParcelShop $selectedPudoService */
        $selectedPudoService = null;
        $hasParcelShops = false;
        if ($parcelShops) {
            if ($selectedPudo->pudo_id) {
                $selectedPudoService = $parcelShopService->getParcelShopByShopId($selectedPudo->pudo_id)[0];
            } else {
                $selectedPudoService = $parcelShops[0];
            }
            $hasParcelShops = true;
        }

        if ($selectedPudoService) {
            /** @var \Invertus\dpdBaltics\Service\PudoService $pudoService */
            $pudoService = $this->getContainer(\Invertus\dpdBaltics\Service\PudoService::class);
            $selectedPudoService->setOpeningHours(
                $pudoService->formatPudoServiceWorkHours($selectedPudoService->getOpeningHours())
            );
        }

        /** @var DPDProduct $dpdProduct */
        foreach ($dpdProducts as $dpdProduct) {
            if ($dpdProduct->is_pudo && !$hasParcelShops) {
                $dpdProduct->active = 0;
            }
        }
        $cityList = $parcelShopRepo->getAllCitiesByCountryCode($countryCode);

        if (\Invertus\dpdBaltics\Config\Config::productHasDeliveryTime($selectedProduct->product_reference)) {
            /** @var \Invertus\dpdBaltics\Repository\OrderDeliveryTimeRepository $orderDeliveryTimeRepo */
            $orderDeliveryTimeRepo = $this->getContainer()->get(\Invertus\dpdBaltics\Repository\OrderDeliveryTimeRepository::class);
            $orderDeliveryTimeId = $orderDeliveryTimeRepo->getOrderDeliveryIdByCartId($cart->id);
            if ($orderDeliveryTimeId) {
                $orderDeliveryTime = new DPDOrderDeliveryTime($orderDeliveryTimeId);
                $this->context->smarty->assign([
                    'orderDeliveryTime' => $orderDeliveryTime->delivery_time,
                    'deliveryTimes' => \Invertus\dpdBaltics\Config\Config::getDeliveryTimes($countryCode)
                ]);
            }
        }

        Media::addJsDef(
            [
                'shipment' => $shipment
            ]
        );
        $tplVars = [
            'dpdLogoUrl' => $this->getPathUri() . 'views/img/DPDLogo.gif',
            'shipment' => $shipment,
            'testOrder' => $shipment->is_test,
            'total_products' => 1,
            'contractPageLink' => $this->context->link->getAdminLink(self::ADMIN_PRODUCTS_CONTROLLER),
            'dpdCodWarning' => $dpdCodWarning,
            'testMode' => Configuration::get(\Invertus\dpdBaltics\Config\Config::SHIPMENT_TEST_MODE),
            'printLabelOption' => Configuration::get(\Invertus\dpdBaltics\Config\Config::LABEL_PRINT_OPTION),
            'defaultLabelFormat' => Configuration::get(\Invertus\dpdBaltics\Config\Config::DEFAULT_LABEL_FORMAT),
            'combinedAddresses' => $combinedCustomerAddresses,
            'orderDetails' => $orderDetails,
            'mobilePhoneCodeList' => $phonePrefixRepository->getCallPrefixes(),
            'products' => $products,
            'dpdProducts' => $dpdProducts,
            'isCodPayment' => $isCodPayment,
            'is_pudo' => (bool)$isPudo,
            'selectedPudo' => $selectedPudoService,
            'city_list' => $cityList,
            'selected_city' => $selectedCity,
            'has_parcel_shops' => $hasParcelShops,
            'receiverAddressCountries' => Country::getCountries($this->context->language->id, true),
            'documentReturnEnabled' => Configuration::get(\Invertus\dpdBaltics\Config\Config::DOCUMENT_RETURN),
        ];

        $this->context->smarty->assign($tplVars);
        $test = $this->context->smarty->fetch(
            $this->getLocalPath() . 'views/templates/hook/admin/admin-order.tpl'
        );
        return $this->context->smarty->fetch(
            $this->getLocalPath() . 'views/templates/hook/admin/admin-order.tpl'
        );
    }

    public function hookActionValidateOrder($params)
    {
        $carrier = new Carrier($params['order']->id_carrier);
        if ($carrier->external_module_name !== $this->name) {
            return;
        }
        $isAdminOrderPage = 'AdminOrders' === Tools::getValue('controller') ;
        $isAdminNewOrderForm = Tools::isSubmit('addorder') || Tools::isSubmit('cart_summary');

        if ($isAdminOrderPage && $isAdminNewOrderForm) {
            $dpdPhone = Tools::getValue('dpd-phone');
            $dpdPhoneArea = Tools::getValue('dpd-phone-area');

            /** @var \Invertus\dpdBaltics\Service\CarrierPhoneService $carrierPhoneService */
            $carrierPhoneService = $this->getContainer(\Invertus\dpdBaltics\Service\CarrierPhoneService::class);

            if (!empty($dpdPhone) && !empty($dpdPhoneArea)) {
                if (!$carrierPhoneService->saveCarrierPhone($this->context->cart->id, $dpdPhone, $dpdPhoneArea)) {
                    $error = $this->l('Phone data is not saved');
                    die($error);
                }
            }

            /** @var \Invertus\dpdBaltics\Service\OrderDeliveryTimeService $orderDeliveryService */
            $orderDeliveryService = $this->getContainer(\Invertus\dpdBaltics\Service\OrderDeliveryTimeService::class);
            $deliveryTime = Tools::getValue('dpd-delivery-time');

            if ($deliveryTime !== null) {
                if (!$orderDeliveryService->saveDeliveryTime(
                    $this->context->cart->id,
                    $deliveryTime
                )) {
                    $error = $this->l('Delivery time is not saved');
                    die($error);
                };
            }
        }

        /** @var Invertus\dpdBaltics\Service\ShipmentService $shipmentService */
        $shipmentService = $this->getContainer(Invertus\dpdBaltics\Service\ShipmentService::class);
        $shipmentService->createShipmentFromOrder($params['order']);
    }

    private function printLabel($idShipment)
    {
        /** @var Invertus\dpdBaltics\Service\API\LabelApiService $labelApiService */
        $labelApiService = $this->getContainer(Invertus\dpdBaltics\Service\API\LabelApiService::class);

        $shipment = new DPDShipment($idShipment);
        $format = $shipment->label_format;
        $position = $shipment->label_position;

        try {
            /** @var Invertus\dpdBalticsApi\Api\DTO\Response\ParcelPrintResponse $parcelPrintResponse */
            $parcelPrintResponse = $labelApiService->printLabel($shipment->pl_number, $format, $position);
        } catch (Invertus\dpdBalticsApi\Exception\DPDBalticsAPIException $e) {
            /** @var Invertus\dpdBaltics\Service\Exception\ExceptionService $exceptionService */
            $exceptionService = $this->getContainer(Invertus\dpdBaltics\Service\Exception\ExceptionService::class);
            Context::getContext()->controller->errors[] = $exceptionService->getErrorMessageForException(
                $e,
                $exceptionService->getAPIErrorMessages()
            );
            return;
        } catch (Exception $e) {
            Context::getContext()->controller->errors[] = $this->l('Failed to print label: ') . $e->getMessage();
            return;
        }

        if ($parcelPrintResponse->getStatus() === Invertus\dpdBaltics\Config\Config::API_SUCCESS_STATUS) {
            $this->updateOrderCarrier($idShipment);
            return;
        }

        Context::getContext()->controller->errors[] = $this->l($parcelPrintResponse->getErrLog());
    }

    private function printMultipleLabels($shipmentIds)
    {
        $plNumbers = [];
        foreach ($shipmentIds as $shipmentId) {
            $shipment = new DPDShipment($shipmentId);
            $plNumbers[] = $shipment->pl_number;
        }

        /** @var Invertus\dpdBaltics\Service\API\LabelApiService $labelApiService */
        $labelApiService = $this->getContainer(Invertus\dpdBaltics\Service\API\LabelApiService::class);

        $position = Configuration::get(Invertus\dpdBaltics\Config\Config::DEFAULT_LABEL_POSITION);
        $format = Configuration::get(Invertus\dpdBaltics\Config\Config::DEFAULT_LABEL_FORMAT);

        try {
            /** @var Invertus\dpdBalticsApi\Api\DTO\Response\ParcelPrintResponse $parcelPrintResponse */
            $parcelPrintResponse = $labelApiService->printLabel(implode('|', $plNumbers), $format, $position);
        } catch (Invertus\dpdBalticsApi\Exception\DPDBalticsAPIException $e) {
            /** @var Invertus\dpdBaltics\Service\Exception\ExceptionService $exceptionService */
            $exceptionService = $this->getContainer(Invertus\dpdBaltics\Service\Exception\ExceptionService::class);
            Context::getContext()->controller->errors[] = $exceptionService->getErrorMessageForException(
                $e,
                $exceptionService->getAPIErrorMessages()
            );
            return;
        } catch (Exception $e) {
            Context::getContext()->controller->errors[] = $this->l('Failed to print label: ') . $e->getMessage();
            return;
        }

        if ($parcelPrintResponse->getStatus() === Invertus\dpdBaltics\Config\Config::API_SUCCESS_STATUS) {
            foreach ($shipmentIds as $shipmentId) {
                $this->updateOrderCarrier($shipmentId);
            }
            return;
        }

        Context::getContext()->controller->errors[] = $this->l($parcelPrintResponse->getErrLog());
    }

    private function updateOrderCarrier($shipmentId)
    {
        $shipment = new DPDShipment($shipmentId);
        /** @var Invertus\dpdBaltics\Repository\OrderRepository $orderRepo */
        /** @var Invertus\dpdBaltics\Service\TrackingService $trackingService */
        $orderRepo = $this->getContainer(Invertus\dpdBaltics\Repository\OrderRepository::class);
        $trackingService = $this->getContainer(Invertus\dpdBaltics\Service\TrackingService::class);
        $orderCarrierId = $orderRepo->getOrderCarrierId($shipment->id_order);

        $orderCarrier = new OrderCarrier($orderCarrierId);
        $orderCarrier->tracking_number = $trackingService->getTrackingNumber($shipment->pl_number);

        try {
            $orderCarrier->update();
        } catch (Exception $e) {
            Context::getContext()->controller->errors[] =
                $this->l('Failed to save tracking number: ') . $e->getMessage();
            return;
        }

        $shipment->printed_label = 1;
        $shipment->date_print = date('Y-m-d H:i:s');
        $shipment->update();
    }

    public function hookActionDispatcher(array $params)
    {
        if (Dispatcher::FC_ADMIN !== $params['controller_type'] ||
            'AdminOrdersController' !== $params['controller_class']
        ) {
            return;
        }

        //TODO Check why there is not functionality to print parcel distributed shipments
        if (Tools::isSubmit('print_label')) {
            $idShipment = Tools::getValue('id_dpd_shipment');
            $this->printLabel($idShipment);
            return;
        }

        if (Tools::isSubmit('print_multiple_labels')) {
            $shipmentIds = json_decode(Tools::getValue('shipment_ids'));
            $this->printMultipleLabels($shipmentIds);
            return;
        }
    }

    public function hookDisplayOrderDetail($params)
    {
        $isReturnServiceEnabled = Configuration::get(Invertus\dpdBaltics\Config\Config::PARCEL_RETURN);
        if (!$isReturnServiceEnabled) {
            return;
        }
        if (\Invertus\dpdBaltics\Util\CountryUtility::isEstonia()) {
            return;
        }
        /** @var Invertus\dpdBaltics\Repository\ShipmentRepository $shipmentRepo */
        $shipmentRepo = $this->getContainer(Invertus\dpdBaltics\Repository\ShipmentRepository::class);
        $shipmentId = $shipmentRepo->getIdByOrderId($params['order']->id);

        $orderState = new OrderState($params['order']->current_state);
        if (!$orderState->delivery) {
            return;
        }
        /** @var Invertus\dpdBaltics\Repository\AddressTemplateRepository $addressTemplateRepo */
        $addressTemplateRepo = $this->getContainer(Invertus\dpdBaltics\Repository\AddressTemplateRepository::class);
        $returnAddressTemplates = $addressTemplateRepo->getReturnServiceAddressTemplates();

        $shipment = new DPDShipment($shipmentId);

        if (!$returnAddressTemplates) {
            return;
        }

        $showTemplates = false;
        if (sizeof($returnAddressTemplates) > 1 && !$shipment->return_pl_number) {
            $showTemplates = true;
        }

        if (isset($this->context->cookie->dpd_error)) {
            $this->context->controller->errors[] = json_decode($this->context->cookie->dpd_error);
            unset($this->context->cookie->dpd_error);
        }
        $href = $this->context->link->getModuleLink(
            $this->name,
            'ShipmentReturn',
            [
                'id_order' => $params['order']->id,
                'dpd-return-submit' => ''
            ]
        );

        $this->context->smarty->assign(
            [
                'href' => $href,
                'return_template_ids' => $returnAddressTemplates,
                'show_template' => $showTemplates,
            ]
        );
        $html = $this->context->smarty->fetch(
            $this->getLocalPath() . 'views/templates/hook/front/order-detail.tpl'
        );

        return $html;
    }

    public function hookActionAdminOrdersListingFieldsModifier($params)
    {
        if (isset($params['select'])) {
            $params['select'] .= ' ,ds.`id_order` AS id_order_shipment ';
        }
        if (isset($params['join'])) {
            $params['join'] .= ' LEFT JOIN `' . _DB_PREFIX_ . 'dpd_shipment` ds ON ds.`id_order` = a.`id_order` ';
        }
        $params['fields']['id_order_shipment'] = [
            'title' => $this->l('DPD Label'),
            'align' => 'text-center',
            'class' => 'fixed-width-xs',
            'orderby' => false,
            'search' => false,
            'remove_onclick' => true,
            'callback_object' => 'dpdbaltics',
            'callback' => 'returnOrderListIcon'
        ];
    }

    /**
     * Callback function, it has to be static so can't call $this, so have to reload dpdBaltics module inside the function
     * @param $idOrder
     * @return string
     * @throws Exception
     */
    public static function returnOrderListIcon($orderId)
    {
        $dpdBaltics = Module::getInstanceByName('dpdbaltics');

        $dpdBaltics->context->smarty->assign('idOrder', $orderId);

        $dpdBaltics->context->smarty->assign(
            'message',
            $dpdBaltics->l('Print label(s) from DPD system. Once label is saved you won\'t be able to modify contents of shipments')
        );
        $icon = $dpdBaltics->context->smarty->fetch(
            $dpdBaltics->getLocalPath() . 'views/templates/hook/admin/order-list-save-label-icon.tpl'
        );


        $dpdBaltics->context->smarty->assign('icon', $icon);

        return $dpdBaltics->context->smarty->fetch($dpdBaltics->getLocalPath() . 'views/templates/hook/admin/order-list-icon-container.tpl');
    }


    public function hookDisplayAdminListBefore()
    {
        if ($this->context->controller instanceof AdminOrdersControllerCore) {
            return $this->context->smarty->fetch($this->getLocalPath() . 'views/templates/hook/admin/admin-orders-header-hook.tpl');
        }
    }
}
