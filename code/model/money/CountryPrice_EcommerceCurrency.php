<?php

/**
 * Adds currency to invididual countries
 *
 */

class CountryPrice_EcommerceCurrency extends DataExtension {

    private static $has_many = array(
        "EcommerceCountries" => "EcommerceCountry"
    );

    function updateCMSFields(FieldList $fields) {
        $fields->addFieldToTab(
            "Root.Countries",
            CheckboxSetField::create(
                'EcommerceCountries',
                'Countries',
                EcommerceCountry::get()
                    ->filter(array("EcommerceCurrencyID" => array(0, $this->owner->ID)))
                    ->sort(array("EcommerceCurrencyID" => "DESC", "Name" => "ASC"))
                    ->map()
            )
        );
        $fields->removeFieldFromTab("Root", "EcommerceCountries");
    }


    /**
     * @param strin $countryCode
     * @return EcommerceCurrency
     */
    public static function get_currency_for_country($countryCode) {
        $currencyPerCountry = CountryPrice_EcommerceCurrency::get_currency_per_country();
        if($countryCode) {
            $currencyCode = isset($currencyPerCountry[$countryCode]) ? $currencyPerCountry[$countryCode] : EcommerceCountry::default_currency();
            $currencyDO = EcommerceCurrency::get_one_from_code($currencyCode);
        }
        if( ! $currencyDO) {
            $currencyDO = EcommerceCurrency::create_new($currencyCode);
        }
        if( ! $currencyDO) {
            $currencyDO = EcommerceCurrency::get_default();
        }
        return $currencyDO;
    }

    /**
     * finds the currency for each country
     * If no currency is found then the default currency is added.
     * returns something like
     *
     *     NZ => NZD
     *     AU => AUD
     *
     * @return array - list of countries and their currencies ...
     */
    public static function get_currency_per_country() {
        $cachekey = "EcommerceCurrencyCountryMatrix";
        $cache = SS_Cache::factory($cachekey);
        if ( ! ($serializedArray = $cache->load($cachekey))) {

            $countries = EcommerceCountry::get();
            $unserializedArray = array();
            $defaultCurrencyCode = EcommerceCurrency::default_currency_code();
            foreach($countries as $country) {
                $currency = $country->EcommerceCurrency();
                $currencyCode = $defaultCurrencyCode;
                if($currency && $currency->exists()) {
                    $currencyCode = $currency->Code;
                }
                $unserializedArray[$country->Code] = $currencyCode;
                $cache->save(serialize($unserializedArray), $cachekey);
            }
            return $unserializedArray;
        }
        return unserialize($serializedArray);
    }

    /**
     * list of currencies used on the site
     * @return Array
     */
    public static function get_currency_per_country_used_ones() {
        $resultArray = array();
        $functioningCountryObjects = EcommerceCountry::get()
            ->filter(array("DoNotAllowSales" => 0))
            ->exclude(array("DistributorID" => 0));
        $countryCurrencies = CountryPrice_EcommerceCurrency::get_currency_per_country();
        if($functioningCountryObjects->count()) {
            $countryCodes = $functioningCountryObjects->map("Code", "Code")->toArray();
            foreach($countryCodes as $countryCode => $countryCodeAlso) {
                if(isset($countryCurrencies[$countryCode])) {
                    $resultArray[$countryCode] = $countryCurrencies[$countryCode];
                }
            }
        }

        return $resultArray;
    }


}
