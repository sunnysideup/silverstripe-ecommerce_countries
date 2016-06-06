<?php



/**
 * Adds functionality to Order
 */
class CountryPrice_OrderDOD extends DataExtension {

    private static $db = array(
        'IP' => 'Varchar(16)',
        'CurrencyCountry' => 'Varchar(3)',
        'OriginatingCountryCode' => 'Varchar(2)'
    );

    private static $number_of_times_we_have_run_localise_order = 0;

    /**
     * this method basically makes sure that the Order
     * has all the localised stuff attached to it, specifically
     * the right currency
     */
    public static function localise_order()
    {
        $order = ShoppingCart::current_order();
        $countryCode = $order->getCountry();
        $currencyObject = CountryPrice_EcommerceCurrency::get_currency_for_country($countryCode);
        EcommerceCountry::set_for_current_order_only_show_countries(array($countryCode));
        if($order->IsSubmitted()) {
            return true;
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
            if(self::$number_of_times_we_have_run_localise_order < 3) {
                self::$number_of_times_we_have_run_localise_order++;
                self::localise_order();
            }
        }
    }


    function onInit() {
        $this->setCountryDetailsForOrder();
    }

    function onCalculateOrder() {
        $this->setCountryDetailsForOrder();
    }

    function updateCMSFields(FieldList $fields) {
        foreach(array("IP", "OriginatingCountryCode", "CurrencyCountry") as $fieldName) {
            $field = $fields->dataFieldByName($fieldName);
            $field = $field->performReadonlyTransformation();
            $fields->addFieldToTab("Root.Country", $field);
            if($distributor = $this->owner->Distributor()) {
                $fields->addFieldToTab("Root.Country", new ReadonlyField("DistributorName", "Distributor Name", $distributor->Name));
            }
        }
    }


    /**
     *
     * @param string (optional) $countryCode
     * @return Distributor | null
     */
    function Distributor($countryCode = null){
        if(!$countryCode) {
            $countryCode = $this->owner->getCountry();
        }
        return distributor::get_one_for_country($countryCode);
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

        //set country
        $countryCode = EcommerceCountry::get_country();
        $this->owner->CurrencyCountry = $countryCode;
        $this->owner->OriginatingCountryCode = $countryCode;
        EcommerceCountry::set_for_current_order_only_show_countries(array($countryCode));
        $this->owner->SetCountryFields($countryCode, $billingAddress = true, $shippingAddress = true);

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
                        "EcommerceCountryID" => EcommerceCountry::get_country_id($countryCode)
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
