(function($) {
	$.entwine('ecommerceUpdatePricing', function($) {
		$('#Root_Pricing').entwine({
			onmatch : function() {
				UpdatePrices.init();
			}
		});

	});

}(jQuery));

UpdatePrices = {

	parentElement: null,

	init: function() {
		this.parentElement = jQuery('#Root_Pricing')
		var countryCurrencies = jQuery.parseJSON(jQuery(UpdatePrices.parentElement).find('[name=CountryCurrencies]').val());
		var fromField = jQuery(UpdatePrices.parentElement).find('#From select');
		var toField = jQuery(UpdatePrices.parentElement).find('#To');

		jQuery(fromField).change(
			function() {
				var code = jQuery(this).val();
				var countries = new Array();
				if(code in countryCurrencies) {
					var currency = countryCurrencies[code];
					jQuery.each(countryCurrencies, function(countryCode, countryCurrency) {
						if(countryCurrency == currency && countryCode != code) {
							countries.push(countryCode);
						}
					});
				}
				jQuery(toField).find('li').hide();
				jQuery(toField).find(':checkbox').attr('checked', false);
				for(i = 0; i < countries.length; i++) {
					jQuery(toField).find('li.val' + countries[i]).show();
				}
			}
		);
		jQuery(fromField).change();

		jQuery(this.parentElement).find('#UpdatePriceLink a').click(
			function(event) {
				var fromCountry = jQuery(fromField).val();
				var toCountries = jQuery(toField).find(':checkbox:checked');
				if(fromCountry && jQuery(toCountries).length > 0) {
					var confirmation = confirm('Are you sure that you want to copy those prices over ?');
					if(confirmation) {
						toCountries = jQuery.map(toCountries, function(element) {
							return jQuery(element).attr('value');
	 					});
						toCountries.join(',');
						var link = jQuery(this).attr('href');
						link += '&' + jQuery(fromField).attr('name') + '=' + jQuery(fromField).val();
						link += '&' + jQuery(toField).attr('id') + '=' + toCountries;
						alert(link);
						jQuery(this).attr('href', link);
						return true;
					}
				}
				event.preventDefault();
				return false;
			}
		);
	}
}

