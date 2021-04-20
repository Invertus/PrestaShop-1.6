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

<div id="order-opc-errors" class="alert alert-danger hidden">
    <p></p>
</div>

{if (!isset($disableMap) || (isset($disableMap) && !$disableMap))}
    <div class="container dpd-checkout-pickup-container dpd-pudo-container pickup-map-{$carrierId}" data-id="{$carrierId}" {if isset($dpdIdCarrier) && $dpdIdCarrier}data-pudo-id-carrier="{$dpdIdCarrier}"{/if}>
        <div class="panel panel-default">
            {include file='./partials/dpd-message.tpl' messageType='error'}

            <div class="panel-heading">
                <p class="text-left"> {if $current_controller != 'orderopc'}{l s='Please select your pick up point' mod='dpdbaltics'}{/if}</p>
            </div>
            <div class="panel-body">
                {include file='./partials/pudo-search-block.tpl'}
                <div class="clearfix">
                    &nbsp;
                </div>
                {if $pickUpMap}
                    <div id="dpd-pudo-map-{$carrierId}"></div>
                {/if}
                <div class="points-container {if $show_shop_list === 'LIST'}dpd-hidden{/if}">
                    {include file='./partials/markers-list.tpl' pudoServices=$pudoServices}
                </div>
            </div>

            {*holds map coordinates. Needed on async action*}
            <input class="dpd-hidden hidden" name="pudo-markers" value="">

            {*inidcated default coordinates if pudo map data is not available*}
            {if isset($coordinates.lat) && isset($coordinates.lng)}
                <input class="dpd-hidden hidden" name="default-lat" value="{$coordinates.lat|floatval}">
                <input class="dpd-hidden hidden" name="default-lng" value="{$coordinates.lng|floatval}">
            {/if}
        </div>
        <div class="clearfix"></div>
    </div>
{/if}