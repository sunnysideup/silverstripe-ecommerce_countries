<?php

/**
 * Adds fields to individual countries.
 *
 */

class CountryPrice_EcommerceCountry extends DataExtension
{
    private static $db = array(
        'IsBackupCountry' => 'Boolean',
        'FAQContent' => 'HTMLText',
        'TopBarMessage' => 'Varchar(255)',
        'DeliveryCostNote' => 'Varchar(255)',
        'ShippingEstimation' => 'Varchar(255)',
        'ReturnInformation' => 'Varchar(255)',
        'ProductNotAvailableNote' => 'HTMLText',
        'LanguageAndCountryCode' => 'Varchar(20)'
    );

    private static $has_one = array(
        'Distributor' => 'Distributor',
        'EcommerceCurrency' => 'EcommerceCurrency',
        'AlwaysTheSameAs' => 'EcommerceCountry'
    );

    private static $has_many = array(
        'ParentFor' => 'EcommerceCountry'
    );

    private static $searchable_fields = array(
        "AlwaysTheSameAsID" => true,
        "IsBackupCountry" => "ExactMatchFilter"
    );

    private static $default_sort = array(
        'Name' => 'DESC'
    );


    private static $indexes = array(
        "IsBackupCountry" => true
    );

    private static $casting = array(
        "LanguageAndCountryCode" => 'Varchar'
    );

    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldToTab(
            "Root.ParentCountry",
            DropdownField::create(
                'AlwaysTheSameAsID',
                'Parent Country',
                array('' => '--- PLEASE SELECT ---') + EcommerceCountry::get()->filter(array("AlwaysTheSameAsID" => 0))->exclude(array("ID" => $this->owner->ID))->map("ID", "Name")->toArray()
            )
        );
        if ($this->owner->AlwaysTheSameAsID) {
            $removeByNameArray = array(
                'IsBackupCountry',
                'DoNotAllowSales',
                'FAQContent',
                'TopBarMessage',
                'DeliveryCostNote',
                'ShippingEstimation',
                'ReturnInformation',
                'ProductNotAvailableNote',
                'DistributorID',
                'EcommerceCurrencyID',
                'ParentFor',
                'Regions'
            );
            foreach ($removeByNameArray as $removeByNameField) {
                $fields->removeByName(
                    $removeByNameField
                );
            }
        } else {
            $fields->addFieldToTab('Root.Messages', TextField::create('TopBarMessage', 'Top Bar Message')->setRightTitle("also see the site config for default messages"));
            if ($this->owner->DistributorID) {
                $FAQContentField = new HtmlEditorField('FAQContent', 'Content');
                $FAQContentField->setRows(7);
                $FAQContentField->setColumns(7);
                $fields->addFieldToTab('Root.FAQPage', $FAQContentField);
            } else {
                $fields->addFieldToTab(
                    'Root.FAQPage',
                    new LiteralField(
                        "FAQPageExplanation",
                        "<p class=\"message warning\">FAQ information can only be added to the main country for a ". _t('Distributor.SINGULAR_NAME', 'Distributor') ."</p>"
                    )
                );
            }

            $distributors = Distributor::get()
                ->filter(array("IsDefault" => 0));
            $distributors = $distributors->count() ? $distributors->map('ID', 'Name')->toArray() : array();
            $distributors = array('' => '--- PLEASE SELECT ---') + $distributors;
            $fields->addFieldToTab(
                'Root.Main',
                DropdownField::create('DistributorID', _t('Distributor.SINGULAR_NAME', 'Distributor'), array(0 => "-- Not Selected --") + $distributors),
                "DoNotAllowSales"
            );

            $fields->addFieldToTab(
                "Root.Testing",
                new LiteralField("LogInAsThisCountry", "<h3><a href=\"/whoami/setmycountry/".$this->owner->Code."/?countryfortestingonly=".$this->owner->Code."\">place an order as a person from ".$this->owner->Title."</a></h3>")
            );

            $fields->addFieldToTab(
                "Root.Currency",
                $fields->dataFieldByName("EcommerceCurrencyID")
            );
        }
    }

    public static function get_real_countries_list()
    {
        return EcommerceCountry::get()
            ->filter(array('DoNotAllowSales' => 0, 'AlwaysTheSameAsID' => 0));
    }

    /**
     *
     *
     * @param  [type] $countryCode [description]
     * @return DataList
     */
    public static function get_sibling_countries($countryCode = null)
    {
        $countryObject = self::get_real_country($countryCode);
        if ($countryObject->AlwaysTheSameAsID) {
            return EcommerceCountry::get()
                ->filterAny(
                    array(
                        'AlwaysTheSameAsID' => array($countryObject->AlwaysTheSameAsID),
                        "ID" => array($countryObject->AlwaysTheSameAsID, $countryObject->ID)
                    )
                );
        } else {
            return EcommerceCountry::get()
                ->filterAny(
                    array(
                        'AlwaysTheSameAsID' => $countryObject->ID,
                        'ID' => $countryObject->ID
                    )
                );
        }
    }

    /**
     * checks if the country has a distributor
     * and returns it.  If not, returns the defaulf country.
     *
     * @return EcommerceCountry
     *
     */
    public static function get_distributor_country($countryCode = null)
    {
        $countryObject = CountryPrice_EcommerceCountry::get_real_country($countryCode);
        if ($countryObject && $countryObject->hasDistributor()) {
            //do nothing ...
        } else {
            $countryObject = self::get_backup_country();
        }
        return $countryObject;
    }

    /**
    * checks if the country has a distributor
    * and returns the primary country for the distributor.
    * If not, returns the defaulf country.
     *
     * @return EcommerceCountry
     *
     */
    public static function get_distributor_primary_country($countryCode = null)
    {
        $countryObject = CountryPrice_EcommerceCountry::get_real_country($countryCode);
        if ($countryObject && $countryObject->hasDistributor()) {
            return $countryObject->Distributor()->PrimaryCountry();
            //do nothing ...
        } else {
            $countryObject = self::get_backup_country();
        }
        return $countryObject;
    }

    private static $_get_real_country_cache = array();

    /**
     * returns the 'always the same as' (parent) country if necessary
     * @param  EcommerceCountry | string | int   (optional)  $country
     *
     * @return EcommerceCountry
     */
    public static function get_real_country($country = null)
    {

        if($country && $country instanceof EcommerceCountry) {
            $cacheKey = $country->Code;
        } elseif($country) {
            $cacheKey = $country;
        } else {
            $cacheKey = 'notprovided';
        }
        if(isset(self::$_get_real_country_cache[$cacheKey]) && self::$_get_real_country_cache[$cacheKey]) {

        } else {
            //save original - just in case...
            $originalCountry = $country;

            //no country provided
            if (! $country) {
                $param = Config::inst()->get('CountryPrice_Translation', 'locale_get_parameter');

                // 1. CHECK FROM URL
                $urlCountryCode = null;
                if (isset($_GET[$param])) {
                    $urlCountryCode = Convert::raw2sql(preg_replace("/[^A-Z]+/", "", strtoupper($_GET[$param])));
                }

                // 2. CHECK WHAT THE SYSTEM THINKS THE COUNTRY CHOULD BE

                //now we check it from order / session ....
                $order = ShoppingCart::current_order();
                if($order && $order->exists()) {
                    Session::clear('temporary_country_order_store');
                    $countryCode = $order->getCountry();
                } else {
                    $countryCode = Session::get('temporary_country_order_store');
                }

                //if we still dont have a country then we use the standard e-commerce methods ...
                if(! $countryCode) {
                    $countryCode = EcommerceCountry::get_country();
                }

                //lets make our object!
                if($countryCode) {
                    $country = DataObject::get_one('EcommerceCountry', ['Code' => $countryCode]);
                }

                if($country && $country instanceof EcommerceCountry) {
                    //do nothing
                } else {
                    $country = null;
                }
                //IF THE COUNTRY DOES NOT MATCH THE URL COUNTRY THEN THE URL WINS!!!!
                if($urlCountryCode) {
                    if (
                            ($country && $country->Code !== $urlCountryCode)
                        ||
                            ! $country

                    ){
                        $country = DataObject::get_one('EcommerceCountry', ['Code' => $urlCountryCode]);
                        if($country) {
                            //change country Object
                            //reset everything ...
                            CountryPrices_ChangeCountryController::set_new_country($country);

                            // return self::get_real_country($country);
                        } else {
                            return $this->redirect('404-country-not-found');
                        }
                    } else {

                    }
                }
            }


            //MAKE SURE WE HAVE AN OBJECT
            //get the Object
            if ($country instanceof EcommerceCountry) {
                //do nothing
            } elseif (is_numeric($country) && intval($country) == $country) {
                $country = EcommerceCountry::get()->byID($country);
            } elseif (is_string($country)) {
                $country = strtoupper($country);
                $country = EcommerceCountry::get_country_object(false, $country);
            }


            //LOOK FOR REPLACEMENT COUNTRIES
            //substitute (always the same as) check ....
            if ($country && $country instanceof EcommerceCountry) {
                if ($country->AlwaysTheSameAsID) {
                    $realCountry = $country->AlwaysTheSameAs();
                    if ($realCountry && $realCountry->exists()) {
                        $country = $realCountry;
                    }
                }
            } else {
                //last chance ... do this only once ...
                $countryCode = EcommerceCountry::get_country_default();
                if ($countryCode && !$originalCountry) {
                    $country = self::get_real_country($countryCode);
                }
            }

            //FINAL BOARDING CALL!
            //surely we have one now???
            if ($country && $country instanceof EcommerceCountry) {
                //do nothing
            } else {
                //final backup....
                $country = EcommerceCountry::get()->first();
            }

            //set to cache ...
            self::$_get_real_country_cache[$cacheKey] = $country;

        }

        return self::$_get_real_country_cache[$cacheKey];

    }

    /**
     *
     * @return EcommerceCountry
     */
    public static function get_backup_country()
    {
        $obj = EcommerceCountry::get()->filter(array("IsBackupCountry" => true))->first();
        if (! $obj) {
            $obj = EcommerceCountry::get()->filter(array("Code" => EcommerceConfig::get('EcommerceCountry', 'default_country_code')))->first();
            if (! $obj) {
                $obj = EcommerceCountry::get()->first();
            }
        }
        return $obj;
    }


    /**
     *
     *
     * @return boolean
     */
    public function hasDistributor()
    {
        $countryObject = CountryPrice_EcommerceCountry::get_real_country($this->owner);

        return
            $countryObject->DistributorID &&
            $countryObject->Distributor() &&
            $countryObject->Distributor()->exists();
    }


    /**
     * make sure there is always a backup country ...
     */
    public function requireDefaultRecords()
    {
        $backupCountry = EcommerceCountry::get()->filter(array("IsBackupCountry" => 1))->first();
        if (!$backupCountry) {
            $backupCountry = self::get_backup_country();
            if ($backupCountry) {
                $backupCountry->IsBackupCountry = true;
                $backupCountry->write();
            }
        }
        if ($backupCountry) {
            DB::query("UPDATE EcommerceCountry SET IsBackupCountry = 0 WHERE EcommerceCountry.ID <> ".$backupCountry->ID);
            DB::alteration_message("Creating back-up country");
        } else {
            DB::alteration_message("Back-up country has not been set", "deleted");
        }
    }

    /**
     * @return string
     */
    public function ComputedLanguageAndCountryCode()
    {
        if ($this->owner->LanguageAndCountryCode) {
            return $this->owner->LanguageAndCountryCode;
        }
        return strtolower('en-'.$this->owner->Code);
    }
}
