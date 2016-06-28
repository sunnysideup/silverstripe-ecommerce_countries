<?php



/**
 * Adds functionality to Order
 */
class CountryPrice_OrderDOD extends DataExtension {

    private static $db = array(
        'IP' => 'Varchar(16)',
        'CurrencyCountry' => 'Varchar(3)',
        'OriginatingCountryCode' => 'Varchar(3)'
    );

    private static $has_one = array(
        'Distributor' => 'Distributor'
    );

    private static $searchable_fields = array(
        'DistributorID' => 'ExactMatchFilter'
    );

    private static $_number_of_times_we_have_run_localise_order = 0;

    private static $only_allow_within_country_sales = false;

    /**
     * this method basically makes sure that the Order
     * has all the localised stuff attached to it, specifically
     * the right currency
     */
    public static function localise_order($countryCode = null)
    {
        if(self::$_number_of_times_we_have_run_localise_order > 2) {
            return
        }
        self::$_number_of_times_we_have_run_localise_order++;
        $order = ShoppingCart::current_order();
        if($order->IsSubmitted()) {
            return true;
        }
        if( ! $countryCode) {
            $countryCode = $order->getCountry();
        }
        $currencyObject = CountryPrice_EcommerceCurrency::get_currency_for_country($countryCode);
        if(Config::inst()->get('CountryPrice_OrderDOD', 'only_allow_within_country_sales')) {
            EcommerceCountry::set_for_current_order_only_show_countries(array($countryCode));
        }

        //if a country code and currency has been set then all is good
        //from there we keep it this way
        if(
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
        if($order->CurrencyUsedID != $currencyObject->ID) {
            $order->SetCurrency($currencyObject);
            $orderHasBeenChanged = true;
        }
        if($orderHasBeenChanged) {
            $order->write();
            $items = $order->OrderItems();
            if($items) {
                foreach($items as $item) {
                    $buyable = $item->Buyable(true);
                    if( ! $buyable->canPurchase()) {
                        $item->delete();
                    }
                }
            }
            // Called after because some modifiers use the country field to calculate the values
            $order->calculateOrderAttributes(true);
        }
        self::localise_order($countryCode);
    }


    function onInit() {
        $this->setCountryDetailsForOrder();
    }

    function onCalculateOrder() {
        $this->setCountryDetailsForOrder();
        $countryCode = $this->owner->getCountry();
        $distributor = Distributor::get_one_for_country($countryCode);
        if($distributor) {
            $this->owner->DistributorID = $distributor->ID;
        }
    }

    function updateCMSFields(FieldList $fields) {
        foreach(array("IP", "OriginatingCountryCode", "CurrencyCountry") as $fieldName) {
            $field = $fields->dataFieldByName($fieldName);
            $field = $field->performReadonlyTransformation();
            $fields->addFieldToTab("Root.Country", $field);
            $fields->addFieldToTab(
                'Root.Country',
                DropdownField::create(
                    'DistributorID',
                    'Distributor',
                    array(''=> '--- Please select ---') + Distributor::get()->map()->toArray()
                )
            );
        }
    }

    function canView($member = null) {
        return $this->canEdit($member);
    }

    function canEdit($member = null) {
        if($member) {
            if($distributor = $this->owner->Distributor()) {
                foreach($distributor->Members() as $distributorMember) {
                    if($member->ID == $distributorMember->ID) {
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
    function canCreate($member) {
        return false;
    }

    /**
     *
     * @param string (optional) $countryCode
     * @return Distributor | null
     */
    function getDistributor($countryCode = null){
        if($this->owner->DistributorID) {
            return Distributor::get()->byID($this->owner->DistributorID);
        } else {
            if(!$countryCode) {
                $countryCode = $this->owner->getCountry();
            }
            return Distributor::get_one_for_country($countryCode);
        }
    }

    /**
     * this needs to run as part of the order live update
     *
     */
    protected function setCountryDetailsForOrder() {
        if($this->owner->IsSubmitted()) {
            return;
        }

        //set IP
        $this->owner->IP = EcommerceCountry::get_ip();

        //here we need to get the REAL ORIGINAL COUNTRY
        $countryCode = EcommerceCountry::get_country();
        if(Config::inst()->get('CountryPrice_OrderDOD', 'only_allow_within_country_sales')) {
            $this->owner->CurrencyCountry = $countryCode;
            EcommerceCountry::set_for_current_order_only_show_countries(array($countryCode));
            $this->owner->SetCountryFields($countryCode, $billingAddress = true, $shippingAddress = true);
        }
        $this->owner->OriginatingCountryCode = $countryCode;

        // set currency
        $currencyObject = CountryPrice_EcommerceCurrency::get_currency_for_country($countryCode);
        if($currencyObject) {
            $this->owner->CurrencyUsedID = $currencyObject->ID;
        }
        //the line below causes a loop!!!
        //$this->owner->SetCurrency($currencyObject);
    }


    /**
     *
     * adds email to order step emails ...
     */
    function updateReplacementArrayForEmail(ArrayData $arrayData) {
        $step = $this->owner->MyStep();
        $countryCode = $this->owner->getCountry();
        $countryMessage = null;
        if($step && $countryCode) {
            $countryMessageObject = EcommerceOrderStepCountryData::get()
                ->filter(
                    array(
                        "OrderStepID" => $step->ID,
                        "EcommerceCountryID" => CountryPrice_EcommerceCountry::get_real_country($countryCode)
                    )
                )
                ->first();
        }
        if($countryMessageObject) {
            $arrayData->setField("Subject", $countryMessageObject->CountrySpecificEmailSubject);
            $arrayData->setField("OrderStepMessage", $countryMessageObject->CountrySpecificEmailMessage);
        }
        if($distributor = $this->owner->Distributor($countryCode)) {
            #### START EXCEPTION FOR
            $distributorEmail = $distributor->Email;
            if($distributorEmail) {
                $arrayData->setField("CC", $distributorEmail);
            }
        }
    }


}
