<?php

/**
 * Adds fields to individual countries.
 *
 */

class EcommerceOrderStepDOD extends DataExtension {

	private static $has_many = array(
		'EcommerceOrderStepCountryDatas' => 'EcommerceOrderStepCountryData'
	);

	function updateCMSFields(FieldList $fields) {

	}
}



