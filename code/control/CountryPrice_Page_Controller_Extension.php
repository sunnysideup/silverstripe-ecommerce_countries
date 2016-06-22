<?php


class CountryPrice_Page_Controller_Extension extends Extension
{

    private static $local_get_parameter = 'locale';
    /**
     * replaces `Title` and `Content` with translated content
     * where available
     * @return [type] [description]
     */
    function onAfterInit()
    {
        $countryID = 0;
        $countryObject = CountryPrice_EcommerceCountry::get_real_country();
        if($countryObject) {
            $countryID = $countryObject->ID;
        }
        $translation = $this->owner->dataRecord
            ->CountryPriceTranslations()
            ->filter(
                array(
                    "EcommerceCountryID" => $countryID,
                    'ParentID' => $this->owner->dataRecord->ID
                )
            )
            ->first();
        if($translation) {
            die("aas");
            $this->owner->Content = $translation->Content;
            $this->owner->Title = $translation->Title;
            $newURL = $this->addCountryCodeToUrlIfRequired($countryCode);
            if($newURL) {
                return $this->redirect($newURL);
            }
        }
    }

    /**
     * returns the best fieldname for
     * @param string $fieldName [description]
     */
    function CountryDistributorBestContentValue($fieldName)
    {
        $countryCode = EcommerceCountry::get_real();

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
        $url = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

        $urlParts = parse_url($url);
        parse_str($urlParts['query'], $params);

        $param = Config::inst()->get('CountryPrice_Page_Controller_Extension', 'local_get_parameter');
        $params['locale'] = $countryCode;     // Overwrite if exists

        // Note that this will url_encode all values
        $urlParts['query'] = http_build_query($params);

        // If you have pecl_http
        if(function_exists('http_build_url')) {
            $newURL = http_build_url($urlParts);
        } else {
            $newURL =  $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'] . '?' . $url_parts['query'];
        }

        if($oldURL !== $newURL && self::$_redirection_count < 3) {
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
    function ChooseNewCountryList()
    {
        $countries = CountryPrice_EcommerceCountry::get_real_countries_list();
        $currentCode = CountryPrice_EcommerceCountry::get_real_country();
        $al = ArrayList::create();
        foreach($countries as $country) {
            $isCurrentOne = $currentCode == $country->Code ? true : false;
            $currency = null;
            if($isCurrentOne) {
                $currency = CountryPrice_EcommerceCurrency::get_currency_for_country($country->Code);
            }
            $al->push(
                ArrayData::create(
                    array(
                        'Link' => CountryPrices_ChangeCountryController::new_country_link($country->Code),
                        'Title' => $country->Name,
                        'LinkingMode' => ($isCurrentOne? 'current' : 'link'),
                        'Currency' => $currency
                    )
                )
            );
        }
        return $al;
    }
}
