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

use Invertus\dpdBaltics\Config\Config;
use Invertus\dpdBaltics\Factory\TabFactory;
use Invertus\dpdBaltics\Install\Installer;
use Invertus\dpdBaltics\Provider\CurrentCountryProvider;
use Invertus\dpdBaltics\Repository\ProductRepository;
use Invertus\dpdBaltics\Service\Product\ProductService;
use Invertus\psModuleTabs\Object\Tab;
use Invertus\psModuleTabs\Object\TabsCollection;
use Invertus\psModuleTabs\Service\TabsInitializer;
use Invertus\psModuleTabs\Service\TabsInstaller;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @return bool
 *
 * @throws PrestaShopDatabaseException
 * @throws PrestaShopException
 */
function upgrade_module_1_1_2(DPDBaltics $module)
{
    /** @var CurrentCountryProvider $currentCountryProvider */
    $currentCountryProvider = $this->module->getContainer(CurrentCountryProvider::class);
    $newCountryIsoCode = $currentCountryProvider->getCurrentCountryIsoCode();

    /** @var ProductRepository $productRepository */
    $productRepository = $module->getContainer(ProductRepository::class);
    /** @var ProductService $productService */
    $productService = $module->getContainer(ProductService::class);
    $productId = $productRepository->getProductIdByProductReference(Config::PRODUCT_TYPE_SATURDAY_DELIVERY_COD);
    if ($newCountryIsoCode === Config::LATVIA_ISO_CODE) {
        $productService->deleteProduct(Config::PRODUCT_TYPE_SATURDAY_DELIVERY_COD);
    } elseif (!$productId) {
        $productService->addProduct(Config::PRODUCT_TYPE_SATURDAY_DELIVERY_COD);
    }

    return true;
}
