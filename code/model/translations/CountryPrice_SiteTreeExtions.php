<?php


class CountryPrice_SiteTreeExtions extends SiteTreeExtension
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

    public function loadTranslatedValues($countryID = 0, $variableOrMethod = '')
    {
        $translation = null;
        if( ! $countryID) {
            $countryObject = CountryPrice_EcommerceCountry::get_real_country();
            if ($countryObject) {
                $countryID = $countryObject->ID;
            }
        }
        if ($countryID) {
            $key = $this->owner->ID.'_'.$countryID;
            if( ! isset(self::$_translations[$key])) {
                $translation = $this->owner
                    ->CountryPriceTranslations()
                    ->filter(
                        array(
                            "EcommerceCountryID" => $countryID,
                            'ParentID' => $this->owner->ID,
                        )
                    )
                    ->exclude(
                        array('WithoutTranslation' => 1)
                    )
                    ->first();
                self::$_translations[$key] = $translation;
            } else {
                $translation = self::$_translations[$key];
            }
        }
        if ($translation) {
            $fieldsToReplace = $translation->FieldsToReplace();
            foreach ($fieldsToReplace as $replaceFields) {
                $pageField = $replaceFields->PageField;
                $translationField = $replaceFields->TranslationField;
                if( ! $variableOrMethod || $variableOrMethod === $pageField) {
                    if ($translation->hasMethod($translationField)) {
                        $pageFieldTranslated = $pageField.'Translated';
                        $this->owner->$pageField = $translation->$translationField();
                        $this->owner->$pageFieldTranslated = $translation->$translationField();
                    } else {
                        $this->owner->$pageField = $translation->$translationField;
                    }
                }
                if($variableOrMethod) {
                    return $this->owner->$pageField;
                }
            }
        } else {
            if($variableOrMethod) {
                if ($translation->hasMethod($variableOrMethod)) {
                    return $this->owner->$variableOrMethod();
                } else {
                    return $this->owner->$variableOrMethod;
                }
            }
        }
    }


}
