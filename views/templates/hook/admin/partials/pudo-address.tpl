{*
 * NOTICE OF LICENSE
 *
 * @author    INVERTUS, UAB www.invertus.eu <support@invertus.eu>
 * @copyright Copyright (c) permanent, INVERTUS, UAB
 * @license   Addons PrestaShop license limitation
 * @see       /LICENSE
 *
 *  International Registered Trademark & Property of INVERTUS, UAB
 *}
{if $has_parcel_shops}
    <div class="js-pickup-point panel col-lg-6">
        <div class="panel-heading">
            {if isset($selectedPudo)}
                {l s='Pickup point: ' mod='dpdbaltics'}<b class="js-parcel-name">{$selectedPudo->getCompany()}</b>
            {else}
                {l s='Please select pick up point.' mod='dpdbaltics'}
            {/if}
        </div>
        <div class="panel-body">
            <input name="id_pudo" hidden class="hidden">
            <input name="is_pudo" hidden class="hidden" value="{$is_pudo}">
            {include file='../../admin/partials/pudo-info.tpl'}
            <div class="col-lg-8 hidden search-container">
                <div class="dpd-pudo-container">
                    {include file='../../admin/partials/dpd-message.tpl' messageType='error'}
                    {include file='../../admin/partials/pudo-search-block.tpl'}
                    <div class="clearfix">&nbsp;</div>
                    <div class="clearfix">&nbsp;</div>
                    <div class="dpd-services-block"></div>
                </div>
            </div>
        </div>
        <div class="panel-footer">
            <button type="button" class="pull-right btn btn-default js-pudo-expand collapsed"
                    data-open="{l s='Change' mod='dpdbaltics'}" data-close="{l s='Close' mod='dpdbaltics'}">
                {l s='Change' mod='dpdbaltics'}
            </button>
        </div>
    </div>
{/if}