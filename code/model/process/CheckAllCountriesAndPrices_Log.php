<?php
class CheckAllCountriesAndAllPrices_Log extends DataObject {

    private static $db = array(
        "UserEmail" => "Varchar",
        "ObjectClass"  => "Varchar",
        "ObjectID"  => "Int",
        "FieldName"  => "Varchar",
        "NewValue"  => "Varchar"
    );

    private static $has_one = array(
        "Distributor" => "Distributor"
    );

    function canDelete($member = null) {
        return false;
    }

    function canEdit($member = null) {
        return false;
    }

}
