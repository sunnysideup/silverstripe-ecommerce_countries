<?php

class CountryPrice_ProductGroup extends DataExtension
{
    private static $db = array(
        'NoProductsInYourCountry' => 'HTMLText'
    );
    
    private static $casting = array(
        'NoProductsInYourCountryMessage' => 'HTMLText'
    );

    /**
     * Update Fields
     * @return FieldList
     */
    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldsToTab(
            'Root.Countries',
            array(
                $field = HTMLEditorField::create('NoProductsInYourCountry', 'No product message')
            )
        );
        $field->setRows(7)->setDescription('Shown when there are no products for sale in the customer\'s country.');
        return $fields;
    }

    function getNoProductsInYourCountryMessage()
    {
        if($this->owner->NoProductsInYourCountry) {
            $message = $this->owner->NoProductsInYourCountry;
        } else {
            $message = $this->owner->EcomConfig()->NoProductsInYourCountry;
        }
        return $message;
    }
}
