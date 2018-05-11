<?php



/**
 * Adds functionality to Order
 */
class CountryPrice_OrderDOD extends DataExtension
{
    private static $db = array(
        'IP' => 'Varchar(45)',
        'CurrencyCountry' => 'Varchar(3)',
        'OriginatingCountryCode' => 'Varchar(3)'
    );

    private static $has_one = array(
        'Distributor' => 'Distributor'
    );

    private static $searchable_fields = array(
        'DistributorID' => array(
            'title' => 'Distributor'
        ),
        'OriginatingCountryCode' => array(
            'field' => 'TextField',
            'filter' => 'PartialMatchFilter',
            'title' => 'Country Code (e.g. NZ)'
        )
    );

    private static $field_labels = array(
        'CurrencyCountry' => 'Currency Country',
        'OriginatingCountryCode' => 'Country'
    );

    private static $summary_fields = array(
        'Distributor.Title' => 'Distributor',
        'OriginatingCountryCode' => 'OriginatingCountryCode',
        'CurrencyUsed.Title' => 'Currency'
    );

    private static $_number_of_times_we_have_run_localise_order = 0;

    private static $only_allow_within_country_sales = false;

    /**
     * this method basically makes sure that the Order
     * has all the localised stuff attached to it, specifically
     * the right currency
     *
     * @param string|EcommerceCountry   $countryCode
     * @param bool                      $force
     * @param bool                      $runAgain
     */
    public static function localise_order($countryCode = null, $force = false, $runAgain = false)
    {
        $countryCode = EcommerceCountry::get_country_from_mixed_var($countryCode, true);

        if (self::$_number_of_times_we_have_run_localise_order > 0) {
            $runAgain = false;
        }
        if ($runAgain) {
            self::$_number_of_times_we_have_run_localise_order = 0;
        }
        if (self::$_number_of_times_we_have_run_localise_order > 2) {
            return;
        }

        self::$_number_of_times_we_have_run_localise_order++;
        $order = ShoppingCart::current_order();
        if ($order && $order->exists()) {
            if ($order->IsSubmitted()) {
                return true;
            }
            if (! $countryCode) {
                $countryCode = $order->getCountry();
            }
            $currencyObject = CountryPrice_EcommerceCurrency::get_currency_for_country($countryCode);
            if (Config::inst()->get('CountryPrice_OrderDOD', 'only_allow_within_country_sales')) {
                $distributor = $order->getDistributor($countryCode);
                $countryOptions = $distributor->Countries();
                if ($countryOptions && $countryOptions->count()) {
                    EcommerceCountry::set_for_current_order_only_show_countries($countryOptions->column('Code'));
                }
            }
            //check if the billing and shipping address have a country so that they will not be overridden by previous Orders
            //we do this to make sure that the previous address can not change the region and thereby destroy the order in the cart
            if ($billingAddress = $order->CreateOrReturnExistingAddress('BillingAddress')) {
                if (! $billingAddress->Country || $force) {
                    $billingAddress->Country = $countryCode;
                    $billingAddress->write();
                }
            }
            if ($shippingAddress = $order->CreateOrReturnExistingAddress('ShippingAddress')) {
                if (! $shippingAddress->ShippingCountry || $force) {
                    $shippingAddress->ShippingCountry = $countryCode;
                    $shippingAddress->write();
                }
            }

            //if a country code and currency has been set then all is good
            //from there we keep it this way
            if (
                $order->OriginatingCountryCode ==  $countryCode &&
                $order->CurrencyUsedID == $currencyObject->ID
            ) {
                return true;
            }
            $order->resetLocale = true;
            $order->write();
            $order = Order::get()->byID($order->ID);
            $orderHasBeenChanged = false;

            //check currency ...
            if ($order->CurrencyUsedID != $currencyObject->ID) {
                $order->SetCurrency($currencyObject);
                $orderHasBeenChanged = true;
            }
            if ($orderHasBeenChanged) {
                ShoppingCart::reset_order_reference();
                $order->write();
                $items = $order->OrderItems();
                if ($items) {
                    foreach ($items as $item) {
                        $buyable = $item->Buyable(true);
                        if (! $buyable->canPurchase()) {
                            $item->delete();
                        }
                    }
                }
                // Called after because some modifiers use the country field to calculate the values
                $order->calculateOrderAttributes(true);
            }
            self::localise_order($countryCode);
        } else {
            Session::set('temporary_country_order_store', $countryCode);
        }
    }


    public function onInit()
    {
        $this->setCountryDetailsForOrder();
    }

    public function onCalculateOrder()
    {
        $this->setCountryDetailsForOrder();
    }

