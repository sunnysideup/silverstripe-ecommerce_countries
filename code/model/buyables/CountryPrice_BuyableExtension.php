<?php
/**
 * Adds pricing to Buyables
 *
 *
 */

class CountryPrice_BuyableExtension extends DataExtension {


    private static $db = array(
        "AllCountries" => "Boolean"
    );

    private static $many_many = array(
        "IncludedCountries" => "EcommerceCountry",
        "ExcludedCountries" => "EcommerceCountry"
    );


    function updateCMSFields(FieldList $fields) {
        $excludedCountries = EcommerceCountry::get()
            ->filter(array("DoNotAllowSales" => 1, "AlwaysTheSameAsID" => 0));
        if($excludedCountries->count()) {
            $excludedCountries = $excludedCountries->map('ID', 'Name')->toArray();
        }
        $includedCountries = EcommerceCountry::get()
            ->filter(array("DoNotAllowSales" => 0, "AlwaysTheSameAsID" => 0));
        if($includedCountries->count())  {
            $includedCountries = $includedCountries->map('ID', 'Name')->toArray();
        }
        if($this->owner->AllCountries) {
            $tabs = new TabSet('Countries',
                new Tab(
                    'Include',
                    new CheckboxField("AllCountries", "All Countries")
                )
            );
        } else {
            $tabs = new TabSet('Countries',
                $includeTab = new Tab(
                    'Include',
                    new CheckboxField("AllCountries", "All Countries")
                ),
                $excludeTab = new Tab(
                    'Exclude'
                )
            );
            if(count($excludedCountries)) {
                $includeTab->push(
                    'Include',
                    new LiteralField("ExplanationInclude", "<p>Products are not available in the countries listed below.  You can include sales of <i>".$this->owner->Title."</i> to new countries by ticking the box(es) next to any country.</p>")
                );
                $includeTab->push(
                    'Include',
                    new CheckboxSetField('IncludedCountries', '', $excludedCountries)
                );
            }
            if(count($includedCountries)) {
                $excludeTab->push(
                    new LiteralField("ExplanationExclude", "<p>Products are available in all countries listed below.  You can exclude sales of <i>".$this->owner->Title."</i> from these countries by ticking the box next to any of them.</p>")
                );
                $excludeTab->push(
                    new CheckboxSetField('ExcludedCountries', '', $includedCountries)
                );
            }
        }


        if($this->owner->ID) {
            //start cms_object hack
            CountryPrice::set_cms_object($this->owner);
            //end cms_object hack
            $source = $this->owner->CountryPrices();
            $table = new GridField(
                'CountryPrices',
                'Country Prices',
                $source,
                GridFieldConfig_RecordEditor::create()
            );
            $tab = 'Root.Countries.Pricing';
            $fields->addFieldsToTab(
                $tab,
                array(
                    NumericField::create('Price', 'Main Price', '', 12),
                    HeaderField::create('OtherCountryPricing', "Prices for other countries"),
                    $table
                )
            );
        }

        $fields->addFieldToTab('Root.Countries', $tabs);
    }


    /**
     * This is called from /ecommerce/code/Product
     * returning NULL is like returning TRUE, i.e. ignore this.
     * @param Member (optional)   $member
     * @param bool (optional)     $checkPrice
     * @return false | null
     */
    function canPurchaseByCountry(Member $member = null, $checkPrice = true) {
        if($this->owner->AllCountries) {
            //is there a valid price ???
            return $this->updateCalculatedPrice() !== 0 ? null : false;
        }
        $countryCode = EcommerceCountry::get_country();
        $countryCode = CountryPrice_EcommerceCountry::get_real_country($countryCode);
        if($countryCode) {
            $included = $this->owner->getManyManyComponents('IncludedCountries', "\"Code\" = '$countryCode'")->Count();
            if($included) {
                //is there a valid price ???
                return floatval($this->updateCalculatedPrice()) > 0 ? null : false;

            }
            $excluded = $this->owner->getManyManyComponents('ExcludedCountries', "\"Code\" = '$countryCode'")->Count();
            if($excluded) {
                return false;
            }
        }
        //is there a valid price ???
        return floatval($this->updateCalculatedPrice()) > 0 ? null : false;
    }

    /**
     * returns all the prices for a particular country and/or currency
     * for the object
     * @param string (optional) $country
     * @param string (optional) $currency
     * @return DataList
     */
    function CountryPrices($country = null, $currency = null) {
        $filterArray = array("ObjectClass" => ClassInfo::subclassesFor($this->ownerBaseClass), "ObjectID" => $this->owner->ID);
        $country = CountryPrice_EcommerceCountry::get_real_country($country);
        if($country) {
            $filterArray["Country"] = $country;
        }
        if($currency) {
            $filterArray["Currency"] = $currency;
        }
        return CountryPrice::get()
            ->filter($filterArray);
    }

