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
			new Tab(
				'Include',
				new LiteralField("ExplanationInclude", "<p>Products are not available in the countries listed below.  You can include sales of <i>".$this->owner->Title."</i> to new countries by ticking the box(es) next to any country.</p>"),
				new CheckboxSetField('IncludedCountries', '', $excludedCountries)
			),
			new Tab(
				'Exclude',
				new LiteralField("ExplanationExclude", "<p>Products are available in all countries listed below.  You can exclude sales of <i>".$this->owner->Title."</i> from these countries by ticking the box next to any of them.</p>"),
				new CheckboxSetField('ExcludedCountries', '', $includedCountries)
			)
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
