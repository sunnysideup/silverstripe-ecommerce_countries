<?php


class CountryPrice_SiteTreeExtensions extends SiteTreeExtension
{
    private static $has_many = array(
        'CountryPriceTranslations' => 'CountryPrice_Translation'
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

    private static $_translations = array();

    /**
     *
     * @return DataList
     */
    public function AvailableTranslationLinks()
    {
        return $this->owner->CountryPriceTranslations()
            ->innerJoin('EcommerceCountry', '"EcommerceCountry"."ID" = "CountryPrice_Translation"."EcommerceCountryID"');
    }

    /**
     *
     * @return CountryPrice_Translation | null
     */
    public function CanonicalObject()
    {
        $countryObject = CountryPrice_EcommerceCountry::get_real_country();
        if ($countryObject && $countryObject->Code) {
            $object = $this->owner->CountryPriceTranslations()
                ->innerJoin('EcommerceCountry', '"EcommerceCountry"."ID" = "CountryPrice_Translation"."EcommerceCountryID"')
                ->filter(array('EcommerceCountry.Code' => $countryObject->Code))
                ->first();
            if ($object && $object->exists()) {
                return $object;
            }
        }
        return false;
    }

    public function loadTranslatedValues($countryID = 0, $variableOrMethod = '')
    {
        $translation = null;
        if (! $countryID) {
            $countryObject = CountryPrice_EcommerceCountry::get_real_country();
            if ($countryObject) {
                $countryID = $countryObject->ID;
            }
        }
        if ($countryID) {
            $key = $this->owner->ID.'_'.$countryID;
            if (isset(self::$_translations[$key])) {
                $translation = self::$_translations[$key];
            } else {
                $translation = $this->owner->getRealEcommerceTranslation($countryID);
                self::$_translations[$key] = $translation;
            }
        }
        if ($translation) {
            $fieldsToReplace = $translation->FieldsToReplace();
            foreach ($fieldsToReplace as $replaceFields) {
                $pageField = $replaceFields->PageField;
                $pageFieldTranslated = $pageField . 'Translated';
                $translationField = $replaceFields->TranslationField;
                if (! $variableOrMethod || $variableOrMethod === $pageField) {
                    if ($translation->hasMethod($translationField)) {
                        $this->owner->$pageField = $translation->$translationField();
                        $this->owner->$pageFieldTranslated = $translation->$translationField();
                    } else {
                        $this->owner->$pageField = $translation->$translationField;
                        $this->owner->$pageFieldTranslated = $translation->$translationField;
                    }
                }
                if ($variableOrMethod) {
                    return $this->owner->$pageField;
                }
            }
        } else {
            if ($variableOrMethod) {
                if ($translation->hasMethod($variableOrMethod)) {
                    return $this->owner->$variableOrMethod();
                } else {
                    return $this->owner->$variableOrMethod;
                }
            }
        }
    }


    /**
     * @var int $countryID
     *
     * @return CountryPrice_Translation | null
     */
    public function getRealEcommerceTranslation($countryID)
    {
        return CountryPrice_Translation::get()
            ->filter(
                array(
                    "EcommerceCountryID" => $countryID,
                    'ParentID' => $this->owner->ID
                )
            )
            ->exclude(
                array('WithoutTranslation' => 1)
            )
            ->first();
    }

    /**
     * cache for all translations ...
     * @var [type]
     */
    private $_translations_all_cache = [];

    /**
     * @var int $countryID
     *
     * @return CountryPrice_Translation | null
     */
    public function getEcommerceTranslation($countryID)
    {
        if (!isset($this->_translations_all_cache[$countryID])) {
            $this->_translations_all_cache[$countryID] = $this->owner
                ->CountryPriceTranslations()
                ->filter(
                    array(
                        "EcommerceCountryID" => $countryID,
                        'ParentID' => $this->owner->ID,
                    )
                )
                ->first();
        }
        return $this->_translations_all_cache[$countryID];
    }

    /**
     * @var int $countryID
     *
     * @return bool
     */
    public function thisPageHasTranslation($countryID)
    {
        return $this->getEcommerceTranslation($countryID) ? true : false;
    }
}
