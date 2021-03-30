var errorsElement = $('#order-opc-errors');


$(document).on('change', '.dpd-phone-block', function(){
    handlePhoneNumber($(this));
});

function handlePhoneNumber(selector)
{
    if (!$('.dpd-phone-block') !== undefined) {
        return;
    }
    var phone = selector.find('input[name="dpd-phone"]').val();
    var phoneArea = selector.find('select[name="dpd-phone-area"] option:selected').val();

    if (!validatePhone(phone)) {
        $('.dpd-checkout-phone-container .error-message').removeClass('hidden');
        selector.find('input[name="dpd-phone"]').css('border-color', 'red');
        errorsElement.removeClass('hidden');
        errorsElement.find('p').text(order_opc_errors['invalid_phone_error']);
        slideToError()

        return;
    }
    errorsElement.addClass('hidden');
    selector.find('input[name="dpd-phone"]').css('border-color', 'initial');

    saveSelectedPhoneNumber(phone, phoneArea)
}

$(document).on('click','.payment_module a', function (e){
    e.preventDefault();

    if ($('.dpd-phone-block') !== undefined) {
        handlePhoneNumber($('.dpd-phone-block'));
    }
    if (!selectedPudo) {
        console.log(selectedPudo)
        errorsElement.removeClass('hidden');
        errorsElement.find('p').text(order_opc_errors['pickup_point_error']);
        slideToError();
    }
});


function validatePhone(phone) {
    if (!$.isNumeric(phone) || !phone) {
        return false;
    }

    return true;
}

function isPudoPointSelected() {

}

function slideToError() {
    $('html, body').animate({
        scrollTop: ($('#order-opc-errors').offset().top - 300)
    }, 2000);
}

function saveSelectedPhoneNumber(phoneNumber, phoneArea) {
    $.ajax(dpdHookAjaxUrl, {
        type: 'POST',
        data: {
            'ajax': 1,
            'phone_number': phoneNumber,
            'phone_area': phoneArea,
            'action': 'saveSelectedPhoneNumber',
            'token': static_token
        },
        success: function (response) {
        },
        error: function (response) {

        }
    });
}