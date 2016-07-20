if(typeof CountryPrice_ShippingCountry_Options_Original !== 'undefined' && CountryPrice_ShippingCountry_Options_New !== "undefined")
{
    (function($) {
        $(document).ready(
            function() {
                EcomOrderFormWithShippingAddress.init();
            }
        );

    })(jQuery);

    CountryPrice_ShippingCountry = {
        init: function() {
            jQuery("input[name='UseShippingAddress']").change(
                function() {

                    if(jQuery("input[name='UseShippingAddress']").is(":checked")) {
                        var options = CountryPrice_ShippingCountry_Options_Original;
                    } else {
                        var options = CountryPrice_ShippingCountry_Options_New;
                    }
                    var el = jQuery("select[name='Country']");
                    CountryPrice_ShippingCountry.swappingOptions(
                        el,
                        options
                    );

                }
            )
            .change();
        },

        swappingOptions(el, newOptions) {

            while (el.options.length > 0) {
                el.remove(el.options.length - 1);
            }

            for (var code in newOptions) {
                if (newOptions.hasOwnProperty(code)) {
                    var title = newOptions[key];
                    var opt = document.createElement('option');

                    opt.text = title;
                    opt.value = code;

                    el.add(opt, null);
                }
            }
        }
    }
}
