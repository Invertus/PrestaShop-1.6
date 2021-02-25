<?php
/**
 * NOTICE OF LICENSE
 *
 * @author    INVERTUS, UAB www.invertus.eu <support@invertus.eu>
 * @copyright Copyright (c) permanent, INVERTUS, UAB
 * @license   Addons PrestaShop license limitation
 * @see       /LICENSE
 *
 *  International Registered Trademark & Property of INVERTUS, UAB
 */
require_once __DIR__ . '/../../src/Controller/AbstractAdminController.php';

use Invertus\dpdBaltics\Config\Config;
use Invertus\dpdBaltics\Controller\AbstractAdminController;
use Invertus\dpdBaltics\Converter\FormDataConverter;
use Invertus\dpdBaltics\DTO\ShipmentData;
use Invertus\dpdBaltics\Factory\ShipmentDataFactory;
use Invertus\dpdBaltics\Repository\ShipmentRepository;
use Invertus\dpdBaltics\Service\Address\ReceiverAddressService;
use Invertus\dpdBaltics\Service\API\ParcelShopSearchApiService;
use Invertus\dpdBaltics\Service\API\ShipmentApiService;
use Invertus\dpdBaltics\Service\Exception\ExceptionService;
use Invertus\dpdBaltics\Service\PudoService;
use Invertus\dpdBaltics\Validate\ShipmentData\Exception\InvalidShipmentDataField;
use Invertus\dpdBaltics\Validate\ShipmentData\ShipmentDataValidator;
use Invertus\dpdBalticsApi\Api\DTO\Object\ParcelShop;
use Invertus\dpdBalticsApi\Api\DTO\Response\ParcelShopSearchResponse;
use Invertus\dpdBalticsApi\Api\DTO\Response\ShipmentCreationResponse;
use Invertus\dpdBalticsApi\Exception\DPDBalticsAPIException;

class AdminDPDBalticsAjaxShipmentsController extends AbstractAdminController
{
    protected $ajaxActions = ['save', 'save_and_print', 'updateAddressBlock', 'getProductPriceByID', 'print'];
    protected $ajaxPudoActions = ['searchPudoServices'];

    /**
     * Process AJAX call
     */
    public function postProcess()
    {
        $response = ['status' => false];

        $action = Tools::getValue('action');
        $idOrder = Tools::getValue('id_order');
        $order = new Order($idOrder);
        $cartId = Tools::getValue('id_cart');

        /** @var FormDataConverter $formDataConverter */
        $formDataConverter = $this->module->getContainer(FormDataConverter::class);
        $data = Tools::getValue('data');

        switch ($action) {
            case 'changeReceiverAddressBlock':
                $receiverAddressData = json_decode(Tools::getValue('dpdReceiverAddress'));
                $orderId = Tools::getValue('id_order');
                $this->changeReceiverAddressBlock($receiverAddressData, $orderId);
                break;
            case 'updateAddressBlock':
                $idAddressDelivery = (int)Tools::getValue('id_address_delivery');
                $this->updateAddressBlock($order, $idAddressDelivery);
                break;
            case 'print':
                $shipmentId = (int)Tools::getValue('shipment_id');
                $labelFormat = Tools::getValue('labelFormat');
                $labelPosition = Tools::getValue('labelPosition');
                $this->returnResponse($this->printLabel($shipmentId, $labelFormat, $labelPosition));
                break;
            case 'save':
            case 'save_and_print':
                $shipmentData = $formDataConverter->convertShipmentFormDataToShipmentObj($data);
                $this->returnResponse($this->saveShipment($order, $shipmentData, $action == 'save_and_print'));
                break;
            case 'searchPudoServices':
                $cityName = Tools::getValue('city_name');
                $productId = Tools::getValue('id_product');

                if ($productId) {
                    $product = new DPDProduct($productId);
                    $carrier = Carrier::getCarrierByReference($product->id_reference);
                } else {
                    $carrierId = (int)Tools::getValue('carrier_id');
                    $carrier = new Carrier($carrierId);
                }
                /** @var PudoService $pudoService */
                $pudoService = $this->module->getContainer(PudoService::class);
                try {
                    $this->returnResponse(
                        $pudoService->searchPudoServices(
                            $cityName,
                            $carrier->id_reference,
                            $cartId
                        )
                    );
                } catch (Exception $e) {
                    $this->returnResponse(
                        [
                            'message' => $this->module->l('Parcel shop search failed!'),
                            'status' => false,
                        ]
                    );
                }
                break;
            case 'updatePudoInfo':
                $pudoId = Tools::getValue('pudo_id');
                $this->returnResponse($this->updatePudoInfo($pudoId));
                break;
            case 'checkIfPudo':
                $productId = Tools::getValue('product_id');
                $dpdProduct = new DPDProduct($productId);
                $this->returnResponse(
                    [
                        'status' => true,
                        'isPudo' => (bool)$dpdProduct->is_pudo
                    ]
                );
                break;
            case 'checkIfHasDeliveryTime':
                $productId = Tools::getValue('product_id');
                $dpdProduct = new DPDProduct($productId);
                $hasDeliveryTime = Config::productHasDeliveryTime($dpdProduct->product_reference);
                $this->returnResponse(
                    [
                        'status' => true,
                        'hasDelivery' => $hasDeliveryTime
                    ]
                );
                break;
            case 'printLabelFromList':
                $orderId = Tools::getValue('id_order');
                $this->returnResponse($this->printLabelFromList($orderId));
                break;
            case 'printMultipleLabelsFromList':
                $orders = json_decode(Tools::getValue('order_ids'));
                $this->returnResponse($this->printMultipleLabelsFromList($orders));
                break;
            case  'getCarrierPhoneTemplate':
                $idCart = Tools::getValue('id_cart');
                $idCarrier = Tools::getValue('id_carrier');

                $carrier = new Carrier($idCarrier);

                if ($carrier->external_module_name !== $this->module->name) {
                    $response = json_encode(
                        [
                            'carrierPhoneTemplate' => ''
                        ]
                    );
                    $this->ajaxDie($response);
                }
                /** @var \Invertus\dpdBaltics\Service\CarrierPhoneService $carrierPhoneService */
                $carrierPhoneService = $this->module->getContainer(\Invertus\dpdBaltics\Service\CarrierPhoneService::class);

                $response = [
                    'carrierPhoneTemplate' => $carrierPhoneService->getCarrierPhoneTemplate($idCart)
                ];
                $response = json_encode($response);
                $this->ajaxDie($response);
                break;
            default:
                break;
        }

        $response['message'] = $this->module->l('Unexpected error occurred.');
        $this->returnResponse($response);
    }

