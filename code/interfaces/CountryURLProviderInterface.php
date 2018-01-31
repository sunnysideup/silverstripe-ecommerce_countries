<?php

interface CountryURLProviderInterface
{
    /**
     */
    public function hasCountrySegment($url = '');

    /**
     */
    public function replaceCountryCodeInUrl($countryCode, $url = '');

    /**
     */
    public function addCountryCodeToUrl($countryCode, $url = '');
}
