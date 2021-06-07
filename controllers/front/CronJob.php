<?php

use Invertus\dpdBaltics\Config\Config;
use Invertus\dpdBaltics\Provider\ZoneRangeProvider;
use Invertus\dpdBaltics\Service\Import\API\ParcelShopImport;

class DpdbalticsCronJobModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $token = Tools::getValue('token');
        if ($token !== Configuration::get(Config::DPDBALTICS_HASH_TOKEN)) {
            $this->ajaxDie([
                'success' => false,
                'message' => 'wrong token'
            ]);
        }

        $action = Tools::getValue('action');
        switch ($action) {

            case 'updateParcelShops':
                /** @var ParcelShopImport $parcelShopImport */
                $parcelShopImport = $this->module->getContainer(ParcelShopImport::class);
                /** @var  ZoneRangeProvider $zoneRangeProvider */
                $zoneRangeProvider = $this->module->getContainer(ZoneRangeProvider::class);
                $countriesInZoneRange = $zoneRangeProvider->getAllZoneRangesCountryIsoCodes();

                foreach ($countriesInZoneRange as $country) {
                    $response = $parcelShopImport->importParcelShops($country);

                    if (isset($response['success']) && !$response['success']) {
                        $this->ajaxDie(json_encode($response));
                    }
                }
                break;
            default:
                return;
        }
    }
}