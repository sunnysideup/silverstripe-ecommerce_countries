<?php

/**
 *
 * Common use:
 * ```php
 *    CountryPrices_ChangeCountryController::changeto('XX');
 *    CountryPrices_ChangeCountryController::new_country_link('XX');
 * ```
 *
 */

class CountryPrices_ChangeCountryController extends ContentController
{

    /**
     * make sure to match route...
     * @var string
     */
    private static $url_segment = 'shoppingcart-countries';

    /**
     * needs to be saved like this:
     * ZA => 'myshop.co.za'
     *
     * @var array
     */
    private static $off_site_url_redirects = array();

    private static $allowed_actions = array(
        "changeto" => true,
        "confirmredirection" => true
    );

    public static function new_country_link($countryCode)
    {
        $redirectsArray = Config::inst()->get('CountryPrices_ChangeCountryController', 'off_site_url_redirects');
        if (isset($redirectsArray[$countryCode])) {
            return $redirectsArray[$countryCode];
        }

        return Injector::inst()->get('CountryPrices_ChangeCountryController')->Link('changeto/'.$countryCode.'/');
    }

    public function changeto($request)
    {
        $redirectsArray = Config::inst()->get('CountryPrices_ChangeCountryController', 'off_site_url_redirects');
        $newCountryCode = substr(strtoupper($request->param('ID')), 0, 2);
        if (isset($redirectsArray[$newCountryCode])) {
            return $this->redirect($redirectsArray[$newCountryCode]);
        }
        Session::set('MyCloudFlareCountry', $newCountryCode);
        $o = Shoppingcart::current_order();
        if ($o && ($o->getCountry() == $newCountryCode)) {
            //..
        } else {
            ShoppingCart::singleton()->clear();
        }
        CountryPrice_OrderDOD::localise_order($newCountryCode, true);

        $this->redirect($this->findNewURL('ecomlocale', $newCountryCode));
    }

    public function Link($action = null)
    {
        return Controller::join_links(Config::inst()->get('CountryPrices_ChangeCountryController', 'url_segment'), $action);
    }

    /**
     * Remove a query string parameter from an URL.
     *
     * @param string $url
     * @param string $varname
     *
     * @return string
     */
    public function findNewURL($varname = 'ecomlocale', $newCountryCode)
    {

        //COPIED FROM DIRECTOR::redirectBack()
        // Don't cache the redirect back ever
        HTTP::set_cache_age(0);

        $url = null;

        // In edge-cases, this will be called outside of a handleRequest() context; in that case,
        // redirect to the homepage - don't break into the global state at this stage because we'll
        // be calling from a test context or something else where the global state is inappropraite
        if ($this->getRequest()) {
            if ($this->getRequest()->requestVar('BackURL')) {
                $url = $this->getRequest()->requestVar('BackURL');
            } elseif ($this->getRequest()->isAjax() && $this->getRequest()->getHeader('X-Backurl')) {
                $url = $this->getRequest()->getHeader('X-Backurl');
            } elseif ($this->getRequest()->getHeader('Referer')) {
                $url = $this->getRequest()->getHeader('Referer');
            }
        }

        if (!$url) {
            $url = Director::baseURL();
        }

        // absolute redirection URLs not located on this site may cause phishing
        if (Director::is_site_url($url)) {
            $url = Director::absoluteURL($url, true);
            $parsedUrl = parse_url($url);
            $query = array();

            if (isset($parsedUrl['query'])) {
                parse_str($parsedUrl['query'], $query);
                if ($query[$varname] !== $newCountryCode) {
                    unset($query[$varname]);
                }
            }

            $path = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';
            $query = !empty($query) ? '?'. http_build_query($query) : '';

            $url = $parsedUrl['scheme']. '://'. $parsedUrl['host']. $path. $query;

            return $url;
        }
        return '/';
    }

    public function confirmredirection($request)
    {
    }
}
