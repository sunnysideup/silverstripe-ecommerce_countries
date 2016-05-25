<?php

/**
 * Adds individual country messages
 * for ordersteps
 *
 */

class EcommerceOrderStepDOD extends DataExtension {

    private static $has_many = array(
        'EcommerceOrderStepCountryDatas' => 'EcommerceOrderStepCountryData'
    );

    function updateCMSFields(FieldList $fields) {

    }
}
