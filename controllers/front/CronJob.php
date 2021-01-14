<?php

use Invertus\dpdBaltics\Config\Config;
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
                $this->ajaxDie($parcelShopImport->importParcelShops(Configuration::get(Config::WEB_SERVICE_COUNTRY)));
                break;
            default:
                return;
        }
    }
}