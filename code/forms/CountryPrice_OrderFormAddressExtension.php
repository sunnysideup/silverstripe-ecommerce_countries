<?php


class CountryPrice_OrderFormAddressExtension extends Extension
{
    public function updateFields($fields)
    {
        $shippingField = $fields->dataFieldByName('ShippingCountry');
        $originalSource = $shippingField->getSource();
        $newSource = $originalSource;
        $allowedCountries = CountryPrice_EcommerceCountry::get_sibling_countries();
        $allowedCountries = $allowedCountries->exclude('OnlyShowChildrenInDropdowns', true);
        if ($allowedCountries->count()) {
            $allowedCountryCodes = $allowedCountries->column('Code');
            foreach ($newSource as $key => $value) {
                if (! in_array($key, $allowedCountryCodes)) {
                    unset($newSource[$key]);
                }
            }
            $shippingField->setSource($newSource);
            $js = '
                var CountryPrice_SetCountriesForDelivery_Original = '.Convert::array2json($originalSource).';
                var CountryPrice_SetCountriesForDelivery_New      = '.Convert::array2json($newSource).';';
            Requirements::customScript($js, 'CountryPrice_OrderFormAddressExtension_updateFields');
        }
    }
}
