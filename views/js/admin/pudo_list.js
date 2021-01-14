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

var sharedIdCart = 0;
var selectedPudoId = 0;
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
        sharedIdCart = idCart;
        processAjaxUpdateBlock(0, 0, idCart);
        return;
    }

    if ('updateDeliveryOption' === action) {
        var idCart = DPDgetUrlParam('id_cart', settings.data);
        sharedIdCart = idCart;
        processAjaxUpdateBlock(0, 0, idCart);
        return;
    }
});

$(document).ready(function () {
    $(document).on('change', '#delivery_option', processUpdateShippingBlockEvent);
    $(document).on('change', '#id_address_delivery', processChangeAddressFieldAndUpdateShippingBlock);
    $(document).on('click', '.dpd-pudo-select', addPudoCartEvent);

    function processUpdateShippingBlockEvent() {
        var carrierId = $(this).val();
        processAjaxUpdateBlock(
            carrierId,
            parseInt($('#id_address_delivery option:selected').val()),
            sharedIdCart
        );
    }

    function processChangeAddressFieldAndUpdateShippingBlock() {
        var $idReference = parseInt($('#delivery_option option:selected').val());
        if (!$idReference.length) {
            return;
        }
        var carrierId = parseInt($(this).val());
        processAjaxUpdateBlock(carrierId, $idAddress, sharedIdCart);
    }

    function addPudoCartEvent() {
        var $button = $(this);
        var $parent = $button.closest('.dpd-services-block');
        $.ajax(dpdAjaxPudoUrl, {
            method: 'POST',
            data: {
                ajax: 1,
                action: 'addPudoCart',
                pudo_id: $(this).attr('data-id'),
                id_carrier: parseInt($('#delivery_option option:selected').val()),
                id_cart: sharedIdCart,
                country_code: $(this).attr('data-countrycode'),
                city: $(this).attr('data-city'),
                post_code: $(this).attr('data-zipcode')
            },
            success: function (response) {
                if (parseInt(response)) {
                    $button
                        .removeClass('btn-default')
                        .addClass('btn-success');
                    selectedPudoId = $button.attr('data-id');

                    var $selectedButtons = $parent.find('.btn-success').not($button);
                    $selectedButtons.removeClass('btn-success').addClass('btn-default');
                }
            }
        });
    }
});

function processAjaxUpdateBlock(carrierId, idAddressDelivery, idCart) {
    $.ajax(dpdAjaxPudoUrl, {
        method: 'POST',
        data: {
            ajax: 1,
            action: 'getPudoCarriers',
            id_carrier: carrierId,
            id_address: idAddressDelivery,
            id_cart: idCart
        },
        success: function (response) {
            if (typeof response === 'undefined') {
                return;
            }

            if (response === '') {
                setPudoTemplate('');
                return;
            }

            var data = JSON.parse(response);

            // if (data.pudoMarkers.template === 'undefined') {
            //     return;
            // }
            setPudoTemplate(data.searchTemplate);

            if (selectedPudoId) {
                var $parent = $('.dpd-services-block');
                var $preselectedButton = $parent.find('[data-id="'+selectedPudoId+'"]');
                $preselectedButton.addClass('btn-success');
            }
        }
    });
}

function setPudoTemplate(pudoTemplate) {
    var $carriersContainer = $('#carrier_form');
    var $pudoContainer = $('.search-block-container');
    // var $template = '<div class="dpd-pudo-container"><div class="search-container form-group">'+ searchTemplate +'</div><div class="form-group">' + pudoTemplate + '</div></div>';
    if (!$pudoContainer.length) {
        $carriersContainer.append(pudoTemplate);
    } else {
        $pudoContainer.html(pudoTemplate);
    }
}