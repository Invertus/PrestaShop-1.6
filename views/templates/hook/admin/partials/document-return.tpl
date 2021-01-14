<div class="dpd-document-return-panel panel">
    <div class="panel-heading">
        <span class="pull-left">
            {l s='DOCUMENT RETURN' mod='dpdbaltics'}
        </span>
    </div>
    <div class="row">
        <label class="control-label col-xs-2">
            {l s='Enable document return' mod='dpdbaltics'}
        </label>
        <div class="col-xs-4">
            <span class="switch prestashop-switch fixed-width-lg">
                <input type="radio" name="DPD_DOCUMENT_RETURN" id="DPD_DOCUMENT_RETURN_on" class="js-document-return"
                       value="1">
                <label for="DPD_DOCUMENT_RETURN_on" class="radioCheck">{l s='Yes' mod='dpdbaltics'}</label>
                <input type="radio" name="DPD_DOCUMENT_RETURN"
                       id="DPD_DOCUMENT_RETURN_off" value="0"
                       checked="checked">
                <label
                        for="DPD_DOCUMENT_RETURN_off" class="radioCheck">
                    {l s='No' mod='dpdbaltics'}
                </label>
                <a class="slide-button btn"></a>
            </span>
        </div>
    </div>

    <div class="dpd-separator"></div>

    <div class="row">
        <label class="control-label col-xs-2">
            {l s='Document number' mod='dpdbaltics'}
        </label>
        <div class="col-xs-4">
            <input name="dpd_document_return_number" class="fixed-width-lg" type="text"
                   value="{$shipment->document_return_number}">
        </div>
    </div>
</div>