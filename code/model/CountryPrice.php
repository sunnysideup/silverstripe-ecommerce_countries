<?php

/**
 * Holds prices for individual countries for
 * Buyables.
 *
 *
 */

class CountryPrice extends DataObject {

    // CURRENCY LIST AND STATIC FUNCTIONS

    private static $db = array(
        'Price' => 'Currency',
        'Country' => 'Varchar(2)',
        'Currency' => 'Varchar(3)',
        'ObjectClass' => 'Varchar',
        'ObjectID' => 'Int'
    );

    private static $summary_fields = array(
        'BuyableName' => 'Buyable',
        'CountryName' => 'Country',
        'FullPrice' => 'Price'
    );

    private static $casting = array(
        'BuyableName' => 'Varchar',
        'Title' => 'Varchar',
        'CountryName' => 'Varchar',
        'FullPrice' => 'Varchar'
    );

    private static $indexes = array(
        'Unique' => array(
            'type' => 'unique',
            'value' => 'Country,ObjectClass,ObjectID'
        )
    );

    private static $searchable_fields = array(
        'Price' => 'PartialMatchFilter',
        'Country' => 'PartialMatchFilter',
        'Currency' => 'PartialMatchFilter',
        'ObjectClass' => 'ExactMatchFilter',
        'ObjectID' => 'ExactMatchFilter'
    );


    /**
     * the buyable we relate to
     * return DataObject | null
     */
    function Buyable() {
        $className = $this->ObjectClass;
        if(class_exists($this->ObjectClass)) {
            return $className::get()->byID($this->ObjectID);
        }
    }

    /**
     *
     * return EcommerceCountry | null
     */
    function CountryObject() {
        if($this->Country) {
            return EcommerceCountry::get()->filter(array("Code" => $this->Country))->First();
        }
    }

    /**
     *
     * return EcommerceCountry | null
     */
    function CurrencyObject() {
        if($this->Country) {
            return EcommerceCurrency::get()->filter(array("Code" => $this->Currency))->First();
        }
    }

    /**
     * casted variable
     * @return String
     */
    function getBuyableName() {
        if($obj = $this->Buyable()){
            return $obj->Title;
        }
        return "ERROR: Object not found";
    }

    /**
     * casted variable
     * @return String
     */
    function getTitle() {
        return $this->getBuyableName()." // ".$this->getCountryName()."// ".$this->getFullPrice();
    }

    /**
     * casted variable
     * @return String
     */
    function getCountryName() {
        return EcommerceCountry::find_title($this->Country);
    }

    /**
     * casted variable
     * returns nicely formatted price..
     * @return String
     */
    function getFullPrice() {
        return "$this->Price $this->Currency" . ($this->isObsolete() ? ' (obsolete!)' : '');
    }

    function getCMSFields() {
        $fields = parent::getCMSFields();
        // This works only because only NZ uses NZD
        $countries = EcommerceCountry::get_country_dropdown(false);
        unset($countries[EcommerceConfig::get('EcommerceCountry', 'default_country_code')]);
        $field = new DropdownField('Country', 'Country', $countries);
        $fields->replaceField('Country', $field);

        if($this->ID) {
            $fields->makeFieldReadonly('Country');
            $fields->makeFieldReadonly('Currency');
        }
        else {
            $fields->removeByName('Currency');
        }
        $fields->removeByName('ObjectClass');
        $fields->removeByName('ObjectID');
        if(self::$cms_object) {
            $fields->addFieldToTab("Root.Main", new HiddenField('MyObjectClass', '', self::$cms_object->ClassName));
            $fields->addFieldToTab("Root.Main", new HiddenField('MyObjectID', '', self::$cms_object->ID));
        } else  {
            //to do BuyableSelectField
        }
        return $fields;
    }

    private static $cms_object = null;

    //MUST KEEP
    public static function set_cms_object($o) {self::$cms_object = $o;}

    function canEdit($member = null) {
        $canEdit = parent::canEdit();
        if(! $canEdit) {
            $member = Member::currentUser();
            $distributor = $member->Distributor();
            if($distributor->exists()) {
                return $distributor->getComponents('Countries', "\"Code\" = '$this->Country'")->Count() > 0;
            }
        }
        return $canEdit;
    }

