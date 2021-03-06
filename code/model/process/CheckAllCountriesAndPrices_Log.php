<?php
class CountryPrice_DistributorManagementTool_Log extends DataObject
{
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

    public function canDelete($member = null)
    {
        return false;
    }

    public function canEdit($member = null)
    {
        return false;
    }
}