    private function printLabelFromList($orderId)
    {
        $order = new Order($orderId);
        /** @var ShipmentDataFactory $shipmentDataFactory */
        $shipmentDataFactory = $this->module->getContainer(ShipmentDataFactory::class);
        $shipmentData = $shipmentDataFactory->getShipmentDataByIdOrder($orderId);

        return $this->saveShipment($order, $shipmentData, true);
    }

    public function printMultipleLabelsFromList($orderIds)
    {
        $response['status'] = false;

        $shipmentIds = [];
        $successfulOrders = [];
        /** @var ShipmentDataFactory $shipmentDataFactory */
        $shipmentDataFactory = $this->module->getContainer(ShipmentDataFactory::class);
        $message = '';
        $success = true;
        foreach ($orderIds as $orderId) {
            $order = new Order($orderId);
            $shipmentData = $shipmentDataFactory->getShipmentDataByIdOrder($orderId);
            $response = $this->saveShipment($order, $shipmentData, true);
            if ($response['status']) {
                $shipmentIds[] = $response['id_dpd_shipment'];
                $successfulOrders[] = $orderId;
            }

            if (!$response['status']) {
                $message .= sprintf($this->module->l('Failed to save shipment for order %s Error: %s'), $orderId, $response['message']) . '</br>';
                $success = false;
            }
        }

        $response['status'] = $success;

        if ($success) {
            $message = $this->module->l('Labels printed successfully');
        }

        if (!$success) {
            $message = sprintf(
                    $this->module->l('Printing failed for some orders, printed labels for orders: %s'),
                    implode(', ', $successfulOrders)
                ) . '</br>' . $message;
        }

        $response['message'] = $message;
        $response['shipment_ids'] = false;

        if (!empty($shipmentIds)) {
            $response['shipment_ids'] = json_encode($shipmentIds);
        }

        return $response;
    }


    /**
     * @param Order $order
     * @param $shipmentData
     * @param bool $print
     * @return mixed
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function saveShipment(Order $order, ShipmentData $shipmentData, $print = false)
    {
        $response['status'] = false;
        /** @var ShipmentDataValidator $shipmentDataValidator */
        $shipmentDataValidator = $this->module->getContainer(ShipmentDataValidator::class);