    /**
     * We use validate as an onBeforeWrite as well because in this case it makes sense
     * as in the validation process we add stuff...
     * @return ValidationResult
     */
    protected function validate() {
        $result = parent::validate();
        if( ! $this->ObjectClass && isset( $_REQUEST["MyObjectClass"])) {
            if(class_exists($_REQUEST["MyObjectClass"])) {
                $this->ObjectClass = Convert::raw2sql($_REQUEST["MyObjectClass"]);
            }
        }
        if( ! $this->ObjectID && isset( $_REQUEST["MyObjectID"])) {
            $this->ObjectID = intval($_REQUEST["MyObjectID"]);
        }
        //check for duplicates in case it has not been created yet...
        if( ! $this->ObjectClass || ! $this->ObjectID) {
            $result->error('Object could not be created. Please contact your developer.');
            return $result;
        }
        $currencyPerCountry = self::get_currency_per_country();
        if(!isset($currencyPerCountry[$this->Country])) {
            $result->error("Can not find currency for this country '".$this->Country."'");
        }
        $this->Currency = $currencyPerCountry[$this->Country];
        $duplicates = CountryPrice::get()
            ->exclude(array("ID" => $this->ID - 0))
            ->filter(array("ObjectClass" => $this->ObjectClass,"ObjectID" => $this->ObjectID,"Country" => $this->Country));
        if($duplicates->count()) {
            $result->error('You can not add this price for this country because a price for this country already exists.');
        }
        return $result;
    }

    /**
     * Returns if the currency is an old currency not used anymore.
     * @return Boolean
     */
    function isObsolete() {
        $currencyPerCountry = self::get_currency_per_country();
        if(isset($currencyPerCountry[$this->Country])) {
            return $currencyPerCountry[$this->Country] != $this->Currency;
        }
    }

    /**
     * name of session variable used to set Country
     * @var String
     */
    private static $location_param = 'Location';


    /**
     * country for user
     * @var String
     */
    private static $location_country;

    /**
     * returns Country code
     * @return string
     */
    public static function get_location_country() {return self::$location_country;}

    /**
     * Returns the most "appropriate" country to use the currency of.
     * Condition : The country is always a key present in the $currency_per_country array.
     * @return String
     */
    public static function get_country_for_currency() {
        $country = EcommerceCountryDOD::get_distributor_country();
        $currency = $country->EcommerceCurrency();
        if($currency && $currency->Code) {
            return $country;
        }
        return $country;
    }

    /**
     * @return EcommerceCurrency
     */
    public static function get_currency() {
        $currencyPerCountry = self::get_currency_per_country();
        $country = self::get_country_for_currency();
        $currencyDO = null;
        if($country) {
            $currencyCode = isset($currencyPerCountry[$country->Code]) ? $currencyPerCountry[$country->Code] : EcommerceCountry::default_currency();
            $currencyDO = EcommerceCurrency::get_one_from_code($currencyCode);
        }
        if(! $currencyDO) {
            $currencyDO = EcommerceCurrency::create_new($currencyCode);
        }
        if(!$currencyDO) {
            $currencyDO = EcommerceCurrency::get_default();
        }
        return $currencyDO;
    }

    /**
     *
     * @return array - list of countries and their currencies ...
     */
    public static function get_currency_per_country() {
        $countries = EcommerceCountry::get();
        $array = array();
        $defaultCurrencyCode = EcommerceCurrency::default_currency_code();
        foreach($countries as $country) {
            $currency = $country->EcommerceCurrency();
            $currencyCode = $defaultCurrencyCode;
            if($currency && $currency->exists()) {
                $currencyCode = $currency->Code;
            }
            $array[$country->Code] = $currencyCode;
        }
        return $array;
    }

    /**
     * list of currencies used on the site
     * @return Array
     */
    public static function get_currency_per_country_used_ones() {
        $resultArray = array();
        $countryCodes = EcommerceCountry::get()
            ->filter(array("DoNotAllowSales" => 0))
            ->exclude(array("DistributorID" => 0));
        $countryCurrencies = self::get_currency_per_country();
        if($countryCodes->count()) {
            $countryCodes = $countryCodes->map("Code", "Code")->toArray();
            foreach($countryCodes as $countryCode => $countryName) {
                if(isset($countryCurrencies[$countryCode])) {
                    $resultArray[$countryCode] = self::$countryCurrencies[$countryCode];
                }
            }
        }

        return $resultArray;
    }

}
