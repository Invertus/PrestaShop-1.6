
<!-- Shipment footer -->
<div class="panel-footer dpd-shipment-footer">
    <button data-loading-text="<i class='icon icon-spinner icon-spin '></i>{l s=' loading...' mod='dpdbaltics'}"
            type="button"
            class="btn btn-default button pull-right js-shipment-save-btn js-shipment-action-btn {if $testOrder && !$testMode}disabled{/if}"
            data-action="save_and_print">
        <i class="process-icon-save"></i>
        {if 'download' == $printLabelOption}
            {l s='Save & Download label' mod='dpdbaltics'}
        {else}
            {l s='Save & Print label' mod='dpdbaltics'}
        {/if}
    </button>

    <button data-loading-text="<i class='icon icon-spinner icon-spin '></i>{l s=' loading...' mod='dpdbaltics'}"
            type="button"
            class="btn btn-default button pull-right js-shipment-save-btn js-shipment-action-btn {if $testOrder && !$testMode}disabled{/if}"
            data-action="save">
        <i class="process-icon-save"></i>
        {l s='Save' mod='dpdbaltics'}
    </button>
    <a href="#"
       class="btn btn-default pull-right js-print-label-btn"
       style="display: none"
       data-action="print"
       data-shipment-id={$shipment->id}
    >
        <i class="process-icon-save"></i>
        {if 'download' == $printLabelOption}
            {l s='Download label' mod='dpdbaltics'}
        {else}
            {l s='Print label' mod='dpdbaltics'}
        {/if}
    </a>
    <div class="dpd-print-format-block">
        <div class="pull-right dpd-print-format-input form-group row">
            <div class="col-lg-5">
                <label>{l s='Printout format' mod='dpdbaltics'}:</label>
                <select name="label_format" class="form-control js-printout-format-select"
                        id="{$default_label_format}">
                    {foreach $dpdSelectFormatOptions as $option}
                        <option {if $option.value == $selectedFormatOptionId}selected{/if} value="{$option.value}">
                            {$option.name}
                        </option>
                    {/foreach}
                </select>
            </div>
            <div class="col-lg-7 js-custom-image-select-order custom-image-select-order DPD_DEFAULT_LABEL_POSITION">
                <label>{l s='Label position' mod='dpdbaltics'}:</label>
                {include file="../../admin/partials/custom-image-select.tpl"}
            </div>
        </div>
    </div>
</div> <!-- end Shipment -->
