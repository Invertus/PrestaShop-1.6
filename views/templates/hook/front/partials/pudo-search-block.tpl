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

<div class="search-block-container row">
    <div class="col-xl-6 col-lg-6 col-xs-6 dpd-city-block dpd-input-wrapper">
        <select name="dpd-city" class="form-control-chosen chosen-select">
            {if !empty($city_list)}
                {if !$selected_city}
                    <option selected value=""> {l s='Please select a city' mod='dpdbaltics'}</option>
                {/if}
                {foreach from=$city_list item=city}
                    <option {if strtolower($selected_city) === strtolower($city)}selected{/if}
                            value="{$city|escape:'htmlall':'UTF-8'}">{$city|escape:'htmlall':'UTF-8'}</option>
                {/foreach}
            {/if}
        </select>
        <div class="control-label dpd-input-placeholder dpd-select-placeholder">
            {l s='City' mod='dpdbaltics'}
        </div>
    </div>
    <div class="col-xl-6 col-lg-6 col-xs-6 dpd-city-block dpd-input-wrapper js-pudo-search-street">
        {include file='./pudo-search-street.tpl'}
    </div>
</div>
