$(document).on('change', '.dpd-phone-block', function(){
    handlePhoneNumber($(this));
});
$(document).ready(function (){
    if ($('.dpd-phone-block') !== undefined) {
        handlePhoneNumber($('.dpd-phone-block'));
    }
});

function handlePhoneNumber(selector)
{
    if ($('.dpd-phone-block') !== undefined) {

    var phone = selector.find('input[name="dpd-phone"]').val();
    var phoneArea = selector.find('select[name="dpd-phone-area"] option:selected').val();

    var termsAndConditionsCheckbox =  $("#uniform-cgv input");
    if (!$.isNumeric(phone) || !phone) {
        $('.dpd-checkout-phone-container .error-message').removeClass('hidden');
        selector.find('input[name="dpd-phone"]').css('border-color', 'red');
        termsAndConditionsCheckbox.attr("disabled", true);
        return false;
    } else {
        $('.dpd-checkout-phone-container .error-message').addClass('hidden');
        termsAndConditionsCheckbox.removeAttr("disabled");
        selector.find('input[name="dpd-phone"]').css('border-color', 'initial');
    }

    saveSelectedPhoneNumber(phone, phoneArea)
    }
}

$('#uniform-cgv').on('click', function (){
    if ($('.dpd-phone-block') !== undefined) {
        handlePhoneNumber($('.dpd-phone-block'));
    }
});

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
            response = JSON.parse(response);
            var $parent = $('.dpd-pudo-container');

            if (!response.status) {
                DPDdisplayMessage($parent, response.template);
            }
        },
        error: function (response) {
            var responseText = JSON.parse(response.responseText);

            if (responseText) {
                DPDdisplayMessage($container, responseText);
            }
        }
    });
}
