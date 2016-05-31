<?php



/**
 * Adds functionality to Order
 */
class CountryPrice_OrderDOD extends DataExtension {

    private static $number_of_times_we_have_run_localise_order = 0;

    public static function localise_order()
    {
        $order = ShoppingCart::current_order();
        $currentCountry = EcommerceCountry::get_country();
        $currencyObject = CountryPrice::get_currency();
        EcommerceCountry::set_for_current_order_only_show_countries(array($currentCountry));
        if($order->IsSubmitted()) {
            return true;
        }
        //if a country code and currency has been set then all is good
        //from there we keep it this way
        if(
            $order->OriginatingCountryCode ==  $currentCountry &&
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
                    if(! $buyable->canPurchase()) {
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

    private $resetLocale = false;

    private static $db = array(
        'IP' => 'Varchar(16)',
        'CurrencyCountry' => 'Varchar(3)',
        'OriginatingCountryCode' => 'Varchar(2)'
    );

    function onInit() {
        $this->setCountryDetailsForOrder();
    }

    function onCalculateOrder() {
        $this->setCountryDetailsForOrder();
    }

    function updateCMSFields(FieldList $fields) {
        $field = $fields->dataFieldByName("CurrencyCountry");
        $field->setTitle("Country used");
        $country = $this->owner->MyCurrencyCountry();
        if($country) {
            $field->setValue($country->Name);
        }
        $field->setTitle("Country used");
        $field = $field->performReadonlyTransformation();
        $fields->addFieldToTab("Root.Country", $field);
        if($distributor = $this->owner->Distributor()) {
            $fields->addFieldToTab("Root.Country", new ReadonlyField("DistributorName", "Distributor Name", $distributor->Name));
        }
    }

    function updateReplacementArrayForEmail(ArrayData $arrayData) {
        $step = $this->owner->MyStep();
        $country = $this->owner->MyCurrencyCountry();
        $countryMessage = null;
        if($step && $country) {
            $countryMessage = EcommerceOrderStepCountryData::get()
                ->filter(
                    array(
                        "OrderStepID" => $step->ID,
                        "EcommerceCountryID" => $country->ID
                    )
                )
                ->first();
        }
        if($countryMessage) {
            $arrayData->setField("Subject", $countryMessage->CountrySpecificEmailSubject);
            $arrayData->setField("OrderStepMessage", $countryMessage->CountrySpecificEmailMessage);
        }
        if($distributor = $this->Distributor($country)) {
            #### START EXCEPTION FOR
            $distributorEmail = $distributor->Email;
            $arrayData->setField("CC", $distributorEmail);
        }
    }

    function Distributor($country = null){
        if(!$country) {
            $country = $this->owner->MyCurrencyCountry();
        }
        if($country) {
            return $country->Distributor();
        }
    }

    /**
     *
     * @return EcommerceCountry
     */
    function MyCurrencyCountry(){
        return EcommerceCountry::get()
            ->filter(array("Code" => $this->owner->CurrencyCountry))
            ->First();
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
        $country = EcommerceCountry::get_country();
        $this->owner->CurrencyCountry = $country;
        $this->owner->OriginatingCountryCode = $country;
        EcommerceCountry::set_for_current_order_only_show_countries(array($country));
        $this->owner->SetCountryFields($country, $billingAddress = true, $shippingAddress = true);

        // set currency
        $currencyObject = CountryPrice::get_currency();
        $currency = CountryPrice::get_currency();
        $this->owner->SetCurrency($currencyObject);
        $this->owner->ExchangeRate = 1;
    }



}
