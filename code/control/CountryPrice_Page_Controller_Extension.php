<?php


/**
 * www.mysite.com/mypage/?ecomlocale=AU
 * if there is a tranlsation page redirects to
 * URL with ?ecomlocale=AU
 *
 * if you go to a URL with ?ecomlocale=AU and the shop country does not match
 * the get param then you get redirected to that shop country.
 *
 *
 */

class CountryPrice_Page_Controller_Extension extends Extension
{

    /**
     * replaces `Title` and `Content` with translated content
     * where available.
     *
     * If the country code in the get parameter is not correct then
     * @return [type] [description]
     */
    public function onAfterInit()
    {
        $countryID = 0;
        //provided by stealth ...
        $countryObject = CountryPrice_EcommerceCountry::get_real_country();

        if ($countryObject) {

            //check if a redirect is required ...
            $this->checkForOffsiteRedirects($countryObject);

            $countryID = $countryObject->ID;
            //check that there is a translation
            if ($this->owner->dataRecord->thisPageHasTranslation($countryID)) {
                //if there is a translation but it is not showing in the URL then redirect
                $newURL = $this->addCountryCodeToUrlIfRequired($countryObject->Code);
                if ($newURL) {
                    $this->owner->redirect($newURL);
                }
            }
        }

        $this->owner->dataRecord->loadTranslatedValues($countryID, null);
    }

    /**
     * returns the best fieldname for
     * @param string $fieldName [description]
     */
    public function CountryDistributorBestContentValue($fieldName)
    {
        $countryObject = CountryPrice_EcommerceCountry::get_real_country();

        //check country
        if (!empty($countryObject->$fieldName)) {
            return $countryObject->$fieldName;
        }

        //check distributor
        $distributor = Distributor::get_one_for_country($countryObject->Code);
        if (!empty($distributor->$fieldName)) {
            return $distributor->$fieldName;
        }
        //check EcomConfig
        $distributor = Distributor::get_one_for_country($countryObject->Code);
        if (!empty($distributor->$fieldName)) {
            return $distributor->$fieldName;
        }
    }

    /**
     * caching variable
     *
     * @var integer
     */
    private static $_redirection_count = 0;

    /**
     * returns a string for the new url if a locale parameter can be added
     *
     * @return string | null
     */
    private function addCountryCodeToUrlIfRequired($countryCode = '')
    {
        if (isset($_POST) && count($_POST)) {
            return null;
        }
        //to do: add query here!

        $newURL = CountryPrice_Translation::get_country_url_provider()->replaceCountryCodeInUrl($countryCode);

        if ($newURL && self::$_redirection_count < 3) {
            self::$_redirection_count++;

            return $newURL;
        }
        return null;
    }


    /**
     *
     *
     * @return ArrayList
     */
    public function ChooseNewCountryList()
    {
        $countries = CountryPrice_EcommerceCountry::get_real_countries_list();
        $currentCode = '';
        if ($obj = CountryPrice_EcommerceCountry::get_real_country()) {
            $currentCode = $obj->Code;
        }
        $al = ArrayList::create();
        foreach ($countries as $country) {
            $isCurrentOne = $currentCode == $country->Code ? true : false;
            $currency = null;
            $currency = CountryPrice_EcommerceCurrency::get_currency_for_country($country->Code);
            $currencyCode = CountryPrice_EcommerceCurrency::get_currency_for_country($country->Code);
            $al->push(
                ArrayData::create(
                    array(
                        'Link' => CountryPrices_ChangeCountryController::new_country_link($country->Code),
                        'Title' => $country->Name,
                        'CountryCode' => $country->Code,
                        'LinkingMode' => ($isCurrentOne ? 'current' : 'link'),
                        'Currency' => $currency,
                        'CurrencyCode' => $currency
                    )
                )
            );
        }
        return $al;
    }

    /**
     *
     * @return DataList
     */
    public function AlternativeHrefLangLinksCachingKey()
    {
        $countryObject = CountryPrice_EcommerceCountry::get_real_country();
        if ($countryObject && $countryObject->Code) {
            return 'AlternativeHrefLangLinksCachingKey-'.$countryObject->Code.'-'.$this->owner->dataRecord->ID.'-'.strtotime($this->owner->dataRecord->LastEdited);
        }
        return 'AlternativeHrefLangLinksCachingKey'.$this->owner->dataRecord->ID.'-'.$this->owner->dataRecord->ID.'-'.strtotime($this->owner->dataRecord->LastEdited);
    }

    /**
     *
     * @param string $link - passed by reference
     */
    public function UpdateCanonicalLink(&$link)
    {
        $obj = $this->owner->dataRecord->CanonicalObject();
        if ($obj) {
            $link = $obj->Link();
        } else {
            $link = $this->owner->dataRecord->AbsoluteLink();
        }
        return $link;
    }

    /**
     * redirects visitors to another website if they are listed as such in
     * CountryPrices_ChangeCountryController.off_site_url_redirects
     *
     * @param  EcommerceCountry $countryObject current country of visitor
     *
     * @return null|SS_HTTPResponse
     */
    protected function checkForOffsiteRedirects($countryObject)
    {
        $redirectsArray = Config::inst()->get('CountryPrices_ChangeCountryController', 'off_site_url_redirects');
        $myCountryCode = strtoupper($countryObject->Code);
        if (isset($redirectsArray[$myCountryCode])) {
            return $this->redirect($redirectsArray[$myCountryCode]);
        }
    }
}
