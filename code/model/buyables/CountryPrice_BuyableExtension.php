<?php
/**
 * Adds pricing to Buyables
 *
 *
 */

class CountryPrice_BuyableExtension extends DataExtension
{
    private static $db = array(
        "AllCountries" => "Boolean"
    );

    private static $many_many = array(
        "IncludedCountries" => "EcommerceCountry",
        "ExcludedCountries" => "EcommerceCountry"
    );

    private static $allow_usage_of_distributor_backup_country_pricing = false;

    public function updateCMSFields(FieldList $fields)
    {
        $excludedCountries = EcommerceCountry::get()
            ->filter(array("DoNotAllowSales" => 1, "AlwaysTheSameAsID" => 0));
        if ($excludedCountries->count()) {
            $excludedCountries = $excludedCountries->map('ID', 'Name')->toArray();
        }
        $includedCountries = EcommerceCountry::get()
            ->filter(array("DoNotAllowSales" => 0, "AlwaysTheSameAsID" => 0));
        if ($includedCountries->count()) {
            $includedCountries = $includedCountries->map('ID', 'Name')->toArray();
        }
        if ($this->owner->AllCountries) {
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
            if (count($excludedCountries)) {
                $includeTab->push(
                    new LiteralField(
                        "ExplanationInclude",
                        "<p>Products are not available in the countries listed below.  You can include sales of <i>".$this->owner->Title."</i> to new countries by ticking the box(es) next to any country.</p>"
                    )
                );
                $includeTab->push(
                    new CheckboxSetField('IncludedCountries', '', $excludedCountries)
                );
            }
            if (count($includedCountries)) {
                $excludeTab->push(
                    new LiteralField("ExplanationExclude", "<p>Products are available in all countries listed below.  You can exclude sales of <i>".$this->owner->Title."</i> from these countries by ticking the box next to any of them.</p>")
                );
                $excludeTab->push(
                    new CheckboxSetField('ExcludedCountries', '', $includedCountries)
                );
            }
        }


        if ($this->owner->ID) {
            //start cms_object hack
            CountryPrice::set_cms_object($this->owner);
            //end cms_object hack
            $source = $this->owner->AllCountryPricesForBuyable();
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

    private $debug = false;

    /**
     * This is called from /ecommerce/code/Product
     * returning NULL is like returning TRUE OR FALSE, i.e. ignore this.
     * @param Member (optional)   $member
     * @param bool (optional)     $checkPrice
     * @return false | null
     */
    public function canPurchaseByCountry(Member $member = null, $checkPrice = true, $countryCode = '')
    {
        $countryObject = CountryPrice_EcommerceCountry::get_real_country($countryCode);
        if ($countryObject) {
            if ($this->debug) {debug::log('found country object: '.$countryObject->Code);}
            $countryCode = $countryObject->Code;
        }
        if ($countryCode == '') {
            if ($this->debug) {debug::log('There is no country Code! ');}

            //we can not decide
            return null;
        } else {
            $canSell = false;

            //easy  ... overrules all ...
            if ($this->owner->AllCountries) {
                //is there a valid price ???
                if ($this->debug) {debug::log('All countries applies - updated  ... new price = '.floatval($this->owner->updateCalculatedPrice()));}
                $canSell = true;
            } else {


                //excluded first...
                $excluded = $this->owner->getManyManyComponents('ExcludedCountries', "\"Code\" = '$countryCode'")->Count();
                if ($excluded) {
                    if ($this->debug) {debug::log('excluded country');}

                    //no!
                    return false;
                }

                //default country is included by default ...
                if ($countryCode == EcommerceConfig::get('EcommerceCountry', 'default_country_code')) {
                    if ($this->debug) {debug::log('we are in the default country! exiting now ... ');}
                    $canSell = true;
                } elseif($this->owner->IncludedCountries()->count()) {
                    $included = $this->owner->getManyManyComponents('IncludedCountries', "\"Code\" = '$countryCode'")->Count();
                    if ($included) {
                        if ($this->debug) {debug::log('In included countries');}
                        //null basically means - ignore ...
                        $canSell = true;
                    } else {
                        //if countries are included and the current country is not included ...
                        return false;
                    }
                }
            }
            if ($this->debug) {debug::log('the product is '.($canSell ? '' : 'NOT ').' for sale - lets check price ... ');}

            //is there a valid price ???
            $countryPrice = $this->owner->getCalculatedPrice(true);
            if ($this->debug) {debug::log('nothing applies, but we have a country price... '.$countryPrice);}

            return floatval($countryPrice) > 0 ? null : false;
        }
    }

    /**
     *
     * @return DataList
     */
    public function AllCountryPricesForBuyable()
    {
        $filterArray = array("ObjectClass" => ClassInfo::subclassesFor($this->ownerBaseClass), "ObjectID" => $this->owner->ID);
        return CountryPrice::get()
            ->filter($filterArray);
    }

    /**
     * returns all the prices for a particular country and/or currency
     * for the object
     * @param string (optional) $country
     * @param string (optional) $currency
     * @return DataList
     */
    public function CountryPricesForCountryAndCurrency($countryCode = null, $currency = null)
    {
        $countryObject = CountryPrice_EcommerceCountry::get_real_country($countryCode);
        $allCountryPricesForBuyable = $this->AllCountryPricesForBuyable();
        if ($countryObject) {
            $filterArray["Country"] = $countryObject->Code;
        }
        if ($currency) {
            $filterArray["Currency"] = $currency;
        }
        $allCountryPricesForBuyable = $allCountryPricesForBuyable->filter($filterArray);
        return $allCountryPricesForBuyable;
    }

    private static $_buyable_price = array();

    /***
     *
     * updates the calculated price to the local price...
     * if there is no price then we return 0
     * if the default price can be used then we use NULL (i.e. ignore it!)
     * @param float $price (optional)
     * @return Float | null (ignore this value and use original value)
     */
    public function updateBeforeCalculatedPrice($price = null)
    {
        $countryCode = '';
        $countryObject = CountryPrice_EcommerceCountry::get_real_country();
        if ($countryObject) {
            $countryCode = $countryObject->Code;
        }
        if ($countryCode === '' || $countryCode === EcommerceConfig::get('EcommerceCountry', 'default_country_code')) {
            if ($this->debug) {
                debug::log('No country code or default country code: '.$countryCode);
            }

            return null;
        }
        $key = $this->owner->ClassName."___".$this->owner->ID.'____'.$countryCode;
        if (! isset(self::$_buyable_price[$key])) {
            //basics
            $currency = null;
            $currencyCode = null;

            if ($countryCode) {
                $order = ShoppingCart::current_order();
                //if the order has never been localised, then we do this now!!!!
                if(count(self::$_buyable_price) === 0) {
                    CountryPrice_OrderDOD::localise_order($countryCode, $force = true, $runAgain = true);

                    // CRUCIAL!!!!
                    // reload order with new values!
                    $order = ShoppingCart::current_order();
                }
                if($order && $order->exists()) {
                    $currency = $order->CurrencyUsed();
                }
                if($currency && $currency->exists()) {
                    //do nothing
                } else {
                    $currency = CountryPrice_EcommerceCurrency::get_currency_for_country($countryCode);
                }
                if ($currency) {
                    $currencyCode = strtoupper($currency->Code);
                    //1. exact price for country
                    if ($currencyCode) {
                        $prices = $this->owner->CountryPricesForCountryAndCurrency(
                            $countryCode,
                            $currencyCode
                        );
                        if ($prices && $prices->count() == 1) {
                            self::$_buyable_price[$key] = $prices->First()->Price;
                            return self::$_buyable_price[$key];
                        } elseif ($prices) {
                            if ($this->debug) {
                                debug::log('MAIN COUNTRY: There is an error number of prices: '.$prices->count().' based on a search for '.$countryCode.' - '.$currencyCode);
                            }
                        } else {
                            if ($this->debug) {
                                debug::log('MAIN COUNTRY: There is no country price: ');
                            }
                        }
                    } else {
                        if ($this->debug) {
                            debug::log('MAIN COUNTRY: There is no currency code '.$currencyCode.'');
                        }
                    }
                } else {
                    if ($this->debug) {
                        debug::log('MAIN COUNTRY: there is no currency');
                    }
                }
                if (Config::inst()->get('CountryPrice_BuyableExtension', 'allow_usage_of_distributor_backup_country_pricing')) {
                    //there is a specific country price ...
                    //check for distributor primary country price
                    // if it is the same currency, then use that one ...
                    $distributorCountry = CountryPrice_EcommerceCountry::get_distributor_primary_country($countryCode);
                    if ($distributorCurrency = $distributorCountry->EcommerceCurrency()) {
                        if ($distributorCurrency->ID == $currency->ID) {
                            $distributorCurrencyCode = strtoupper($distributorCurrency->Code);
                            $distributorCountryCode = $distributorCountry->Code;
                            if ($distributorCurrencyCode && $distributorCountryCode) {
                                $prices = $this->owner->CountryPricesForCountryAndCurrency(
                                    $distributorCountryCode,
                                    $distributorCurrencyCode
                                );
                                if ($prices && $prices->count() == 1) {
                                    self::$_buyable_price[$key] = $prices->First()->Price;

                                    return self::$_buyable_price[$key];
                                } elseif ($prices) {
                                    if ($this->debug) {
                                        debug::log('BACKUP COUNTRY: There is an error number of prices: '.$prices->count());
                                    }
                                } else {
                                    if ($this->debug) {
                                        debug::log('BACKUP COUNTRY: There is no country price: ');
                                    }
                                }
                            } else {
                                if ($this->debug) {
                                    debug::log('BACKUP COUNTRY: We are missing the distributor currency code ('.$distributorCurrencyCode.') or the distributor country code ('.$distributorCountryCode.')');
                                }
                            }
                        } else {
                            if ($this->debug) {
                                debug::log('BACKUP COUNTRY: The distributor currency ID ('.$distributorCurrency->ID.') is not the same as the order currency ID ('.$currency->ID.').');
                            }
                        }
                    }
                } else {
                    if ($this->debug) {
                        debug::log('We do not allow backup country pricing');
                    }
                }
            } else {
                if ($this->debug) {
                    debug::log('There is not Country Code ');
                }
            }
            //order must have a country and a currency
            if (! $currencyCode ||  ! $countryCode) {
                if ($this->debug) {
                    debug::log('No currency ('.$currencyCode.') or no country code ('.$countryCode.') for order: ');
                }
            }
            //catch error 2: no country price BUT currency is not default currency ...
            if (EcommercePayment::site_currency() != $currencyCode) {
                if ($this->debug) {
                    debug::log('site currency  ('.EcommercePayment::site_currency().') is not the same order currency ('.$currencyCode.')');
                }

            } else {
                if ($this->debug) {
                    debug::log('SETTING '.$key.' to ZERO - NOT FOR SALE');
                }
            }
            self::$_buyable_price[$key] = 0;
        }

        return self::$_buyable_price[$key];
    }

    /**
     * delete the related prices
     */
    public function onBeforeDelete()
    {
        $prices = $this->AllCountryPricesForBuyable();
        if ($prices && $prices->count()) {
            foreach ($prices as $price) {
                $price->delete();
            }
        }
    }

    // VARIATION CODE ONLY

    // We us isNew to presave if we should add some country price for the newy created variation based on the "possibly" pre-existing ones of the product
    protected $isNew = false;

    public function onBeforeWrite()
    {
        $this->isNew = $this->owner->ID == 0;
    }


    public function onAfterWrite()
    {
        //only run if these are variations
        if ($this->isNew && $this->owner instanceof ProductVariation) {
            $product = $this->owner->Product();
            if ($product) {
                $productPrices = $product->AllCountryPricesForBuyable();
                foreach ($productPrices as $productPrice) {
                    if ($productPrice->Country) {
                        if (
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
                        } else {
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
    public function canEdit($member = null)
    {
        if (! $member) {
            $member = Member::currentUser();
        }
        if ($member) {
            $distributor = $member->Distributor();
            if ($distributor->exists()) {
                return true;
            }
        }
        return false;
    }
}
