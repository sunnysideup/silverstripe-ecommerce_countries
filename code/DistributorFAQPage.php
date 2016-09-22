<?php

/**
 * Page where distributors can put specific information about their country.
 *
 *
 *
 */

class DistributorFAQPage extends Page
{
    private static $icon = 'mysite/images/treeicons/DistributorFAQPage';

    private static $description = 'Frequently asked questions related local distributor. You can enter country specific content that will show up here. ';

    private static $can_be_root = true;

    private static $allow_children = 'none';

    public function canCreate($member = null)
    {
        return DistributorFAQPage::get()->count() ? true : false;
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->findOrMakeTab('Root.Main')->removeByName('Content');
        $fields->addFieldToTab('Root.Main', HtmlEditorField::create("Content", "Default Content"));
        $ecommerceCountry = Injector::inst()->get("EcommerceCountry");
        $fields->addFieldToTab(
            'Root.Main',
            new LiteralField(
                "MyContent",
                "<p>You can edit the content for this page on <a href=\"".$ecommerceCountry->CMSEditLink()."\">each country</a>.</p>"
            ),
            "Content"
        );
        $ecommerceCountriesEntered = EcommerceCountry::get()->where("\"FAQContent\" IS NOT NULL AND \"FAQContent\" <> ''");
        $list = array();
        if ($ecommerceCountriesEntered && $ecommerceCountriesEntered->count()) {
            $list = $ecommerceCountriesEntered->map("ID", "Name")->toArray();
        }
        if ($list && count($list)) {
            $enteredForContent = implode(", ", $list);
        } else {
            $enteredForContent = "You have not entered any country specific Content yet";
        }
        $fields->addFieldToTab(
            'Root.Main',
            new ReadonlyField("CountrySpecificDetailsEnteredFor", "Country specific content has been entered for", $enteredForContent),
            "Content"
        );
        return $fields;
    }
}

class DistributorFAQPage_Controller extends Page_Controller
{

    /**
     * finds the country specific content
     *
     */
    public function getContent()
    {
        $country = CountryPrice_EcommerceCountry::get_distributor_country();
        if ($country && strlen($country->FAQContent) > 17) {
            return $country->FAQContent;
        } else {
            return $this->dataRecord->Content;
        }
    }

    public function Distributor()
    {
    }
}
