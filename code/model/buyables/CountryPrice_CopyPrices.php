<?php
/**
 * a class to copy prices from country A to country
 * This can be added to Products, but also to Product Groups
 * and other pages ...
 *
 *
 *
 */
class CountryPrice_CopyPrices extends DataExtension {

    private static $db = array(
        "AllowCopying" => "Boolean"
    );

    function updateCMSFields(FieldList $fields) {
        if($this->owner->ID) {
            $page = is_a($this->owner, 'SiteTree'); // We use singleton to skip the different is_a php versions issues
            $tab = 'Root.Countries.Pricing';
            $fromCountries = CountryPrice_EcommerceCountry::get_real_countries_list();
                //->where('CountryPrice.ObjectClass = \''.$this->owner->ClassName.'\' AND CountryPrice.ObjectID = '.$this->owner->ID.'')
            if($fromCountries && $fromCountries->count()) {
                $fromCountriesArray = $fromCountries->map('Code', 'Name')->toArray();
            }
            else {
                $fromCountriesArray = array();
            }
            $allCountries =  EcommerceCountry::get();
            $toCountries = array();
            foreach($allCountries as $country) {
                $country = CountryPrice_EcommerceCountry::get_real_country($country);
                $toCountries[$country->Code] = $country->Name . ($country->DoNotAllowSales ? ' (Sales not allowed)' : '');
            }
            $countryCurrencies = CountryPrice_EcommerceCurrency::get_currency_per_country();
            $link = CountryPrice_CopyPrices_Controller::get_link($this->owner);
            $fields->addFieldToTab(
                $tab,
                $allowCopyingField = new CheckboxField("AllowCopying", "Allow copying")
            );
            $allowCopyingField->setRightTitle("Turn this on only when you like to copy a bunch of prices.  Otherwise just leave it turned off to avoid accidental copies and speed up the CMS loading times.");
            if(count($fromCountriesArray) && count($toCountries) && $this->owner->AllowCopying) {
                $fields->addFieldsToTab($tab, array(
                    new HeaderField('Copy Prices'),
                    new DropdownField('From', 'From', $fromCountriesArray),
                    new CheckboxSetField('To', 'To', $toCountries),
                    new HiddenField('CountryCurrencies', '', Convert::array2json($countryCurrencies)),
                    new LiteralField('UpdatePriceLink', "<p id=\"UpdatePriceLink\" class=\"message good\"><a href=\"$link\" class=\"action ss-ui-button\" target=\"_blank\">Copy Prices</a></p>")
                ));
            }
        }
    }

    /**
     * update all child buyables and the current buyable prices
     * based on $fromCountry and applied to ALL $toCountries
     * @param  string $fromCountryCode  the country code to copy from
     * @param  array  $toCountriesArray  the country code to copy to
     */
    function updatePrices($fromCountryCode, array $toCountriesArray) {
        $fromCountryObject = EcommerceCountry::get()->filter(array("Code" => $fromCountryCode));
        if($fromCountryObject) {
            $fromCountryObject = CountryPrice_EcommerceCountry::get_real_country($fromCountryObject);
        }
        else {
            user_error('From Country is not valid');
        }
        $currencyObject = $fromCountryObject->EcommerceCurrency();
        if($currencyObject && $currencyObject->Code) {
            $values = $this->getUpdatePriceValues($fromCountryCodeA, $currencyObject->Code, array());
            foreach($toCountriesArray as $toCountryCode) {
                $toCountryCode = CountryPrice_EcommerceCountry::get_real_country($toCountryCode)->Code;
                foreach($values as $value) {
                    $sqlValues[] = "(NOW(),NOW(),{$value[0]},'$toCountryCode','".$currencyObject->Code."','{$value[1]}',{$value[2]})";
                }
            }
            if(isset($sqlValues)) {
                $sqlValues = implode(',', $sqlValues);
                $sql = "
                    INSERT INTO \"CountryPrice\" (\"Created\",\"LastEdited\",\"Price\",\"Country\",\"Currency\",\"ObjectClass\",\"ObjectID\")
                    VALUES $sqlValues
                    ON DUPLICATE KEY
                        UPDATE \"LastEdited\" = VALUES(\"LastEdited\"), \"Price\" = VALUES(\"Price\")";
                DB::query($sql);
            }
        }
    }

    /**
     * returns an array of
     *  - Price
     *  - ClassName
     *  - ID
     * searches through children, until all all childpages have been added
     *
     * @param  string $country [description]
     * @param  array  $values  [description]
     * @return array          [description]
     */
    public function getUpdatePriceValues($fromCountryCode, $currencyCode, array $values) {
        $fromCountryCode = CountryPrice_EcommerceCountry::get_real_country($fromCountryCode)->Code;
        if($this->owner->hasExtension('CountryPrice_BuyableExtension')) {
            $countryPrice = $this->owner->CountryPriceForCountryAndCurrency($fromCountry, $currency);
            if($countryPrice) {
                $price = $countryPrice->First()->Price;
            }
            else {
                $countryPrices = $this->owner->CountryPriceForCountryAndCurrency($fromCountryCode, $currencyCode, $values);
                if($countryPrices && $countryPrices->count()) {
                    $price = $countryPrices->First()->Price;
                }
            }
            if(isset($price)) {
                $values[] = array(
                    $price,
                    $this->owner->ClassName,
                    $this->owner->ID
                );
            }
        }
        if($this->owner->hasExtension('ProductWithVariationDecorator')) {
            $variations = $this->owner->Variations();
            foreach($variations as $variation) {
                $values += $variation->getUpdatePriceValues($fromCountryCode, $currencyCode, $values);
            }
        }
        if(is_a($this->owner, 'SiteTree')) {
            $pages = $this->owner->AllChildren();
            if($pages) {
                foreach($pages as $page) {
                    $values += $page->getUpdatePriceValues($fromCountryCode, $currencyCode, $values);
                }
            }
        }
        return $values;
    }
}
