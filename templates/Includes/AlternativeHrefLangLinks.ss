<% cached AlternativeHrefLangLinksCachingKey %>
<% if $AvailableTranslationLinks %>
<link rel="alternate" hreflang="x-default" href="$AbsoluteLink"/>
<% loop $AvailableTranslationLinks %>
    <link rel="alternate" hreflang="$EcommerceCountry.ComputedLanguageAndCountryCode" href="$Link.URL" />
<% end_loop %>
<% end_if %>
<link rel="canonical" href="<% if $CanonicalObject %>$CanonicalObject.Link<% else %>$AbsoluteLink<% end_if %>"/>
<% end_cached %>
