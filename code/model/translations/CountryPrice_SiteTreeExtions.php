<?php


class CountryPrice_SiteTreeExtions extends SiteTreeExtension
{
    private static $has_many = array(
        'CountryPriceTranslations' => 'CountryPrice_Translations'
    );

    private static $field_labels = array(
        'CountryPriceTranslations' => 'Translations'
    );

    /**
     * Update Fields
     * @return FieldList
     */
    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldsToTab(
            'Root.Translations',
            GridField::create(
                'CountryPriceTranslations',
                'Translations',
                $this->owner->CountryPriceTranslations(),
                GridFieldConfigForOrderItems::create()
            )
        );
        return $fields;
    }

}
