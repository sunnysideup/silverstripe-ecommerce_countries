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
        $excludedCountries = EcommerceCountry::get()->filter(array("DoNotAllowSales" => 1));
        if($excludedCountries->count()) {
            $excludedCountries = $excludedCountries->map('ID', 'Name')->toArray();
        }
        $includedCountries = EcommerceCountry::get()->filter(array("DoNotAllowSales" => 0));
        if($includedCountries->count())  {
            $includedCountries = $includedCountries->map('ID', 'Name')->toArray();
        }

        $tabs = new TabSet('Countries',
            new Tab(
                'Include',
                new CheckboxField("AllCountries", "All Countries"),
                new LiteralField("ExplanationInclude", "<p>Products are not available in the countries listed below.  You can include sales of <i>".$this->owner->Title."</i> to new countries by ticking the box(es) next to any country.</p>"),
                new CheckboxSetField('IncludedCountries', '', $excludedCountries)
            ),
            new Tab(
                'Exclude',
                new LiteralField("ExplanationExclude", "<p>Products are available in all countries listed below.  You can exclude sales of <i>".$this->owner->Title."</i> from these countries by ticking the box next to any of them.</p>"),
                new CheckboxSetField('ExcludedCountries', '', $includedCountries)
            )
        );

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
     * @param Member $member
     * @return false | null
     */
    function canPurchaseByCountry(Member $member = null, $checkPrice = true) {
        if($this->owner->AllCountries) {
            return null;
        }
        $countryCode = EcommerceCountry::get_country();
        if($countryCode) {
            $included = $this->owner->getManyManyComponents('IncludedCountries', "\"Code\" = '$countryCode'")->Count();
            if($included) {
                return null;
            }
            $excluded = $this->owner->getManyManyComponents('ExcludedCountries', "\"Code\" = '$countryCode'")->Count();
            if($excluded) {
                return false;
            }
        }
        return null;
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
        if($country) {
            $filterArray["Country"] = $country;
        }
        if($currency) {
            $filterArray["Currency"] = $currency;
        }
        return CountryPrice::get()
            ->filter($filterArray);
    }

    /***
     *
     * updates the calculated price to the local price...
     * if there is no price then we return 0
     * @return Float | null (ignore this value and use original value)
     */
    function updateCalculatedPrice() {
        $order = ShoppingCart::current_order();
        $currency = $order->CurrencyUsed();
        // We never uses the NZ value in the CountryPrice table
        $country = EcommerceCountryDOD::get_distributor_country();
        $prices = null;
        if($country && $currency) {
            $prices = $this->owner->CountryPrices(
                $countryCode = $country->Code,
                $currencyCode = strtoupper($currency->Code)
            );
        }
        if($prices && $prices->count() == 1){
            return $prices->First()->Price;
        }
        if(EcommercePayment::site_currency() == $currency->Code) {
            return $this->owner->Price;
        }
        return null;
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
