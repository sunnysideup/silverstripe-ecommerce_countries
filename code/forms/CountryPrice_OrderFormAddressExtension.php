<?php


class CountryPrice_OrderFormAddressExtension extends Extension
{
    function updateFields($fields)
    {
        $shippingField = $fields->dataFieldByName('ShippingCountry');
        $source = $shippingField->getSource();
        $allowedCountries = CountryPrice_EcommerceCountry::get_sibling_countries();
        if($allowedCountries->count()) {
            $allowedCountryCodes = $allowedCountries->column('Code');
            foreach($source as $key => $value) {
                if( ! in_array($key, $allowedCountryCodes)) {
                    unset($source[$key]);
                }
            }
            $shippingField->setSource($source);
        }
    }
}
