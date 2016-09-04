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

    private static $summary_fields = array(
        'EcommerceCountry.Name' => 'Country',
        'Title' => 'Title'
    );

    /**
     * Standard SS variable.
     *
     * @var string
     */
    private static $singular_name = 'Translation';
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
        $countries = CountryPrice_EcommerceCountry::get_real_countries_list()->map()->toArray();
        $fields->addFieldToTab(
            'Root.Main',
            DropdownField::create(
                'EcommerceCountryID',
                'Country',
                array('' => '-- make sure to select a country --')+$countries
            ),
            'Title'
        );
        $fields->removeFieldFromTab("Root.Main", 'ParentID');
        return $fields;
    }

    function canCreate($member = null) {
        if(CountryPrice_EcommerceCountry::get_real_countries_list()->count()) {
            return parent::canCreate($member);
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function validate()
    {
        $validation = parent::validate();
        if($validation->valid()) {
            if($this->exists()) {
                $existing = CountryPrice_Translation::get()
                    ->exclude(array("ID" => $this->ID))
                    ->filter(
                        array(
                            "EcommerceCountryID" => $this->EcommerceCountryID,
                            "ParentID" => $this->ParentID
                        )
                    );
                if($existing->count() > 0) {
                    $validation->error(
                        'There is already an entry for this country and page'
                    );
                }
            }
        }
        return $validation;
    }

    /**
     *     'PageField' => 'Title'
     *     'TranslationField' => 'Title'
     *
     * @return ArrayList
     */
    public function FieldsToReplace()
    {
        $al = ArrayList::create();
        $al->push(
            ArrayData::create(
                array(
                    'PageField' => 'Title',
                    'TranslationField' => 'Title'
                )
            )
        );
        $al->push(
            ArrayData::create(
                array(
                    'PageField' => 'Content',
                    'TranslationField' => 'Content'
                )
            )
        );
        $this->extend('updateFieldsToReplace', $al);
        return $al;

    }

}
