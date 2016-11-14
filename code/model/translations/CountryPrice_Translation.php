<?php



class CountryPrice_Translation extends DataObject
{
    private static $db = array(
        'Title' => 'Varchar(200)',
        'Content' => 'HTMLText',
        'WithoutTranslation' => 'Boolean'
    );

    private static $field_labels = array(
        'Title' => 'Page Title',
        'Content' => 'Page Content',
        'EcommerceCountryID' => 'Country',
        'WithoutTranslation' => 'Without Translation'
    );

    private static $has_one = array(
        'EcommerceCountry' => 'EcommerceCountry',
        'Parent' => 'SiteTree'
    );

    private static $summary_fields = array(
        'EcommerceCountry.Name' => 'Country',
        'Title' => 'Title',
        'WithoutTranslation.Nice' => 'Price Difference Only'
    );

    private static $casting = array(
        'Link' => 'Varchar'
    );

    /**
     * @var string
     */
    private static $locale_get_parameter = 'ecomlocale';

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
                $fields->dataFieldByName('EcommerceCountryID')->Title(),
                array('' => '-- make sure to select a country --')+$countries
            ),
            'Title'
        );
        $withoutTranslationField = $fields->dataFieldByName('WithoutTranslation');
        $fields->addFieldToTab(
            'Root.Main',
            CheckboxField::create(
                'WithoutTranslation',
                $withoutTranslationField->Title()
            )->setRightTitle('The page is <em>translated</em> for search engines because it has prices for this country. '),
            'Title'
        );
        $fields->removeFieldFromTab("Root.Main", 'ParentID');
        return $fields;
    }

    public function canCreate($member = null)
    {
        if (CountryPrice_EcommerceCountry::get_real_countries_list()->count()) {
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
        if ($validation->valid()) {
            if ($this->exists()) {
                $existing = CountryPrice_Translation::get()
                    ->exclude(array("ID" => $this->ID))
                    ->filter(
                        array(
                            "EcommerceCountryID" => $this->EcommerceCountryID,
                            "ParentID" => $this->ParentID
                        )
                    );
                if ($existing->count() > 0) {
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

    /**
     * @return string
     */
    function Link()
    {
        return $this->getLink();
    }

    /**
     * @return string
     */
    function getLink()
    {
        $link = $this->Parent()->Link();
        if($this->EcommerceCountryID) {
            $link .= '?'.$this->Config()->get('locale_get_parameter').'='.$this->EcommerceCountry()->Code;
        }
        return Director::absoluteURL($link);
    }
}
