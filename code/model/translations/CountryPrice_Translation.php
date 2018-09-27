<?php



class CountryPrice_Translation extends DataObject
{
    private static $automatically_create_dummy_translations_for_products_and_productgroups = true;

    private static $dependencies = array(
        'CountryURLProvider' => '%$CountryURLProvider',
    );

    /**
     * automatically populated by the dependency manager.
     *
     * @var CountryURLProvider
     */
    public $CountryURLProvider = null;

    private static $db = array(
        'Title' => 'Varchar(200)',
        'UseOriginalTitle' => 'Boolean',
        'Content' => 'HTMLText',
        'UseOriginalContent' => 'Boolean',
        'WithoutTranslation' => 'Boolean'
    );

    private static $field_labels = array(
        'Title' => 'Page Title',
        'Content' => 'Page Content',
        'EcommerceCountryID' => 'Country',
        'WithoutTranslation' => 'Price Difference Only'
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
     * name of the object that provides the country URL
     *
     * @return string
     */
    public static function get_country_url_provider()
    {
        $obj = Injector::inst()->get('CountryPrice_Translation');

        return $obj->CountryURLProvider;
    }

    /**
     * CMS Fields
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $withoutTranslationField = $fields->dataFieldByName('WithoutTranslation');
        $withoutTranslationField = CheckboxField::create(
            'WithoutTranslation',
            $withoutTranslationField->Title()
        )
        ->setDescription('The page is <em>translated</em> for search engines because it has prices for this country. ');

        $countries = CountryPrice_EcommerceCountry::get_real_countries_list()->map()->toArray();
        $countryDropdownField = DropdownField::create(
            'EcommerceCountryID',
            $fields->dataFieldByName('EcommerceCountryID')->Title(),
            array('' => '-- make sure to select a country --')+$countries
        );

        // //$fields->removeFieldFromTab("Root.Main", 'ParentID');
        if ($this->WithoutTranslation) {
            return FieldList::create(
                array(
                    $countryDropdownField,
                    $withoutTranslationField
                )
            );
        } else {
            $fields->addFieldToTab(
                'Root.Main',
                $countryDropdownField,
                'Title'
            );
            $fields->addFieldToTab(
                'Root.Main',
                $withoutTranslationField,
                'Title'
            );
        }
        $dbFields = $this->inheritedDatabaseFields();
        foreach ($dbFields as $dbField => $fieldType) {
            $useField = 'UseOriginal'.$dbField;
            if (!empty($this->$useField)) {
                $fields->replaceField(
                    $dbField,
                    $fields->dataFieldByName($dbField)->performReadonlyTransformation()
                );
            }
            if ($fields->dataFieldByName($useField)) {
                $fields->dataFieldByName($useField)->setDescription(_t('CountryPrice_Translation.IGNORE', 'Use untranslated value for ') . $dbField);
            }
        }
        if ($this->exists() && $this->ParentID) {
            $fields->addFieldToTab(
                'Root.ParentPage',
                CMSEditLinkField::create(
                    $name = 'MyParent',
                    $title = 'My Parent Page',
                    $linkedObject = $this->Parent()
                )
            );
        }
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
            if (! $this->EcommerceCountryID) {
                $validation->error(
                    'You can not create a translation without seleting a country.'
                );
            }
            if (! $this->ParentID) {
                $validation->error(
                    'You can not create a translation without attaching it to a page.'
                );
            }

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
                    'There is already an entry for this page for this country.'
                );
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
        foreach ($al as $fieldToReplace) {
            $ignoreField = 'UseOriginal' . $fieldToReplace->PageField;
            if (!empty($this->owner->$ignoreField)) {
                $al->remove($fieldToReplace);
            }
        }
        return $al;
    }

    /**
     * @return string
     */
    public function Link()
    {
        return $this->getLink();
    }

    /**
     * @return string
     */
    public function getLink()
    {
        $standardLink = $this->Parent()->Link();
        $linkWithNewCountryCode = '';
        if ($this->EcommerceCountryID) {
            $linkWithNewCountryCode = CountryPrice_Translation::get_country_url_provider()
                ->replaceCountryCodeInUrl(
                    $this->EcommerceCountry()->Code,
                    $standardLink
                );
        }
        if ($linkWithNewCountryCode) {
            $link = $linkWithNewCountryCode;
        } else {
            $link = $standardLink;
        }

        return Director::absoluteURL($link);
    }

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();
        //get rid of rogue entries:
        DB::query('DELETE FROM CountryPrice_Translation WHERE EcommerceCountryID = 0 OR ParentID = 0');
        if (Config::inst()->get('CountryPrice_Translation', 'automatically_create_dummy_translations_for_products_and_productgroups')) {
            $prices = CountryPrice::get();
            $ecommerceCountries = array();
            foreach ($prices as $price) {
                if ($countryObject = $price->CountryObject()) {
                    if ($buyable = $price->Buyable()) {
                        if ($buyable instanceof Product) {
                            if ($buyable->ID && $countryObject->ID) {
                                $filter = array(
                                    'EcommerceCountryID' => $countryObject->ID,
                                    'ParentID' => $buyable->ID
                                );
                                $ecommerceCountries[$countryObject->ID] = $countryObject;
                                if (! CountryPrice_Translation::get()->filter($filter)->first()) {
                                    DB::alteration_message(
                                        'Creating fake translation for '.$buyable->Title.' for country '.$countryObject->Code,
                                        'created'
                                    );
                                    $obj = CountryPrice_Translation::create($filter);
                                    $obj->WithoutTranslation = true;
                                    $obj->write();
                                }
                            }
                        }
                    }
                }
            }
            if (count($ecommerceCountries)) {
                foreach (ProductGroup::get() as $productGroup) {
                    foreach ($ecommerceCountries as $countryID => $countryObject) {
                        $filter = array(
                            'EcommerceCountryID' => $countryObject->ID,
                            'ParentID' => $productGroup->ID
                        );
                        if (! CountryPrice_Translation::get()->filter($filter)->first()) {
                            DB::alteration_message(
                                'Creating fake translation for '.$productGroup->Title.' for country '.$countryObject->Code,
                                'created'
                            );
                            $obj = CountryPrice_Translation::create($filter);
                            $obj->WithoutTranslation = true;
                            $obj->write();
                        }
                    }
                }
            }
        }
    }
}
