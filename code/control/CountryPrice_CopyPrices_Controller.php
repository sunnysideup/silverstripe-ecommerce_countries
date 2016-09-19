<?php

class CountryPrice_CopyPrices_Controller extends ContentController
{
    private static $url_segment = 'shoppingcart-copy-prices';

    private static $allowed_actions = array(
        "index" => 'ADMIN'
    );


    public static function get_link(DataObject $object)
    {
        return Injector::inst()->get('CountryPrice_CopyPrices_Controller')->Link()."?class={$object->ClassName}&id={$object->ID}";
    }

    /**
     * requires the following _GET variables:
     *  - class
     *  - id
     *  - from (country code)
     *  - to (country code or codes!), using commas ...
     *
     */
    public function index()
    {
        if (Permission::check('ADMIN')) {
            $object = $_REQUEST['class']::get()->byID(intval($_REQUEST['id']));
            if ($object && $object->hasMethod('updatePrices')) {
                $object->updatePrices(Convert::raw2sql($_REQUEST['From']), explode(',', Convert::raw2sql($_REQUEST['To'])));
                echo '<p>All prices updated from <strong>'.$_REQUEST['From'].'</strong> to <strong>'.$_REQUEST['To'].'</strong></p>';
                return;
            }
        } else {
            echo "please log in as admin first";
        }
    }


    public function Link($action = null)
    {
        return Controller::join_links(Config::inst()->get('CountryPrice_CopyPrices_Controller', 'url_segment'), $action);
    }
}
