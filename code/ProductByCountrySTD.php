<?php

/**
 * Determine products for sale on a country by country basis.
 *
 *
 *
 *
 */

class ProductByCountrySTD extends SiteTreeDecorator {

	function extraStatics() {
		return array(
			'db' => array(
				'AllCountries' => 'Boolean'
			),
			'many_many' => array(
				'IncludedCountries' => 'EcommerceCountry',
				'ExcludedCountries' => 'EcommerceCountry'
			)
		);
	}

	function updateCMSFields(FieldSet $fields) {
		$excludedCountries = DataObject::get('EcommerceCountry', "\"DoNotAllowSales\" = 1");
		if($excludedCountries) {
			$excludedCountries = $excludedCountries->map('ID', 'Name');
		}
		$includedCountries = DataObject::get('EcommerceCountry', "\"DoNotAllowSales\" = 0");
		if($includedCountries)  {
			$includedCountries = $includedCountries->map('ID', 'Name');
		}
		$tabs = new TabSet('Countries',
			new Tab(
				'Include',
				new CheckboxField("AllCountries", "All Countries"),
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


	/**
	 * This is called from /ecommerce/code/Product
	 * returning NULL is like returning TRUE, i.e. ignore this.
	 * @param Member $member
	 * @return FALSE | NULL
	 */
	function canPurchaseByCountry($member = null) {
		if($this->owner->AllCountries) {
			return null;
		}
		$countryCode = EcommerceCountry::get_country();
		if($countryCode) {
			$included = $this->owner->getManyManyComponents('IncludedCountries', "\"Code\" = '$countryCode'")->Count();
			if($included) {
				return null;
			}
			$excluded = $this->owner->getManyManyComponents('ExcludedCountries', "\"Code\" = '$countryCode'")->Count();
			if($excluded) {
				return false;
			}
		}
		return null;
	}
}