    public function updateCMSFields(FieldList $fields)
    {
        foreach (array('IP', 'OriginatingCountryCode', 'CurrencyCountry') as $fieldName) {
            $field = $fields->dataFieldByName($fieldName);
            $field = $field->performReadonlyTransformation();
            $fields->addFieldToTab("Root.Country", $field);
            $fields->addFieldToTab(
                'Root.Country',
                DropdownField::create(
                    'DistributorID',
                     _t('Distributor.SINGULAR_NAME', 'Distributor'),
                    array(''=> '--- Please select ---') + Distributor::get()->map()->toArray()
                )
            );
        }
    }

    /**
     * Event handler called after writing to the database.
     */
    public function onAfterWrite()
    {
        if (! $this->owner->DistributorID) {
            if ($defaultDistributor = Distributor::get_default_distributor()) {
                if ($defaultDistributor->exists()) {
                    if ($defaultDistributor->ID) {
                        $this->owner->DistributorID = $defaultDistributor->ID;
                        $this->owner->write();
                    }
                }
            }
        }
    }

    public function canView($member = null)
    {
        return $this->canEdit($member);
    }

    public function canEdit($member = null)
    {
        if (! $member) {
            $member = Member::currentUser();
        }
        if ($member) {
            if ($distributor = $this->owner->Distributor()) {
                foreach ($distributor->Members() as $distributorMember) {
                    if ($member->ID == $distributorMember->ID) {
                        return true;
                    }
                }
            }
        }
    }

    /**
     * it is safer to only allow creation on the front-end...
     *
     */
    public function canCreate($member = null)
    {
        return false;
    }

    /**
     *
     * @param string (optional) $countryCode
     * @return Distributor | null
     */
    public function getDistributor($countryCode = null)
    {
        if ($this->owner->DistributorID) {
            return Distributor::get()->byID($this->owner->DistributorID);
        } else {
            if (!$countryCode) {
                $countryCode = $this->owner->getCountry();
            }
            return Distributor::get_one_for_country($countryCode);
        }
    }

    /**
     * this needs to run as part of the order live update
     *
     */
    protected function setCountryDetailsForOrder()
    {
        if ($this->owner->IsSubmitted()) {
            return;
        }

        //set IP
        $this->owner->IP = EcommerceCountry::get_ip();

        //here we need to get the REAL ORIGINAL COUNTRY
        $countryCode = EcommerceCountry::get_country();
        if (Config::inst()->get('CountryPrice_OrderDOD', 'only_allow_within_country_sales')) {
            $this->owner->CurrencyCountry = $countryCode;
            EcommerceCountry::set_for_current_order_only_show_countries(array($countryCode));
            $this->owner->SetCountryFields($countryCode, $billingAddress = true, $shippingAddress = true);
        }
        $this->owner->OriginatingCountryCode = $countryCode;

        // set currency
        $currencyObject = CountryPrice_EcommerceCurrency::get_currency_for_country($countryCode);
        if ($currencyObject) {
            $this->owner->CurrencyUsedID = $currencyObject->ID;
        }
        //the line below causes a loop!!!
        //$this->owner->SetCurrency($currencyObject);

        $distributor = Distributor::get_one_for_country($countryCode);
        if ($distributor) {
            $this->owner->DistributorID = $distributor->ID;
        }
    }


    /**
     *
     * 1. adds distribut emails to order step emails ... if $step->SendEmailToDistributor === true
     *
     * 2. adds country specific data into arrayData that is used for search
     * and replace in email ...
     *
     * @param ArrayData $arrayData
     */
    public function updateReplacementArrayForEmail(ArrayData $arrayData)
    {
        $step = $this->owner->MyStep();
        $countryCode = $this->owner->getCountry();
        $countryMessageObject = null;
        if ($step && $countryCode) {
            $countryMessageObject = EcommerceOrderStepCountryData::get()
                ->filter(
                    array(
                        "OrderStepID" => $step->ID,
                        "EcommerceCountryID" => CountryPrice_EcommerceCountry::get_real_country($countryCode)->ID
                    )
                )
                ->first();
        }
        if ($countryMessageObject) {
            $arrayData->setField(
                "Subject",
                $countryMessageObject->CountrySpecificEmailSubject
            );
            $arrayData->setField(
                "OrderStepMessage",
                $countryMessageObject->CountrySpecificEmailMessage
            );
        }
        if ($step->SendEmailToDistributor) {
            if ($distributor = $this->owner->Distributor()) {
                $distributorEmail = $distributor->Email;
                if ($distributorEmail) {
                    $bccArray = array($distributorEmail => $distributorEmail);
                    foreach ($distributor->Members() as $member) {
                        if ($member && $member->Email) {
                            $bccArray[$member->Email] = $member->Email;
                        }
                    }
                    $arrayData->setField('BCC', implode(', ', $bccArray));
                }
            }
        }
    }
}
