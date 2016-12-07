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

        if ($countryObject) {
            $countryID = $countryObject->ID;
            if($this->owner->dataRecord->getEcommerceTranslation($countryID)) {
                $newURL = $this->addCountryCodeToUrlIfRequired($countryObject->Code);
                if($newURL) {
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
        $oldURL = "http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];

        $urlParts = parse_url($oldURL);
        if(!isset($urlParts['query'])) {
            $urlParts['query'] = '';
        }
        parse_str($urlParts['query'], $params);

        $param = Config::inst()->get('CountryPrice_Translation', 'locale_get_parameter');
        $params[$param] = $countryCode;     // Overwrite if exists

        // Note that this will url_encode all values
        $urlParts['query'] = http_build_query($params);

        // If you have pecl_http
        if (function_exists('http_build_url')) {
            $newURL = http_build_url($urlParts);
        } else {
            $newURL =  $urlParts['scheme'] . '://' . $urlParts['host'] . $urlParts['path'] . '?' . $urlParts['query'];
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
    function AlternativeHrefLangLinksCachingKey()
    {
        return 'AlternativeHrefLangLinksCachingKey'.'-'.$this->owner->dataRecord->ID.'-'.strtotime($this->owner->dataRecord->LastEdited);
    }
}
