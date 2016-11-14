<% cached AlternativeHrefLangLinksCachingKey %>
<% if $AvailableTranslationLinks %>
<% loop $AvailableTranslationLinks %>
    <link rel="alternate" hreflang="$EcommerceCountry.ComputedLanguageAndCountryCode" href="$Link.URL" />
<% end_loop %>
    <link rel="alternate" hreflang="x-default" href="$AbsoluteLink" />
<% end_if %>
<% end_cached %>
