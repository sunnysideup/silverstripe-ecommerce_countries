<?php

/**
 * Adds currency to invididual countries
 *
 */

class EcommerceCurrencyDOD extends DataExtension {

    private static $has_many = array(
        "EcommerceCountries" => "EcommerceCountry"
    );

    function updateCMSFields(FieldList $fields) {
        $fields->addFieldToTab(
            "Root.Countries",
            new CheckboxSetField::create(
                'EcommerceCountries',
                'Countries',
                EcommerceCountry::get()
                    ->filter(array("EcommerceCurrencyID" => array(0, $this->ID)))
                    ->map()
            )
        );
    }

}
