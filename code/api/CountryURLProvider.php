<?php

/**
 * Usage:
 *     $myAnswer =
 *         CountryPrice_Translation::get_country_url_provider()
 *             ->getSomething();
 *
 */

class CountryURLProvider extends Object implements CountryURLProviderInterface
{
    /**
     * @var string
     */
    private static $locale_get_parameter = 'ecomlocale';

    /**
     * returns the selected country code if there is one ...
     * as an uppercase code, e.g. NZ
     * @param string|null $url
     *
     * @return bool
     */
    public function hasCountrySegment($url = '')
    {
        return $this->CurrentCountrySegment($url) ? true : false;
    }

    /**
     * returns the selected country code if there is one ...
     * as an uppercase code, e.g. NZ
     * @param string|null $url
     *
     * @return string|null
     */
    public function CurrentCountrySegment($url = '')
    {
        $param = Config::inst()->get('CountryURLProvider', 'locale_get_parameter');
        if (isset($_GET[$param])) {
            $potentialCountry = $_GET[$param];
        } else {
            $url = $this->getCurrentURL($url);
            $parts = parse_url($url);
            if (isset($parts['path'])) {
                $path = trim($parts['path'], '/');
                $array = explode('/', $path);
                $potentialCountry = isset($array[0]) ? trim($array[0]) : '';
            }
        }
        if (strlen($potentialCountry) === 2) {
            $potentialCountry = strtoupper($potentialCountry);
            $check = EcommerceCountry::get()->filter(['Code' => $potentialCountry])->count();
            if ($check) {
                return $potentialCountry;
            }
        }
    }

    /**
     * replaces a country code in a URL with another one
     *
     * @param  string $newCountryCode e.g. NZ / nz
     * @param  string $url
     *
     * @return string|null only returns a string if it is different from the original!
     */
    public function replaceCountryCodeInUrl($newCountryCode, $url = '')
    {
        $url = $this->getCurrentURL($url);

        $newCountryCode = strtolower($newCountryCode);
        $oldURL = $url;
        $parsedUrl = parse_url($url);
        if (isset($parsedUrl['path']) && isset($parsedUrl['host'])) {
            $path = $parsedUrl['path'];
            $path = trim($path, '/');
            $pathParts = explode('/', $path);
            $currentCountryCode = $this->CurrentCountrySegment($url);
            if ($currentCountryCode) {
                $pathParts[0] = $newCountryCode;
            } else {
                array_unshift($pathParts, $newCountryCode);
            }
            $parsedUrl['path'] = implode('/', $pathParts);
            $newURL =
                $parsedUrl['scheme'] .
                '://' .
                Controller::join_links(
                    $parsedUrl['host'],
                    $parsedUrl['path']
                );
            if (isset($parsedUrl['query'])) {
                $newURL = $newURL . '?' . $parsedUrl['query'];
            }
        }
        if ($oldURL !== $newURL) {
            return $newURL;
        }

        return '';
    }

    /**
     *
     * @param  string|null $url can be a relative one or nothing at all ...
     *
     * @return string      full URL currently being called.
     */
    public function getCurrentURL($url = '')
    {
        if ($url) {
            $url = Director::absoluteURL($url);
        } else {
            $protocol = Director::is_https() ? 'https://' : 'http://';

            $url = $protocol.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
        }
        if (Director::is_site_url($url)) {
            return $url;
        } else {
            return Director::absoluteURL('/');
        }
    }
}