    private static $_buyable_price = array();

    /***
     *
     * updates the calculated price to the local price...
     * if there is no price then we return 0
     * @return Float | null (ignore this value and use original value)
     */
    function updateCalculatedPrice() {
        $key = $this->owner->ClassName."___".$this->owner->ID;
        if( ! isset(self::$_buyable_price[$key])) {
            //basics
            $currency = null;
            $currencyCode = null;
            //order stuff
            $order = ShoppingCart::current_order();
            $countryCode = $order->getCountry();
            $countryCode = CountryPrice_EcommerceCountry::get_real_country($countryCode);
            if($countryCode) {
                $currency = $order->CurrencyUsed();
                if($currency) {
                    $currencyCode = strtoupper($currency->Code);

                    //1. exact price for country
                    if($currencyCode) {
                        $prices = $this->owner->CountryPrices(
                            $countryCode,
                            $currencyCode
                        );
                        if($prices && $prices->count() == 1){
                            self::$_buyable_price[$key] = $prices->First()->Price;
                            return self::$_buyable_price[$key];
                        }
                    }
                }
                //there is a specific country price ...
                //check for distributor primary country price
                // if it is the same currency, then use that one ...
                $distributorCountry = CountryPrice_EcommerceCountry::get_distributor_primary_country($countryCode);
                if($distributorCurrency = $distributorCountry->EcommerceCurrency()) {
                    if($distributorCurrency->ID == $currency->ID) {
                        $distributorCurrencyCode = strtoupper($distributorCurrency->Code);
                        $distributorCountryCode = $distributorCountry->Code;
                        if($distributorCurrencyCode && $distributorCountryCode) {
                            $prices = $this->owner->CountryPrices(
                                $distributorCountryCode,
                                $distributorCurrencyCode
                            );
                            if($prices && $prices->count() == 1){
                                self::$_buyable_price[$key] = $prices->First()->Price;
                                return self::$_buyable_price[$key];
                            }
                        }
                    }
                }
            }
            //order must have a country and a currency
            if( ! $currencyCode ||  ! $countryCode) {
                self::$_buyable_price[$key] = 0;
                return self::$_buyable_price[$key];
            }
            //catch error 2: no country price BUT currency is not default currency ...
            if($currency && EcommercePayment::site_currency() != $currency->Code) {
                self::$_buyable_price[$key] = 0;
                return self::$_buyable_price[$key];
            }
            self::$_buyable_price[$key] = null;
            return self::$_buyable_price[$key];
        }
        return self::$_buyable_price[$key];
    }

    /**
     * delete the related prices
     */
    function onBeforeDelete() {
        $prices = $this->CountryPrices();
        if($prices && $prices->count()) {
            foreach($prices as $price) {
                $price->delete();
            }
        }
    }

    // VARIATION CODE ONLY

    // We us isNew to presave if we should add some country price for the newy created variation based on the "possibly" pre-existing ones of the product
    protected $isNew = false;

    function onBeforeWrite() {
        $this->isNew = $this->owner->ID == 0;
    }


    function onAfterWrite() {
        //only run if these are variations
        if($this->isNew && $this->owner instanceof ProductVariation) {
            $product = $this->owner->Product();
            if($product) {
                $productPrices = $product->CountryPrices();
                foreach($productPrices as $productPrice) {
                    if($productPrice->Country) {
                        if(
                            $countryVariationPrice = CountryPrice::get()
                            ->filter(
                                array(
                                    "Country" => $productPrice->Country,
                                    "ObjectClass" => $this->owner->ClassName,
                                    "ObjectID" => $this->owner->ID
                                )
                            )
                            ->First()
                        ) {
                            //do nothing
                        }
                        else {
                            $countryVariationPrice = new CountryPrice(
                                array(
                                    'Price' => $productPrice->Price,
                                    'Country' => $productPrice->Country,
                                    'Currency' => $productPrice->Currency,
                                    'ObjectClass' => $this->owner->ClassName,
                                    'ObjectID' => $this->owner->ID
                                )
                            );
                            $countryVariationPrice->write();
                        }
                    }
                }
            }
        }
    }

    /**
     * as long as we do not give distributors access to the Products
     * this is fairly safe.
     * @param member (optiona) $member
     * @return null / bool
     */
    function canEdit($member = null)
    {
        if( ! $member ) {
            $member = Member::currentUser();
        }
        if($member) {
            $distributor = $member->Distributor();
            if($distributor->exists()) {
                return true;
            }
        }
        return false;
    }
}
