
(function($) {
    $(document).ready(
        function() {
            if(typeof CountryPrice_SetCountriesForDelivery_Original !== 'undefined' && typeof CountryPrice_SetCountriesForDelivery_New !== "undefined") {
                var CountryPrice_SetCountriesForDelivery = {
                    init: function() {
                        jQuery("input[name='UseShippingAddress']").change(
                            function() {

                                if(jQuery("input[name='UseShippingAddress']").is(":checked")) {
                                    var options = CountryPrice_SetCountriesForDelivery_Original;
                                } else {
                                    var options = CountryPrice_SetCountriesForDelivery_New;
                                }
                                var el = jQuery("select[name='Country']");
                                CountryPrice_SetCountriesForDelivery.swappingOptions(
                                    el,
                                    options
                                );

                            }
                        )
                        .change();
                    },

                    swappingOptions: function(el, newOptions) {
                        var oldValue = jQuery(el).val();
                        jQuery(el).empty();
                        $.each(
                            newOptions,
                            function(key,value) {
                                el.append($("<option></option>")
                                    .attr("value", key).text(value));
                        });
                        jQuery(el).val(oldValue);
                    }
                }

                CountryPrice_SetCountriesForDelivery.init();
            }
        }
    );

})(jQuery);
