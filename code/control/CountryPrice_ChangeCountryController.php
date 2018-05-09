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

    /**
     * only call this function if it is a NEW country we are dealing with!
     *
     * @param string $newCountryCode
     *
     */
    public static function set_new_country($newCountryCode)
    {
        $newCountryCode = strtoupper($newCountryCode);

        Session::set('MyCloudFlareCountry', $newCountryCode);
        $o = Shoppingcart::current_order();
        if ($o && $o->exists() && ($o->getCountry() == $newCountryCode)) {
            //..
        } else {
            ShoppingCart::singleton()->clear();
        }

        CountryPrice_OrderDOD::localise_order($newCountryCode, $force = true, $runAgain = true);
    }

    /**
     * link to change to a new country.
     *
     * @param string $newCountryCode
     *
     */
    public static function new_country_link($newCountryCode)
    {
        $newCountryCode = strtoupper($newCountryCode);
        $redirectsArray = Config::inst()->get('CountryPrices_ChangeCountryController', 'off_site_url_redirects');
        if (isset($redirectsArray[$newCountryCode])) {
            return $redirectsArray[$newCountryCode];
        }

        return Injector::inst()->get('CountryPrices_ChangeCountryController')->Link('changeto/'.strtolower($newCountryCode).'/');
    }

    /**
     *
     * @param  SS_HTTPRequest $request
     *
     * @return SS_HTTPResponse
     */
    public function changeto($request)
    {
        //check for offsite redirects???
        $newCountryCode = strtoupper($request->param('ID'));
        self::set_new_country($newCountryCode);

        //redirect now
        if (isset($_GET['force']) && $_GET['force']) {
            return $this->redirect($this->Link($newCountryCode) . '?force-back-home');
        }
        if (isset($_GET['force-back-home']) && $_GET['force-back-home']) {
            return $this->redirect(Director::baseURL('/'));
        }

        $newLink = $this->findNewURL($newCountryCode);
        if ($newLink) {
            return $this->redirect($newLink);
        }

        return [];
    }

    public function Link($action = null)
    {
        $link = Controller::join_links(
            Config::inst()->get('CountryPrices_ChangeCountryController', 'url_segment'),
            $action
        );

        return $link . '/';
    }

    /**
     * Remove a query string parameter from an URL.
     *
     * @param string $newCountryCode
     *
     * @return string
     */
    public function findNewURL($newCountryCode)
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
            $oldURL = Director::absoluteURL($url, true);
            $parsedUrl = parse_url($oldURL);
            $query = array();

            if (isset($parsedUrl['query'])) {
                $varname = $this->Config()->get('locale_get_parameter');
                parse_str($parsedUrl['query'], $query);
                if (isset($query[$varname])) {
                    if ($query[$varname] !== $newCountryCode) {
                        unset($query[$varname]);
                    }
                }
            }

            $path = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';

            $query = empty($query) ? '' :  '?'. http_build_query($query);
            $newURL =
                $parsedUrl['scheme'] .
                '://' .
                Controller::join_links(
                    $parsedUrl['host'],
                    $parsedUrl['path']
                ).
                $query;
            $newURLwithNewCountryCode = CountryPrice_Translation::get_country_url_provider()
                ->replaceCountryCodeInUrl(
                    $newCountryCode,
                    $newURL
                );
            if ($newURLwithNewCountryCode) {
                return $newURLwithNewCountryCode;
            } else {
                return $newURL;
            }
        }
        return '/';
    }

    public function confirmredirection($request)
    {
    }
}
