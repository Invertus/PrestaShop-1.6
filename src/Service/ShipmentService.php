<?php

namespace Invertus\dpdBaltics\Service;

use Address;
use Carrier;
use Configuration;
use Country;
use Customer;
use DPDAddressTemplate;
use DPDBaltics;
use DPDParcel;
use DPDShipment;
use Exception;
use Invertus\dpdBaltics\Config\Config;
use Invertus\dpdBaltics\Helper\ShipmentHelper;
use Invertus\dpdBaltics\Repository\AddressTemplateRepository;
use Invertus\dpdBaltics\Repository\OrderRepository;
use Invertus\dpdBaltics\Repository\ProductRepository;
use Invertus\dpdBaltics\Repository\ShipmentRepository;
use Invertus\dpdBaltics\Service\API\ShipmentApiService;
use Invertus\dpdBalticsApi\Api\DTO\Request\ShipmentCreationRequest;
use Invertus\dpdBalticsApi\Api\DTO\Response\ShipmentCreationResponse;
use Invertus\dpdBalticsApi\Exception\DPDBalticsAPIException;
use Language;
use Order;
use OrderCarrier;

class ShipmentService
{

    /**
     * @var DPDBaltics
     */
    private $module;

    /**
     * @var Language
     */
    private $language;

    /**
     * @var ShipmentRepository
     */
    private $shipmentRepository;

    /**
     * @var ShipmentHelper
     */
    private $shipmentHelper;

    /**
     * @var ShipmentApiService
     */
    private $shipmentApiService;

    public function __construct(
        DPDBaltics $module,
        Language $language,
        ShipmentRepository $shipmentRepository,
        ShipmentHelper $shipmentHelper,
        ShipmentApiService $shipmentApiService
    ) {
        $this->module = $module;
        $this->language = $language;
        $this->shipmentRepository = $shipmentRepository;
        $this->shipmentHelper = $shipmentHelper;
        $this->shipmentApiService = $shipmentApiService;
    }

    public function createShipment(Order $order, $idProduct, $isTestMode, $numOfParcels, $weight, $goodsPrice)
    {
        $shipment = new DPDShipment();
        $shipment->id_order = $order->id;
        $shipment->printed_label = 0;
        $shipment->printed_manifest = 0;
        $shipment->manifest_closed = 0;
        $shipment->id_ws_manifest = '';
        $shipment->date_shipment = date('Y-m-d H:i:s');
        $shipment->id_service = $idProduct;
        $shipment->saved = 0;
        $shipment->is_test = $isTestMode;
        $shipment->label_format = Configuration::get(Config::DEFAULT_LABEL_FORMAT);
        $shipment->label_position = Configuration::get(Config::DEFAULT_LABEL_POSITION);
        $shipment->reference1 = $this->shipmentHelper->getReference($order->id, $order->reference);
        $shipment->num_of_parcels = $numOfParcels;
        $shipment->weight = $weight;
        $shipment->goods_price = $goodsPrice;
        $shipment->reference4 = Config::getPsAndModuleVersion();

        $shipment->save();

        return $shipment;
    }

    public function createShipmentFromOrder(Order $order)
    {
        /** @var ShipmentRepository $shipmentRepository */
        $shipmentRepository = $this->module->getContainer(ShipmentRepository::class);
        /** @var ProductRepository $productRepository */
        $productRepository = $this->module->getContainer(ProductRepository::class);

        if ($shipmentRepository->hasAnyShipments($order->id)) {
            return true;
        }

        $parcelDistribution = Configuration::get(Config::PARCEL_DISTRIBUTION);

        $carrier = new Carrier($order->id_carrier, $this->language->id);
        $serviceCarrier = $productRepository->findProductByCarrierReference($carrier->id_reference);

        $idProduct = $serviceCarrier['id_dpd_product'];

        $isTestMode = Configuration::get(Config::SHIPMENT_TEST_MODE);
        if (DPDParcel::DISTRIBUTION_NONE === $parcelDistribution) {
            $result = $this->createNonDistributedShipment($order, $idProduct, $isTestMode);
        } elseif (DPDParcel::DISTRIBUTION_PARCEL_PRODUCT === $parcelDistribution) {
            $result = $this->createParcelDistributedShipments($order, $idProduct, $isTestMode);
        } elseif (DPDParcel::DISTRIBUTION_PARCEL_QUANTITY === $parcelDistribution) {
            $result =
                $this->createParcelQuantityDistributedShipments($order, $idProduct, $isTestMode);
        } else {
            $result = false;
        }

        return $result;
    }

    private function createNonDistributedShipment(Order $order, $idProduct, $isTestMode)
    {
        $products = $order->getProducts();
        $parcelPrice = 0;
        $parcelWeight = 0;
        foreach ($products as $product) {
            $parcelPrice += round($product['total_wt'], 2);
            $parcelWeight += $product['weight'] * $product['product_quantity'];
        }
        $shipment = $this->createShipment($order, $idProduct, $isTestMode, 1, $parcelWeight, $parcelPrice);

        if (!$shipment->id) {
            return false;
        }

        return true;
    }

    private function createParcelDistributedShipments(Order $order, $idProduct, $isTestMode)
    {
        $products = $order->getProducts();
        $parcelPrice = 0;
        $parcelWeight = 0;
        $parcelsNum = 0;
        foreach ($products as $product) {
            $parcelPrice += round($product['total_wt'], 2);
            $parcelWeight += $product['weight'] * $product['product_quantity'];
            $parcelsNum++;
        }

        $shipment = $this->createShipment($order, $idProduct, $isTestMode, $parcelsNum, $parcelWeight, $parcelPrice);
        if (!$shipment->id) {
            return false;
        }
        return true;
    }

    private function createParcelQuantityDistributedShipments(Order $order, $idProduct, $isTestMode)
    {
        $products = $order->getProducts();
        $parcelPrice = 0;
        $parcelWeight = 0;
        $parcelsNum = 0;
        foreach ($products as $product) {
            $parcelPrice += round($product['total_wt'], 2);
            $parcelWeight += $product['weight'] * $product['product_quantity'];
            $parcelsNum += $product['product_quantity'];
        }

        $shipment = $this->createShipment($order, $idProduct, $isTestMode, $parcelsNum, $parcelWeight, $parcelPrice);
        if (!$shipment->id) {
            return false;
        }
        return true;
    }

    public function createReturnServiceShipment($returnAddressId, $orderId)
    {
        if (!$returnAddressId) {
            return false;
        }
        /** @var ShipmentCreationResponse $shipmentResponse */
        $shipmentResponse = $this->shipmentApiService->createReturnServiceShipment($returnAddressId);

        if ($shipmentResponse->getStatus() !== Config::API_SUCCESS_STATUS) {
            throw new DPDBalticsAPIException(
                $shipmentResponse->getErrLog(),
                DPDBalticsAPIException::SHIPMENT_CREATION
            );
        }
        $dpdShipmentId = $this->shipmentRepository->getIdByOrderId($orderId);
        $dpdShipment = new DPDShipment($dpdShipmentId);
        $dpdShipment->return_pl_number = $shipmentResponse->getPlNumbersAsString();
        $dpdShipment->update();

        return $dpdShipment;
    }
}
