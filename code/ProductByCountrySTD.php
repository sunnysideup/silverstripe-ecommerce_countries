<?php

class ProductByCountrySTD extends SiteTreeDecorator {
	
	function extraStatics() {
		return array(
			'many_many' => array(
				'IncludedCountries' => 'EcommerceCountry',
				'ExcludedCountries' => 'EcommerceCountry'
			)
		);
	}

	function updateCMSFields(FieldSet &$fields) {
		$excludedCountries = DataObject::get('EcommerceCountry', '`DoNotAllowSales` = 1');
		$excludedCountries = $excludedCountries->map('ID', 'Name');
		$includedCountries = DataObject::get('EcommerceCountry', '`DoNotAllowSales` = 0');
		$includedCountries = $includedCountries->map('ID', 'Name');
		$tabs = new TabSet('Countries',
			new Tab('Include', new CheckboxSetField('IncludedCountries', '', $excludedCountries)),
			new Tab('Exclude', new CheckboxSetField('ExcludedCountries', '', $includedCountries))
		);
		$fields->addFieldToTab('Root.Content', $tabs);
	}

	function canPurchaseByCountry() {
		$countryCode = @Geoip::visitor_country();
		if($countryCode) {
			$included = $this->owner->getManyManyComponents('IncludedCountries', "`Code` = '$countryCode'")->Count();
			if($included) {
				return true;
			}
			$excluded = $this->owner->getManyManyComponents('ExcludedCountries', "`Code` = '$countryCode'")->Count();
			if($excluded) {
				return false;
			}
		}
		return EcommerceCountry::allow_sales();
	}
}