<?php


class CountryPrices_ChangeCountryController extends ContentController
{

    private static $url_segment = 'shoppingcart-countries';

    private static $allowed_actions = array(
        "changeto" => true
    );

    public static function new_country_link($countryCode)
    {
        return Injector::inst()->get('CountryPrices_ChangeCountryController')->Link('changeto/'.$countryCode.'/');
    }

    function changeto($request)
    {
        $newCountryCode = substr(strtoupper($request->param('ID')), 0, 2);
        Session::set('MyCloudFlareCountry', $newCountryCode);
        CountryPrice_OrderDOD::localise_order($newCountryCode);
        $this->redirectBack();
    }

    function Link($action = null)
    {
        return Controller::join_links(Config::inst()->get('CountryPrices_ChangeCountryController', 'url_segment'), $action);
    }



}
