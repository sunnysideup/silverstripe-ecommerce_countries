<?php

/**
 * Holds prices for individual countries for
 * Buyables.
 *
 *
 */

class CountryPrice extends DataObject
{

    // CURRENCY LIST AND STATIC FUNCTIONS

    private static $db = array(
        'Price' => 'Currency',
        'Country' => 'Varchar(2)',
        'Currency' => 'Varchar(3)',
        'ObjectClass' => 'Varchar',
        'ObjectID' => 'Int'
    );

    private static $field_labels = array(
        'Currency' => 'Currency Code',
        'Country' => 'Country Code',
        'ObjectClass' => 'Buyable Name',
        'ObjectID' => 'Buyable ID'
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
        ),
        'Currency' => true
    );

    private static $searchable_fields = array(
        'Price' => 'PartialMatchFilter',
        'Country' => 'PartialMatchFilter',
        'Currency' => 'PartialMatchFilter'
    );


    /**
     * the buyable we relate to
     * return DataObject | null
     */
    public function Buyable()
    {
        $className = $this->ObjectClass;
        if (class_exists($this->ObjectClass)) {
            return $className::get()->byID($this->ObjectID);
        }
    }

    /**
     *
     * return EcommerceCountry | null
     */
    public function CountryObject()
    {
        if ($this->Country) {
            return EcommerceCountry::get()->filter(array("Code" => $this->Country))->First();
        }
    }

    /**
     *
     * return EcommerceCountry | null
     */
    public function CurrencyObject()
    {
        if ($this->Country) {
            return EcommerceCurrency::get()->filter(array("Code" => $this->Currency))->First();
        }
    }

    /**
     * casted variable
     * @return String
     */
    public function getBuyableName()
    {
        if ($obj = $this->Buyable()) {
            return $obj->Title;
        }
        return "ERROR: Object not found";
    }

    /**
     * casted variable
     * @return String
     */
    public function getTitle()
    {
        return $this->getBuyableName()." // ".$this->getCountryName()."// ".$this->getFullPrice();
    }

    /**
     * casted variable
     * @return String
     */
    public function getCountryName()
    {
        return EcommerceCountry::find_title($this->Country);
    }

    /**
     * casted variable
     * returns nicely formatted price..
     * @return String
     */
    public function getFullPrice()
    {
        return "$this->Price $this->Currency" . ($this->isObsolete() ? ' (obsolete!)' : '');
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        // This works only because only NZ uses NZD
        $countries = CountryPrice_EcommerceCountry::get_real_countries_list()->map('Code', 'Name')->toArray();
        unset($countries[EcommerceConfig::get('EcommerceCountry', 'default_country_code')]);
        $field = DropdownField::create('Country', 'Country', $countries);
        $fields->replaceField('Country', $field);

        if ($this->ID) {
            $fields->makeFieldReadonly('Country');
            $list = EcommerceCurrency::ecommerce_currency_list()->exclude(array("Code" => $this->Currency));
            if ($list->count()) {
                $listArray = array($this->Currency => $this->Currency) + $list->map("Code", "Name")->toArray();
            } else {
                $listArray = array($this->Currency => $this->Currency);
            }
            $fields->replaceField(
                'Currency',
                DropdownField::create(
                    'Currency',
                    'Currency',
                    $listArray
                )
            );
        } else {
            $fields->removeByName('Currency');
        }
        $fields->removeByName('ObjectClass');
        $fields->removeByName('ObjectID');
        if (self::$cms_object) {
            $fields->addFieldToTab("Root.Main", new HiddenField('MyObjectClass', '', self::$cms_object->ClassName));
            $fields->addFieldToTab("Root.Main", new HiddenField('MyObjectID', '', self::$cms_object->ID));
        } else {
            //to do BuyableSelectField
        }
        $buyable = $this->Buyable();
        if ($buyable && $buyable->exists()) {
            $fields->addFieldToTab(
                'Root.Main',
                $buyableLink = ReadonlyField::create(
                    'ProductOrService',
                    'Product or Service',
                    '<a href="'.$buyable->CMSEditLink().'">'.$this->getBuyableName().'</a>'
                )
            );
            $buyableLink->dontEscape = true;
        }
        $fields->addFieldsToTab(
            'Root.Debug',
            array(
                ReadonlyField::create('ObjectClass', 'ObjectClass'),
                ReadonlyField::create('ObjectID', 'ObjectID')
            )
        );
        return $fields;
    }

    private static $cms_object = null;

    //MUST KEEP
    public static function set_cms_object($o)
    {
        self::$cms_object = $o;
    }

    public function canEdit($member = null)
    {
        $canEdit = parent::canEdit();
        if (! $canEdit) {
            $member = Member::currentUser();
            $distributor = $member->Distributor();
            if ($distributor->exists()) {
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
    protected function validate()
    {
        $result = parent::validate();
        if (! $this->ObjectClass && isset($_REQUEST["MyObjectClass"])) {
            if (class_exists($_REQUEST["MyObjectClass"])) {
                $this->ObjectClass = Convert::raw2sql($_REQUEST["MyObjectClass"]);
            }
        }
        if (! $this->ObjectID && isset($_REQUEST["MyObjectID"])) {
            $this->ObjectID = intval($_REQUEST["MyObjectID"]);
        }
        //check for duplicates in case it has not been created yet...
        if (! $this->ObjectClass || ! $this->ObjectID) {
            $result->error('Object could not be created. Please contact your developer.');
            return $result;
        }
        $currencyPerCountry = CountryPrice_EcommerceCurrency::get_currency_per_country();
        if (!isset($currencyPerCountry[$this->Country])) {
            $result->error("Can not find currency for this country '".$this->Country."'");
        }
        $this->Currency = $currencyPerCountry[$this->Country];
        $duplicates = CountryPrice::get()
            ->exclude(array("ID" => $this->ID - 0))
            ->filter(array("ObjectClass" => $this->ObjectClass, "ObjectID" => $this->ObjectID, "Country" => $this->Country));
        if ($duplicates->count()) {
            $result->error('You can not add this price for this country because a price for this country already exists.');
        }
        return $result;
    }

    /**
     * Returns if the currency is an old currency not used anymore.
     * @return Boolean
     */
    public function isObsolete()
    {
        $currencyPerCountry = CountryPrice_EcommerceCurrency::get_currency_per_country();
        if (isset($currencyPerCountry[$this->Country])) {
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
    public static function get_location_country()
    {
        return self::$location_country;
    }
}
