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

    private static $_translation = array();

    public function TranslatedValues($variableOrMethod = '')
    {
        $translation = null;
        $key = $this->owner->ID;

        if (isset(self::$_translations[$key])) {
            $translation = self::$_translations[$key];
        } else {
            $countryID = 0;
            $countryObject = CountryPrice_EcommerceCountry::get_real_country();
            if ($countryObject) {
                $countryID = $countryObject->ID;
            }
            if ($countryID) {
                $translation = $this->owner->dataRecord
                    ->CountryPriceTranslations()
                    ->filter(
                        array(
                            "EcommerceCountryID" => $countryID,
                            'ParentID' => $this->owner->dataRecord->ID
                        )
                    )
                    ->first();
                self::$_translations[$key] = $translation;
            }
        }
        if ($translation) {
            foreach ($translation->FieldsToReplace() as $replaceFields) {
                $pageField = $replaceFields->PageField;
                $translationField = $replaceFields->TranslationField;
                if (! $variableOrMethod || ($variableOrMethod == $pageField)) {
                    if ($translation->hasMethod($translationField)) {
                        $this->owner->$pageField = $translation->$translationField();
                    } else {
                        $this->owner->$pageField = $translation->$translationField;
                    }
                    if ($variableOrMethod && ($variableOrMethod == $pageField)) {
                        return $this->owner->$pageField;
                    }
                }
            }
        }
    }
}
