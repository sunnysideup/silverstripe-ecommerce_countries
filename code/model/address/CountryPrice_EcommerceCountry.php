<?php

/**
 * Adds fields to individual countries.
 *
 */

class CountryPrice_EcommerceCountry extends DataExtension {

    private static $db = array(
        'IsBackupCountry' => 'Boolean',
        'FAQContent' => 'HTMLText',
        'TopBarMessage' => 'Varchar(255)',
        'DeliveryCostNote' => 'Varchar(255)',
        'ShippingEstimation' => 'Varchar(255)',
        'ReturnInformation' => 'Varchar(255)',
        'ProductNotAvailableNote' => 'HTMLText'
    );

    private static $has_one = array(
        'Distributor' => 'Distributor',
        'EcommerceCurrency' => 'EcommerceCurrency'
    );

    private static $searchable_fields = array(
        "IsBackupCountry"
    );

    private static $indexes = array(
        "IsBackupCountry" => true
    );

    function updateCMSFields(FieldList $fields) {
        $fields->addFieldToTab('Root.Messages', TextField::create('TopBarMessage', 'Top Bar Message')->setRightTitle("also see the site config for default messages"));
        if($this->owner->DistributorID) {
            $FAQContentField = new HtmlEditorField('FAQContent', 'Content');
            $FAQContentField->setRows(7);
            $FAQContentField->setColumns(7);
            $fields->addFieldToTab('Root.FAQPage', $FAQContentField);
        }
        else {
            $fields->addFieldToTab(
                'Root.FAQPage',
                new LiteralField(
                    "FAQPageExplanation",
                    "<p class=\"message warning\">FAQ information can only be added to the main country for a Distributor</p>"
                )
            );
        }

        $distributors = Distributor::get()
            ->filter(array("IsDefault" => 0));
        $distributors = $distributors->count() ? $distributors->map('ID', 'Name')->toArray() : array();
        $fields->addFieldToTab('Root.Main', new DropdownField('DistributorID', 'Distributor', array(0 => "-- Not Selected --") + $distributors), "DoNotAllowSales");


        $fields->addFieldToTab(
            "Root.Testing",
            new LiteralField("LogInAsThisCountry", "<h3><a href=\"/whoami/setmycountry/".$this->owner->Code."/?countryfortestingonly=".$this->owner->Code."\">place an order as a person from ".$this->owner->Title."</a></h3>")
        );

        $fields->addFieldToTab(
            "Root.Currency",
            $fields->dataFieldByName("EcommerceCurrencyID")
        );
    }

    /**
     * make sure there is always a backup country ...
     */
    function requireDefaultRecords(){
        $backupCountry = EcommerceCountry::get()->filter(array("IsBackupCountry" => 1))->first();
        if(!$backupCountry) {
            $backupCountry = self::get_backup_country();
            if($backupCountry) {
                $backupCountry->IsBackupCountry = true;
                $backupCountry->write();
            }
        }
        if($backupCountry) {
            DB::query("UPDATE EcommerceCountry SET IsBackupCountry = 0 WHERE EcommerceCountry.ID <> ".$backupCountry->ID);
            DB::alteration_message("Creating back-up country");
        }
        else {
            DB::alteration_message("Back-up country has not been set", "deleted");
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
        $countryObject = EcommerceCountry::get_country_object($countryCode);
        if($countryObject && $countryObject->hasDistributor()) {
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
        $countryObject = EcommerceCountry::get_country_object(false, $countryCode);
        if($countryObject && $countryObject->hasDistributor()) {
            $countryObject->Distributor()->PrimaryCountry();
            //do nothing ...
        } else {
            $countryObject = self::get_backup_country();
        }
        return $countryObject;
    }

    /**
     *
     * @return EcommerceCountry
     */
    public static function get_backup_country(){
        $obj = EcommerceCountry::get()->filter(array("IsBackupCountry" => true))->first();
        if( ! $obj) {
            $obj = EcommerceCountry::get()->filter(array("Code" => EcommerceConfig::get('EcommerceCountry', 'default_country_code')))->first();
            if( ! $obj) {
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
    public function hasDistributor(){
        return $this->owner->DistributorID && $this->owner->Distributor()->exists();
    }

}
