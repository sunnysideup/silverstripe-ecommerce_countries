<?php

/**
 * Data for each in orderstep
 * for each country
 *
 */

class EcommerceOrderStepCountryData extends DataObject
{
    private static $singular_name = "Country specific Order Step Information";
    public function i18n_singular_name()
    {
        return "Country specific Order Step Information";
    }

    private static $plural_name = "Country specific Order Step Information Items";
    public function i18n_plural_name()
    {
        return "Country specific Order Step Information Items";
    }

    private static $db = array(
        'CountrySpecificEmailSubject' => 'Varchar(255)',
        'CountrySpecificEmailMessage' => 'HTMLText'
    );

    private static $has_one = array(
        "OrderStep" => "OrderStep",
        "EcommerceCountry" => "EcommerceCountry"
    );

    private static $required_fields = array(
        "EcommerceCountryID"
    );

    private static $summary_fields = array(
        "EcommerceCountry.Title" => "Country",
        "OrderStep.Title" => "Step",
        "CountrySpecificEmailSubject" => "Subject"

    );

    private static $field_labels = array(
        "CountrySpecificEmailSubject" => "Email Subject",
        "CountrySpecificEmailMessage" => "Email Message"
    );


    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $messageField = new HtmlEditorField('CountrySpecificEmailMessage', "Email Content");
        $messageField->setRows(7);
        $fields->addFieldsToTab(
            'Root.Main',
            array(
            new TextField('CountrySpecificEmailSubject', "Subject"),
            $messageField
        )
        );
        $fields->addFieldToTab(
            "Root.Main",
            new DropdownField(
                "EcommerceCountryID",
                "Country",
                EcommerceCountry::get()->map()
            )
        );
        return $fields;
    }

    /**
     * make sure this entry does not exist yet...
     */
    public function validate()
    {
        $valid = parent::validate();
        $filter = array(
            "OrderStepID" => $this->OrderStepID,
            "EcommerceCountryID" => $this->EcommerceCountryID
        );
        $exclude = array("ID" => $this->ID);
        if (EcommerceOrderStepCountryData::get()->filter($filter)->exclude($exclude)->count()) {
            $valid->error('An entry for this country and order step already exists. Please change the country or review existing records.');
        }
        return $valid;
    }
}