        try {
            $shipmentDataValidator->validate($shipmentData);
        } catch (InvalidShipmentDataField $e) {
            /** @var ExceptionService $exceptionService */
            $exceptionService = $this->module->getContainer(ExceptionService::class);
            $response['message'] = $exceptionService->getErrorMessageForException(
                $e,
                $exceptionService->getShipmentFieldErrorMessages()
            );
            return $response;
        } catch (Exception $e) {
            $response['message'] = $this->module->l(
                sprintf('Failed to save shipment data. Error: %s', $e->getMessage())
            );

            return $response;
        }

        /** @var ShipmentRepository $shipmentRepository */
        $shipmentRepository = $this->module->getContainer(ShipmentRepository::class);
        $shipmentId = $shipmentRepository->getIdByOrderId($order->id);
        $shipment = new DPDShipment($shipmentId);

        if ($shipment->printed_label) {
            $labelFormat = Configuration::get(Config::DEFAULT_LABEL_FORMAT);
            $labelPosition = Configuration::get(Config::DEFAULT_LABEL_POSITION);
            return $this->printLabel($shipmentId, $labelFormat, $labelPosition);
        }

        try {
            $shipmentRepository->saveShipment($shipmentData, $shipmentId);
        } catch (Exception $e) {
            $response['message'] = $this->module->l('Failed to save shipment');
            $this->returnResponse($response);
        }

        if ($shipmentData->isPudo()) {
            /** @var PudoService $pudoService */
            $pudoService = $this->module->getContainer(PudoService::class);
            $productId = $shipmentData->getProduct();
            $pudoId = $shipmentData->getSelectedPudoId();
            $isoCode = $shipmentData->getSelectedPudoIsoCode();
            $city = $shipmentData->getCity();
            $street = $shipmentData->getDpdStreet();
            $cartId = $order->id_cart;
            try {
                $pudoService->savePudoOrder($productId, $pudoId, $isoCode, $cartId, $city, $street);
            } catch (Exception $e) {
                $response['message'] = $e->getMessage();
                return $response;
            }
        }

        /** @var \Invertus\dpdBaltics\Repository\OrderDeliveryTimeRepository $orderDeliveryTimeRepo */
        $orderDeliveryTimeRepo = $this->module->getContainer()->get(\Invertus\dpdBaltics\Repository\OrderDeliveryTimeRepository::class);

        if ($shipmentData->getDeliveryTime()) {
            $deliveryTimeId = $orderDeliveryTimeRepo->getOrderDeliveryIdByCartId($order->id_cart);
            $deliveryTime = new DPDOrderDeliveryTime($deliveryTimeId);
            $deliveryTime->delivery_time = $shipmentData->getDeliveryTime();
            $deliveryTime->update();
        }

        if ($print) {
            return $this->printAndSaveLabel($shipmentData, $shipmentId, $order->id);
        }

        $response['id_dpd_shipment'] = $shipmentId;
        $response['status'] = true;

