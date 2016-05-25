<?php



/**
 * Adds functionality to Order
 */
class CountryPrice_OrderDOD extends DataExtension {

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
