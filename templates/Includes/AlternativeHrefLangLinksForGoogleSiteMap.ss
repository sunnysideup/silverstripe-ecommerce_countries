<% if $AvailableTranslationLinks %><% loop $AvailableTranslationLinks %>
            <xhtml:link rel="alternate" hreflang="$EcommerceCountry.ComputedLanguageAndCountryCode" href="$Link.URL" /><% end_loop %>
            <xhtml:link rel="alternate" hreflang="x-default" href="<% if $CanonicalLink %>$CanonicalLink.Link<% else %>$AbsoluteLink<% end_if %>"/><% end_if %>