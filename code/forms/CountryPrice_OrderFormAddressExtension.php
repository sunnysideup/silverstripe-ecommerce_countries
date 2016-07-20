<?php


class CountryPrice_OrderFormAddressExtension extends Extension
{
    function updateFields($fields)
    {
        $shippingField = $fields->dataFieldByName('ShippingCountry');
        $originalSource = $shippingField->getSource();
        $newSource = $originalSource;
        $allowedCountries = CountryPrice_EcommerceCountry::get_sibling_countries();
        if($allowedCountries->count()) {
            $allowedCountryCodes = $allowedCountries->column('Code');
            foreach($newSource as $key => $value) {
                if( ! in_array($key, $allowedCountryCodes)) {
                    unset($newSource[$key]);
                }
            }
            $shippingField->setSource($newSource);
            $js = '
                var CountryPrice_ShippingCountry_Options_Original = '.Convert::array2json($originalSource).'
                var CountryPrice_ShippingCountry_Options_New = '.Convert::array2json($newSource).'';
            Requirements::customScript($js, 'CountryPrice_OrderFormAddressExtension_updateFields');
            Requirements::javascript('ecommerce_countries/javascript/CountryPrice_SetCountriesForDelivery.js');
        }
    }
}
