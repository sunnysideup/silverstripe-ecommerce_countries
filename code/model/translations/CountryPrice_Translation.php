<?php



class CountryPrice_Translation extends DataObject
{
    private static $db = array(
        'Title' => 'Varchar(200)',
        'Content' => 'HTMLText'
    );

    private static $field_labels = array(
        'Title' => 'Page Title',
        'Content' => 'Page Content',
        'EcommerceCountryID' => 'Country'
    );

    private static $has_one = array(
        'EcommerceCountry' => 'EcommerceCountry',
        'Parent' => 'SiteTree'
    );

    private static $indexes = array(
        'EcommerceCountryPageUnique' => array(
            'type' => 'unique',
            'value' => 'EcommerceCountryID,ParentID'
        )
    );
    private static $summary_fields = array(
        'EcommerceCountry.Name' => 'Country',
        'Title' => 'Title'
    );

    /**
     * Standard SS variable.
     *
     * @var string
     */
    private static $singular_name = 'Tanslation';
    public function i18n_singular_name()
    {
        return self::$singular_name;
    }

    /**
     * Standard SS variable.
     *
     * @var string
     */
    private static $plural_name = 'Translations';
    public function i18n_plural_name()
    {
        return self::$plural_name;
    }

    /**
     * CMS Fields
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->addFieldToTab(
            'Root.Main',
            DropdownField::create(
                'EcommerceCountryID',
                'Country',
                CountryPrice_EcommerceCountry::get_real_countries_list()->map()->toArray()
            ),
            'Title'
        );
        return $fields;
    }

    function canCreate($member = null) {
        if(CountryPrice_EcommerceCountry::get_real_countries_list()->count()) {
            return parent::canCreate($member);
        }
        return false;
    }

}
