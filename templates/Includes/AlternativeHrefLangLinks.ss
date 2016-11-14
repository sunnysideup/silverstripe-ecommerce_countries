<% if $AvailableTranslationLinks %>
<% loop $AvailableTranslationLinks %>
    <link rel="alternate" hreflang="$EcommerceCountry.ComputedLanguageAndCountryCode" href="$Link" />
<% end_loop %>
    <link rel="alternate" hreflang="x-default" href="$Link" />
<% end_if %>