        return $response;
    }

    /**
     * @param array $response
     * @throws PrestaShopException
     */
    protected function returnResponse(array $response)
    {
        $response = json_encode($response);
        $this->ajaxDie($response);
    }


    private function changeReceiverAddressBlock($receiverAddressData, $orderId)
    {
        if (preg_match('#[^0-9]#',$receiverAddressData->phone)) {
            $response['message'] = $this->module->l('Invalid phone number.');
            $this->returnResponse($response);
        }
        $order = new Order($orderId);
        /** @var ReceiverAddressService $receiverAddressService */
        $receiverAddressService = $this->module->getContainer(ReceiverAddressService::class);
        $receiverCustomAddress = $receiverAddressService->addReceiverCustomAddress($receiverAddressData, $orderId);

        if (false === $receiverCustomAddress) {
            $response['message'] = $this->module->l('Failed to add new recipient address.');
            $this->returnResponse($response);
        }

        if (!$receiverAddressService->deletePreviousEditedAddress($order->id)) {
            $response['message'] = $this->module->l('Failed to delete previously edited address');
            $this->returnResponse($response);
        }

        $receiverAddress = new DPDReceiverAddress();
        $receiverAddress->id_order = $orderId;
        $receiverAddress->id_origin_address = $receiverCustomAddress->id;
        try {
            $receiverAddress->save();
            $addressResponse = $receiverAddressService->processUpdateAddressBlock($order, $receiverCustomAddress->id);
        } catch (Exception $e) {
            $response['message'] = $this->module->l('Failed to update address for this order');
            $this->returnResponse($response);
        }

        if (false === $addressResponse) {
            $response['message'] = $this->module->l('Failed to update address for this order');
            $this->returnResponse($response);
        }

        $addressResponse['message'] = $this->module->l('Receiver address successfully updated for this order.');
        $this->returnResponse($addressResponse);
    }

    private function updateAddressBlock($order, $idAddressDelivery)
    {
        /** @var ReceiverAddressService $receiverAddressService */
        $receiverAddressService = $this->module->getContainer(ReceiverAddressService::class);
        try {
            $addressResponse = $receiverAddressService->processUpdateAddressBlock($order, $idAddressDelivery);
        } catch (Exception $e) {
            $response['message'] = $this->module->l('Failed to update address.');
            $this->returnResponse($response);
        }

        if (false === $addressResponse) {
            $response['message'] = $this->module->l('Failed to update address.');
            $this->returnResponse($response);
        }

        $this->returnResponse($addressResponse);
    }

    private function printLabel($shipmentId, $labelFormat, $labelPosition)
    {
        $shipment = new DPDShipment($shipmentId);
        if ($shipment->printed_label && !$shipment->printed_manifest) {
            $shipment->label_format = $labelFormat;
            $shipment->label_position = $labelPosition;
            try {
                $shipment->update();
            } catch (Exception $e) {
                $response['message'] = $this->module->l('Failed to update shipment label');
                return $response;
            }
            $response['status'] = true;
        }
        $response['id_dpd_shipment'] = $shipmentId;
        $response['message'] = $this->module->l('Parcel cannot be printed');
        return $response;
    }

    private function printAndSaveLabel(ShipmentData $shipmentData, $shipmentId, $orderId)
    {
        $response['status'] = false;
        /** @var ShipmentApiService $shipmentApiService */
        $shipmentApiService = $this->module->getContainer(ShipmentApiService::class);

        try {
            /** @var ShipmentCreationResponse $shipmentCreationResponse */
            $shipmentCreationResponse = $shipmentApiService->createShipment(
                $shipmentData->getAddressId(),
                $shipmentData,
                $orderId
            );
        } catch (DPDBalticsAPIException $e) {
            /** @var ExceptionService $exceptionService */
            $exceptionService = $this->module->getContainer(ExceptionService::class);
            $response['message'] = $exceptionService->getErrorMessageForException(
                $e,
                $exceptionService->getAPIErrorMessages()
            );
        } catch (Exception $e) {
            $response['message'] = $this->module->l("Failed to created shipment: {$e->getMessage()}");
            return $response;
        }

        if ($shipmentCreationResponse->getStatus() !== Config::API_SUCCESS_STATUS) {
            $response['message'] = $this->module->l(
                "Failed to created shipment: {$shipmentCreationResponse->getErrLog()}"
            );
            return $response;
        }

        $shipment = new DPDShipment($shipmentId);
        $shipment->pl_number = $shipmentCreationResponse->getPlNumbersAsString();
        try {
            $shipment->update();
        } catch (Exception $e) {
            $response['message'] = $this->module->l(
                "Failed to update shipment: {$e->getMessage()}"
            );
            return $response;
        }

        $response['status'] = true;
        $response['id_dpd_shipment'] = $shipmentId;

        return $response;

    }

    private function updatePudoInfo($pudoId)
    {
        /** @var \Invertus\dpdBaltics\Service\Parcel\ParcelShopService $parcelShopService */
        $parcelShopService= $this->module->getContainer(\Invertus\dpdBaltics\Service\Parcel\ParcelShopService::class);
        try {
            /** @var ParcelShop[] $parcelShops */
            $parcelShops = $parcelShopService->getParcelShopByShopId($pudoId);
        } catch (Exception $e) {
            $response['status'] = false;
            $response['message'] = $this->module->l('Failed to find parcel shops: ') . $e->getMessage();
            $this->returnResponse($response);
        }

        /** @var PudoService $pudoService */
        $pudoService = $this->module->getContainer(PudoService::class);
        $pudoServices = $pudoService->setPudoServiceTypes($parcelShops);
        $pudoServices = $pudoService->formatPudoServicesWorkHours($pudoServices);

        $this->context->smarty->assign(
            [
                'receiverAddressCountries' => Country::getCountries($this->context->language->id, true),
                'selectedPudo' => $pudoServices[0]

            ]
        );

        return [
            'template' => $this->context->smarty->fetch(
                $this->module->getLocalPath() . '/views/templates/hook/admin/partials/pudo-info.tpl'
            ),
            'parcel_name' => $pudoServices[0]->getCompany(),
            'status' => true,
        ];
    }
}
