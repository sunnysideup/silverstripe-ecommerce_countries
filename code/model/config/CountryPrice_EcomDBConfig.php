<?php

class CountryPrice_EcomDBConfig extends DataExtension
{
    private static $db = array(
        'NoProductsInYourCountry' => 'HTMLText'
    );

    /**
     * Update Fields
     * @return FieldList
     */
    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldsToTab(
            'Root.Products',
            array(
                $field = HTMLEditorField::create('NoProductsInYourCountry', 'Message shown when there are no products available in your country.')
            )
        );
        $field->setRows(7);
        return $fields;
    }


}
