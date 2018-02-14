<?php

interface CountryURLProviderInterface
{

    /**
     * returns the selected country code if there is one ...
     * as an uppercase code, e.g. NZ
     * @param string|null $url
     *
     * @return bool
     */
    public function hasCountrySegment($url = '');

    /**
     * returns the selected country code if there is one ...
     * as an uppercase code, e.g. NZ
     * @param string|null $url
     *
     * @return string|null
     */
    public function CurrentCountrySegment($url = '');

    /**
     * replaces a country code in a URL with another one
     *
     * @param  string $newCountryCode e.g. NZ / nz
     * @param  string $url
     *
     * @return string|null only returns a string if it is different from the original!
     */
    public function replaceCountryCodeInUrl($newCountryCode, $url = '');

    /**
     *
     * @param  string|null $url can be a relative one or nothing at all ...
     *
     * @return string      full URL currently being called.
     */
    public function getCurrentURL($url = '');
}
