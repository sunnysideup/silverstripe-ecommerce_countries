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
                $this->owner->getCountryPriceTranslations(),
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
        return $this->owner->getCountryPriceTranslations()
            ->innerJoin('EcommerceCountry', '"EcommerceCountry"."ID" = "CountryPrice_Translation"."EcommerceCountryID"');
    }

    /**
     *
     * @return CountryPrice_Translation | null
     */
    public function CanonicalObject()
    {
        return $this->owner->getCountryPriceTranslations()
            ->innerJoin('EcommerceCountry', '"EcommerceCountry"."ID" = "CountryPrice_Translation"."EcommerceCountryID"')
            ->filter(array('EcommerceCountry.IsBackupCountry' => 1))
            ->first();
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
     * @var int $countryID
     *
     * @return CountryPrice_Translation | null
     */
    public function getEcommerceTranslation($countryID)
    {
        return $this->owner
            ->getCountryPriceTranslations()
            ->filter(
                array(
                    "EcommerceCountryID" => $countryID,
                    'ParentID' => $this->owner->ID,
                )
            )
            ->first();
    }

    /**
     * @var int $countryID
     *
     * @return bool
     */
    public function hasCountryLocalInURL($countryID)
    {
        return $this->getEcommerceTranslation($countryID) ? true : false;
    }

    /**
     * we have added this because we got some weird mixups with magical methods
     * @return DataList (of CountryPrice_Translation objects)
     */
    function CountryPriceTranslations()
    {
        return $this->getCountryPriceTranslations();
    }

    /**
     * we have added this because we got some weird mixups with magical methods
     * @return DataList (of CountryPrice_Translation objects)
     */
    function getCountryPriceTranslations()
    {
        return CountryPrice_Translation::get()->filter(
            array('CountryPrice_Translation.ParentID' => $this->owner->ID)
        );
    }

}
