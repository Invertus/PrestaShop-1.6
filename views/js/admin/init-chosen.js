$(document).ready(function () {
    $('select.chosen-dpd').each(function(k, item){
        $(item).chosen({disable_search_threshold: 10, search_contains: true, width: '100%', });
    });
});