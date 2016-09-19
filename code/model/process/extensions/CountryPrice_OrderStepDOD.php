<?php

/**
 * Adds individual country messages
 * for ordersteps
 *
 */

class CountryPrice_OrderStepDOD extends DataExtension
{
    private static $has_many = array(
        'EcommerceOrderStepCountryDatas' => 'EcommerceOrderStepCountryData'
    );
}
