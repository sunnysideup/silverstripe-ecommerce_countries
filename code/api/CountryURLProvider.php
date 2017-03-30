<?php

/**
 * Usage:
 *     $myAnswer =
 *         CountryPrice_Translation::get_country_url_provider()
 *             ->doSomething(....);
 *
 */

class CountryURLProvider extends Object implements CountryURLProviderInterface
{
    private static $country_segments  = array('nz', 'au', 'gb', 'eu', 'us', 'row');

    public function hasCountrySegment($url = ''){
        $url = $this->getDefaultURL($url);
        $parsedUrl = parse_url($url);
        $pathSegments = explode("/", $parsedUrl['path']);
        $firstSegment = '';
        $countries =  Config::inst()->get('CountryURLProvider', 'country_segments');
        foreach ($pathSegments as $position => $segment){
            if($segment){
                $firstSegment = $segment;
                break;
            }
        }
        if(in_array($firstSegment, $countries)){
            return true;
        }
        return false;
    }

    public function replaceCountryCodeInUrl($countryCode, $url = ''){
        $url = $this->getDefaultURL($url);
        $parsedUrl = parse_url($url);
        $pathParts = explode('/', $parsedUrl['path']);
        $countries =  Config::inst()->get('CountryURLProvider', 'country_segments');
        foreach($pathParts as $pathPartsKey => $pathPart) {
            //check for first match
            if(in_array($pathPart, $countries)) {
                $pathParts[$pathPartsKey] = strtolower($countryCode);
                break;
            }
        }
        $parsedUrl['path'] = implode('/', $pathParts);
        $url = $parsedUrl['scheme']. '://'. $parsedUrl['host']. $parsedUrl['path'];
        if(isset($parsedUrl['query'])){
            $url = $url . $parsedUrl['query'];
        }
        return $url;
    }

    public function addCountryCodeToUrl($countryCode, $url = ''){
        $url = $this->getDefaultURL($url);
        $parsedUrl = parse_url($url);
        $url = $parsedUrl['scheme']. '://'. $parsedUrl['host']. '/'. strtolower($countryCode) . $parsedUrl['path'];
        if(isset($parsedUrl['query'])){
            $url = $url . $parsedUrl['query'];
        }
        return $url;
    }

    private function getDefaultURL($url = '')
    {
        if($url) {
            return Director::absoluteURL($url);
        }
        return (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    }

}
