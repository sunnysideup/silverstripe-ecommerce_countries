<?php

class CountryPrice_CopyPrices_Controller extends ContentController {

    public static function get_link(DataObject $object) {
        return "copy-prices?class={$object->ClassName}&id={$object->ID}";
    }

    /**
     * requires the following _GET variables:
     *  - class
     *  - id
     *  - from (country code)
     *  - to (country code or codes!), using commas ...
     *
     */
    function index(){
        if(Permission::check('ADMIN')) {
            $object = $_REQUEST['class']::get()->byID(intval($_REQUEST['id']));
            if($object && $object->hasMethod('updatePrices')) {
                $object->updatePrices(Convert::raw2sql($_REQUEST['From']), explode(',', Convert::raw2sql($_REQUEST['To'])));
                echo '<p>All prices updated from <strong>'.$_REQUEST['From'].'</strong> to <strong>'.$_REQUEST['To'].'</strong></p>';
                return;
            }
        }
        else {
            echo "please log in as admin first";
        }
    }

}
