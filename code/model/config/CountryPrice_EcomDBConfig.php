<?php

class CountryPrice_EcomDBConfig extends DataExtension
{
    private static $db = array(
        'NoProductsInYourCountry' => 'HTMLText'
    );

    /**
     * Update Fields
     * @return FieldList
     */
    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldsToTab(
            'Root.Products',
            array(
                $field = HTMLEditorField::create('NoProductsInYourCountry', 'Message shown when there are no products available in your country.')
            )
        );
        $field->setRows(7);
        return $fields;
    }

    /**
     *
     *
     * @return ArrayList
     */
    function ChooseNewCountryList()
    {
        $countries = CountryPrice_EcommerceCountry::get_real_countries_list();
        $currentCode = EcommerceCountry::get_country();
        $al = ArrayList::create();
        foreach($countries as $country) {
            $isCurrentOne = $currentCode == $country->Code ? true : false;
            $currency = null;
            if($isCurrentOne) {
                $currency = CountryPrice_EcommerceCurrency::get_currency_for_country($country->Code);
            }
            $al->push(
                ArrayData::create(
                    array(
                        'Link' => CountryPrices_ChangeCountryController::new_country_link($country->Code),
                        'Title' => $country->Name,
                        'LinkingMode' => ($isCurrentOne? 'current' : 'link'),
                        'Currency' => $currency
                    )
                )
            );
        }
        return $al;
    }

}
