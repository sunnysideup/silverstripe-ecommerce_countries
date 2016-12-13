<% cached AlternativeHrefLangLinksCachingKey %>
<% if $AvailableTranslationLinks %>
    <link rel="alternate" hreflang="x-default" href="$AbsoluteLink" />
<% loop $AvailableTranslationLinks %>
    <link rel="alternate" hreflang="$EcommerceCountry.ComputedLanguageAndCountryCode" href="$Link.URL" />
<% end_loop %>
<% end_if %>
<% if CanonicalLink %><link rel="canonical" href="$CanonicalLink.URL"/><% else %><link rel="canonical" href="$AbsoluteLink"/><% end_if %>
<% end_cached %>
