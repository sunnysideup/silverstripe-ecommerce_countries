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
        $param = Config::inst()->get('CountryPrice_Translation', 'locale_get_parameter');
        $countryObject = CountryPrice_EcommerceCountry::get_real_country();
        if (isset($_GET[$param])) {
            $countryCode = preg_replace("/[^A-Z]+/", "", strtoupper(Convert::raw2sql($_GET[$param])));
            if ($countryObject->Code != $countryCode) {
                return $this->owner->redirect(
                    CountryPrices_ChangeCountryController::new_country_link($countryCode)
                );
            }
        }

        //check that there is a country!
        if ($countryObject) {
            $countryID = $countryObject->ID;
            //check that there is a translation
            if ($this->owner->dataRecord->hasCountryLocalInURL($countryID)) {
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
        $protocol = Director::is_https() ? 'https://' : 'http://';
        $oldURL = $protocol.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];

        $hasCountrySegment = CountryPrice_Translation::get_country_url_provider()->hasCountrySegment($oldURL);
        if($hasCountrySegment){
            $newURL = CountryPrice_Translation::get_country_url_provider()->replaceCountryCodeInUrl($countryCode, $oldURL);
        }
        else {
            $newURL = CountryPrice_Translation::get_country_url_provider()->addCountryCodeToUrl($countryCode, $oldURL);
        }

        if ($oldURL !== $newURL && self::$_redirection_count < 3) {
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
        return 'AlternativeHrefLangLinksCachingKey'.'-'.$this->owner->dataRecord->ID.'-'.strtotime($this->owner->dataRecord->LastEdited);
    }

    /**
     *
     * @param string $link - passed by reference
     */
    public function UpdateCanonicalLink(&$link)
    {
        $obj = $this->owner->dataRecord->CanonicalObject();
        if($obj) {
            $link = $obj->Link();
        } else {
            $link = $this->owner->dataRecord->AbsoluteLink();
        }
        return $link;
    }

}
