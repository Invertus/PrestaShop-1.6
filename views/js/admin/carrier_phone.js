/*
 * NOTICE OF LICENSE
 *
 * @author    INVERTUS, UAB www.invertus.eu <support@invertus.eu>
 * @copyright Copyright (c) permanent, INVERTUS, UAB
 * @license   Addons PrestaShop license limitation
 * @see       /LICENSE
 *
 *  International Registered Trademark & Property of INVERTUS, UAB
 */

$(document).ready(function() {
    $(document).on('click', '.dpd-input-wrapper .dpd-input-placeholder', function(){
        $(this).closest(".dpd-input-wrapper").find("input").focus();
    });

    $(document).on('keyup', '.dpd-input-wrapper input', function(){
        var value = $.trim($(this).val());

        if (value) {
            $(this).closest(".dpd-input-wrapper").addClass("hasValue");
        } else {
            $(this).closest(".dpd-input-wrapper").removeClass("hasValue");
        }
    });

    $(document).on('change', 'input[name="dpd-phone-area"]', function(){
        $('input[name="dpd-phone-area"]').val($(this).val());
    });

    $(document).on('change', 'input[name="dpd-phone"]', function(){
        $('input[name="dpd-phone"]').val($(this).val());
    });

    $(document).on('change', '#delivery_option', processAjaxAddCarrierPhoneTemplate);

    $('form.form-horizontal').submit(function(event) {

        if ($('input[name="dpd-phone-area"]').is(':visible') && $('input[name="dpd-phone-area"]').val() === '') {
            event.preventDefault();

            $('html, body').animate({
                scrollTop: $('input[name="dpd-phone-area"]').offset().top - 150
            }, 300);

            $('input[name="dpd-phone-area"]').css('border-color', 'red')
        }

        if ($('input[name="dpd-phone"]').is(':visible') && $('input[name="dpd-phone"]').val() === '') {
            event.preventDefault();

            $('html, body').animate({
                scrollTop: $('input[name="dpd-phone"]').offset().top - 150
            }, 300);

            $('input[name="dpd-phone"]').css('border-color', 'red')
        }
    });
});

$( document ).ajaxComplete(function( event, request, settings ) {
    if (typeof settings == 'undefined') {
        return;
    }

    if (typeof settings.data !== 'string') {
        return;
    }

    var action = DPDgetUrlParam('action', settings.data);

    if ('updateQty' === action) {
        var idCart = DPDgetUrlParam('id_cart', settings.data);
        processAjaxAddCarrierPhoneTemplate(idCart);

        return;
    }

    if ('updateDeliveryOption' === action) {
        var idCart = DPDgetUrlParam('id_cart', settings.data);
        processAjaxAddCarrierPhoneTemplate(idCart);

        return;
    }

    if ('getSummary' === action) {
        var idCart = DPDgetUrlParam('id_cart', settings.data);
        processAjaxAddCarrierPhoneTemplate(idCart);

        return;
    }
});

function processAjaxAddCarrierPhoneTemplate(idCart) {
    var carrierId = $('#delivery_option').val();

    if (carrierId === undefined || !carrierId) {
        return false;
    }

    $.ajax(dpdAjaxShipmentsUrl, {
        method: 'POST',
        data: {
            ajax: 1,
            action: 'getCarrierPhoneTemplate',
            id_cart: idCart,
            id_carrier: carrierId
        },
        success: function (response) {
            if (typeof response === 'undefined') {
                return;
            }

            if (response === '') {
                setCarrierPhoneTemplate('');
                return;
            }

            var data = JSON.parse(response);

            if (data.carrierPhoneTemplate === '') {
                setCarrierPhoneTemplate('');
                return;
            }

            if (data.carrierPhoneTemplate === 'undefined') {
                return;
            }

            setCarrierPhoneTemplate(data.carrierPhoneTemplate);
            $('.chosen-select.js-dpd-phone-prefix').chosen({inherit_select_classes: true});
        }
    });
}

function setCarrierPhoneTemplate($carrierPhoneTemplate) {
    var $deliveryOptionContainer = $('#delivery_option').closest('.form-group');

    var $carrierPhoneContainer = $('.dpd-phone-block');
    var $carrierPhoneContainerHr = $('.phone-block-hr');

    $carrierPhoneContainer.remove();
    $carrierPhoneContainerHr.remove();

    if ($carrierPhoneTemplate === '') {
        return;
    }

    $deliveryOptionContainer.append($carrierPhoneTemplate);
}

function DPDgetUrlParam(sParam, string)
{
    var sPageURL = decodeURIComponent(string),
        sURLVariables = sPageURL.split('&'),
        sParameterName,
        i;

    for (i = 0; i < sURLVariables.length; i++) {
        sParameterName = sURLVariables[i].split('=');

        if (sParameterName[0] === sParam) {
            return sParameterName[1] === undefined ? true : sParameterName[1];
        }
    }
}