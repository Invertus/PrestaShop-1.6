<?php
/**
 * Copyright (c) 2012-2020, Mollie B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * - Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE.
 *
 * @author     Mollie B.V. <info@mollie.nl>
 * @copyright  Mollie B.V.
 * @license    Berkeley Software Distribution License (BSD-License 2) http://www.opensource.org/licenses/bsd-license.php
 * @category   Mollie
 * @package    Mollie
 * @link       https://www.mollie.nl
 */

use Invertus\dpdBaltics\Collection\DPDProductInstallCollection;
use Invertus\dpdBaltics\Config\Config;
use Invertus\dpdBaltics\Provider\CurrentCountryProvider;
use Invertus\dpdBaltics\Service\Carrier\CreateCarrierService;
use Invertus\dpdBaltics\Service\Product\ProductService;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @return bool
 *
 * @throws PrestaShopDatabaseException
 * @throws PrestaShopException
 */
function upgrade_module_1_1_0()
{
    $sql = [];

    $sql[] = 'ALTER TABLE `' . _DB_PREFIX_ . 'dpd_pudo_cart` ADD COLUMN `city` varchar(64) AFTER `country_code`';

    $sql[] = 'ALTER TABLE `' . _DB_PREFIX_ . 'dpd_pudo_cart` ADD COLUMN `street` varchar(64) AFTER `city`';

    $sql[] = 'ALTER TABLE `' . _DB_PREFIX_ . 'dpd_shipment` ADD COLUMN `is_document_return_enabled` tinyint(1) AFTER `return_pl_number`';

    $sql[] = 'ALTER TABLE `' . _DB_PREFIX_ . 'dpd_shipment` ADD COLUMN `document_return_number` varchar(255) AFTER `return_pl_number`';

    foreach ($sql as $query) {
        try {
            if (Db::getInstance()->execute($query) == false) {
                continue;
            }
        } catch (PrestaShopDatabaseException $e) {
            continue;
        }
    }
    $sql = [];

    $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'dpd_courier_request` (
      `id_dpd_courier_request` INT(11) UNSIGNED AUTO_INCREMENT,
      `shipment_date` DATETIME NOT NULL,
      `sender_name` VARCHAR(255) NOT NULL,
      `sender_phone_code` VARCHAR(255) NOT NULL,
      `sender_phone` VARCHAR(255) NOT NULL,
      `sender_id_ws_country` INT(11) NOT NULL,
      `country` VARCHAR(255) NOT NULL,
      `sender_postal_code` VARCHAR(255) NOT NULL,
      `sender_city` VARCHAR(255) NOT NULL,
      `sender_address` VARCHAR(255) NOT NULL,
      `sender_additional_information` VARCHAR(255) NOT NULL,
      `order_nr` VARCHAR(255) NOT NULL DEFAULT \'\',
      `pick_up_time` VARCHAR(255) NOT NULL,
      `sender_work_until` VARCHAR(255) NOT NULL,
      `weight` FLOAT(11) UNSIGNED NOT NULL,
      `parcels_count` INT(11) NOT NULL,
      `pallets_count` INT(11) NOT NULL,
      `comment_for_courier` VARCHAR(255) NOT NULL,
      `id_shop` INT(11) UNSIGNED NOT NULL,
      `date_add` DATETIME,
      `date_upd` DATETIME,
      PRIMARY KEY (`id_dpd_courier_request`)
    ) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;';

    $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'dpd_shop` (
      `id_dpd_shop` INT(11) UNSIGNED AUTO_INCREMENT,
      `parcel_shop_id` varchar(64) NOT NULL,
      `company` varchar(64) NOT NULL,
      `country` varchar(2) NOT NULL,
      `city` varchar(64) NOT NULL,
      `p_code` VARCHAR(12) NOT NULL,
      `street` varchar(64) NOT NULL,
      `email` varchar(64) NOT NULL,
      `phone` varchar(64) NOT NULL,
      `longitude` decimal(20,6) NOT NULL,
      `latitude` decimal(20,6) NOT NULL,
      PRIMARY KEY (`id_dpd_shop`)
    ) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;
    ';

    $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'dpd_shop_work_hours` (
      `id_dpd_shop_work_hours` INT(11) UNSIGNED AUTO_INCREMENT,
      `parcel_shop_id` varchar(64) NOT NULL,
      `week_day` varchar(64) NOT NULL,
      `open_morning` varchar(64) NOT NULL,
      `close_morning` varchar(64) NOT NULL,
      `open_afternoon` varchar(64) NOT NULL,
      `close_afternoon` varchar(64) NOT NULL,
      PRIMARY KEY (`id_dpd_shop_work_hours`)
    ) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;
    ';

    foreach ($sql as $query) {
        if (Db::getInstance()->execute($query) == false) {
            return false;
        }
    }

    Configuration::updateValue(
        Config::PARCEL_SHOP_DISPLAY,
        Config::PARCEL_SHOP_DISPLAY_LIST
    );

    $module = Module::getInstanceByName('dpdbaltics');

    /** @var CreateCarrierService $carrierCreateService */
    /** @var ProductService $productService */
    $carrierCreateService = $module->getContainer()->get(CreateCarrierService::class);
    $productService = $module->getContainer()->get(ProductService::class);

    /** @var CurrentCountryProvider $currentCountryProvider */
    $currentCountryProvider = $this->module->getContainer(CurrentCountryProvider::class);
    $countryCode = $currentCountryProvider->getCurrentCountryIsoCode();

    $productService->updateCarriersOnCountryChange($countryCode);

    $collection = new DPDProductInstallCollection();
    $product = Config::getProductByReference(Config::PRODUCT_TYPE_SATURDAY_DELIVERY);
    $collection->add($product);

    $product = Config::getProductByReference(Config::PRODUCT_TYPE_SATURDAY_DELIVERY_COD);
    $collection->add($product);

    try {
        $result = $carrierCreateService->createCarriers($collection);
    } catch (Exception $e) {
        $this->errors[] = $e->getMessage();
        $result = false;
    }

    return $result;
}
