<?php


class CountryPriceTestController extends ContentController {

    private static $allowed_actions = array(
        "whoami" => true,
        "testpaymentgateway" => true,
        "addproducts" => true,
        "testcm" => true,
        "setmycountry" => true,
        "resetmycountry" => true,
        "currencypercountry" => true,
        "countrieswithoutdistributor" => true,
        "stockistcountrieswithoutcountry" => true
    );

    function init(){
        parent::init();
        if(strtotime("NOW") < (strtotime($this->LastEdited) +( 60 * 15) ) ) {
            //all OK
        }
        elseif(! Permission::check('ADMIN')) {
            Security::permissionFailure($this, 'This page is secured and you need administrator rights to access it. You can also save the page in the CMS to get 15 minutes access without being logged in.');
            return;
        }
    }

    function index(){
        echo "<hr />";
        echo "<hr />";
        echo "<hr />";
        foreach(self::$allowed_actions as $action => $notNeeded ) {
            if(!in_array($action, array("setmycountry", "resetmycountry"))) {
                DB::alteration_message("<a href=\"".$this->Link($action)."\">$action</a>");
            }
        }
        DB::alteration_message("<a href=\"/dev/ecommerce/\">All E-commerce Tools</a>");
    }

    public function testcm(){
        $obj = new CampaignMonitorAPIConnector();
        $obj->init();
        $obj->getCampaigns();
    }

    function Link($action = null)
    {
        if($action) {
            $action .= "/";
        }
        return "/countrypricetestcontrollerurl/".$action;
    }

    function addproducts(){
        $sc = ShoppingCart::singleton();
        $count = 1;
        $products = Product::get()->filter("AllowPurchase", 1)->limit(20)->Sort("Rand()");
        foreach($products as $product) {
            if($product->canPurchase() && $count < 3) {
                $count++;
                $sc->addBuyable($product, rand(1,5));
            }
        }
        $count = 1;
        $productVariations = ProductVariation::get()->filter("AllowPurchase", 1)->limit(20)->Sort("Rand()");
        foreach($productVariations as $productVariation) {
            if($productVariation->canPurchase() && $count < 3) {
                $count++;
                $sc->addBuyable($productVariation, rand(1,5));
            }
        }
        return $this->redirect($this->Link());
    }


    function resetmycountry($request) {
        Session::clear("countryfortestingonly");
        Session::clear("MyCloudFlareCountry");
        Session::clear("MyCloudFlareIPAddress");
        Session::clear("ipfortestingonly");
        Session::save();
        Session::clear("countryfortestingonly");
        Session::clear("MyCloudFlareCountry");
        Session::clear("MyCloudFlareIPAddress");
        Session::clear("ipfortestingonly");
        Session::save();
        DB::alteration_message("Cleared countries");
        return $this->redirect("/dev/ecommerce/ecommercetaskcartmanipulation_current");
    }


    function setmycountry($request) {
        $value = strtoupper(Convert::raw2sql($request->param("ID")));
        if($o = ShoppingCart::current_order()) {
            $o->SetCountryFields($value);
            $o->CurrencyCountry = $value;
            $o->write();
            $o->SetCountryFields($value);
            $o->CurrencyCountry = $value;
            $o->write();
            CountryPrice_OrderDOD::localise_order();
            return $this->redirectBack('/dev/ecommerce/ecommercetaskcartmanipulation_current/');
        }
    }

    function currencypercountry($request){
        $currencies = CountryPrice::get_currency_per_country();
        $usedCurrencies = CountryPrice::get_currency_per_country_used_ones();
        $useArray = array();
        $nonUseArray = array();
        foreach($currencies as $country => $currency) {
            $countryName = EcommerceCountry::find_title($country);
            $currencyObject = EcommerceCurrency::create_new($currency);
            if(isset($usedCurrencies[$country])) {
                $useArray[] = "$country: $currency ($countryName, ".$currencyObject->getTitle().")";
            }
            else {
                $nonUseArray[] = "$country: $currency ($countryName, ".$currencyObject->getTitle().")";
            }

        }
        echo "<h2>Countries ready for sale with their currency:</h2>";
        foreach($useArray as $line) {
            DB::alteration_message($line);
        }
        echo "<h2>Countries NOT ready for sale with their currency:</h2>";
        foreach($nonUseArray as $line) {
            DB::alteration_message($line);
        }
        return $this->index();
    }

    function countrieswithoutdistributor($request) {
        $list = EcommerceCountry::get()->filter(array("DistributorID" => 0));
        foreach($list as $country) {
            DB::alteration_message("<a href=\"".$country->CMSEditLink()."\">".$country->Code." ".$country->Name."</a>");
        }
        return $this->index();
    }

    function stockistcountrieswithoutcountry($request) {
        $mainPage = StockistSearchPage::get()->first();
        $list = StockistCountryPage::get()->where("Country = '' OR Country IS NULL")->exclude(array("ParentID" => $mainPage->ID));
        foreach($list as $stockistCountry) {
            DB::alteration_message("<a href=\"".$stockistCountry->CMSEditLink()."\">".$stockistCountry->Title."</a>");
        }
        return $this->index();
    }

    function testpaymentgateway(){
        CountryPrice_OrderDOD::set_payment_gateway();
        DB::alteration_message("Gateway is set to: ".Session::get("MyPaymentGateway"));
        return $this->index();
    }

    function whoami(){
        $descriptionArray = array(
            "MyCountryCode" => "This is the key function",
            "MyCountryTitle" => "",
            "MyCurrency" => "Based on the currency set in the order.",
            "MyDistributor" => "You have to set the distributor's countries in order to work out someone's distributor.",
            "MyDistributorCountry" => "This is the country that is being used for the sale.  For dodgy countries, we use the backup country.",
            "MyDeliveryCostNote" => "Set in country, distributor and default country.",
            "MyShippingDeliveryInfo" => "Set in country, distributor and default country.",
            "MyShippingReturnInfo" => "Set in country, distributor and default country.",
            "MyProductNotAvailableNote" => "Set in country, distributor, default country, AND the Ecommerce Config.",
            "MyStockistSearchPage" => "",
            "MyStockistCountryPage" => "The stockist page that is related to the visitor's country",
            "MyCountryFAQPage" => "",
            "MyBackupCountryCode" => "This country is used if the information for the selected country is not available.",
            "MyDefaultDistributor" => "You can set one default distributor (head office) with a tickbox for any Distributor",

        );
        $array = array(
            "MyCountryCode" => "String",
            "MyCountryTitle" => "String",
            "MyCurrency" => "Object",
            "MyDistributor" => "Object",
            "MyDistributorCountry" => "Object",
            "MyDeliveryCostNote" => "String",
            "MyShippingDeliveryInfo" => "String",
            "MyShippingReturnInfo" => "String",
            "MyProductNotAvailableNote" => "String",
            "MyStockistSearchPage" => "Object",
            "MyStockistCountryPage" => "Object",
            "MyCountryFAQPage" => "Object",
            "MyBackupCountryCode" => "String",
            "MyDefaultDistributor" => "Object",
        );
        echo "<h1>Current Settings</h1>";
        echo "<p>Most of these settings can be adjusted in the country, distributor and generic e-commerce settings.</p>";
        foreach($array as $name => $type) {
            $notSet = false;
            if($type == "String") {
                $string = $this->$name();
                if(is_object($string)) {
                    $string = $string->raw();
                }
            }
            else {
                $obj = $this->$name();
                if(!$obj) {
                    $notSet = true;
                }
                else {
                    switch($name) {
                        case "MyDistributorCountry":
                        case "MyCountryFAQPage":
                        case "MyDefaultDistributor":
                        case "MyDistributor":
                            $string = $obj->Name;
                            break;

                        case "MyStockistSearchPage":
                        case "MyStockistCountryPage":
                            $string = $obj->Title;
                            break;
                        default:
                            $string = print_r($obj, 1);
                    }
                }
            }
            if($notSet || !$string) {
                $string = "NOT SET";
                $style = "deleted";
            }
            else {
                $style = "created";
            }
            DB::alteration_message("<i>$name</i>: <u><strong>".$string."</strong></u> ... <br /><sup>".$descriptionArray[$name]."</sup><hr />", $style);
        }
        echo "<h1>Change Settings</h1>";
        $links = array(
            "resetmycountry/" => "Go back to the standard country",
        );
        $countries = array(
            "NZ" => "New Zealand",
            "AU" => "Australia",
            "FR" => "France",
            "BW" => "Botswana",
            "US" => "US",
            "IT" => "Italy",
            "CN" => "China"
        );
        foreach($countries as $code => $name) {
            $links["setmycountry/$code/?countryfortestingonly=$code"] = "Change to $name as country";
        }
        $links["/dev/tasks/TEST_GEOIP_COUNTRY_CODE_BY_NAME"] = "test GEOIP function";
        foreach($links as $link => $desc) {
            DB::alteration_message("<a href=\"".$this->Link($link)."\">".$desc."</a>");
        }
        return $this->index();
    }


    /**
     *
     * @return String
     */
    function MyCurrency(){
        return ShoppingCart::current_order()->CurrencyUsed()->Name;
    }

    /**
     *
     * @return String
     */
    function MyCountryCode(){
        return EcommerceCountry::get_country();
    }

    /**
     *
     * @return String
     */
    function MyBackupCountryCode(){
        return EcommerceCountryDOD::get_backup_country()->Code;
    }

    /**
     * @return String
     */
    function MyCountryTitle(){
        $code = EcommerceCountry::get_country();
        return EcommerceCountry::find_title($code);
    }

    function MyDefaultDistributor(){
        return Distributor::get_one_for_country("");
    }



    /**
     * returns note about Delivery Cost
     * Needs to be in model so we can access from the order in the template.
     * @return Varchar Field
     */
    function MyDeliveryCostNote(){
        $note = EcommerceCountry::get_country_object()->DeliveryCostNote;
        if(!$note) {
            if($distributor = $this->MyDistributor()) {
                $note = $distributor->DeliveryCostNote;
            }
            if(!$note) {
                $note = EcommerceCountryDOD::get_backup_country()->DeliveryCostNote;
            }
        }
        return DBField::create_field("Varchar", $note);
    }

    /**
     * returns note about Shipping Estimation Time
     * Needs to be in model so we can access from the order in the template.
     * @return Varchar Field
     */
    function MyShippingDeliveryInfo(){
        $info = EcommerceCountry::get_country_object()->ShippingEstimation;
        if(!$info) {
            if($distributor = $this->MyDistributor()) {
                $info = $distributor->ShippingEstimation;
            }
            if(!$info) {
                $info = EcommerceCountryDOD::get_backup_country()->ShippingEstimation;
            }
        }
        return DBField::create_field("Varchar", $info);
    }

    /**
     * returns the Distributor for the user
     * Needs to be in model so we can access from the order in the template.
     * @return Varchar Field
     */
    function MyDistributor(){
        $code = EcommerceCountry::get_country();
        $distributor = Distributor::get_one_for_country($code);
        return $distributor;
    }

    /**
     * returns note about shipping return information
     * Needs to be in model so we can access from the order in the template.
     * @return Varchar Field
     */
    function MyShippingReturnInfo(){
        $info = EcommerceCountry::get_country_object()->ReturnInformation;
        if(!$info) {
            if($distributor = $this->MyDistributor()) {
                $info = $distributor->ReturnInformation;
            }
            if(!$info) {
                $info = EcommerceCountryDOD::get_backup_country()->ReturnInformation;
            }
        }
        return DBField::create_field("Varchar", $info);
    }


    /**
     * returns note to show when product / variation is not available in the specific country
     * Needs to be in model so we can access from the order in the template.
     * @return Varchar Field
     */
    function MyProductNotAvailableNote(){
        $note = EcommerceCountry::get_country_object()->ProductNotAvailableNote;
        if(!$note) {
            if($distributor = $this->MyDistributor()) {
                $note = $distributor->ProductNotAvailableNote;
            }
            if(!$note) {
                $note = EcommerceCountryDOD::get_backup_country()->ProductNotAvailableNote;
            }
            if(!$note) {
                $note = $this->EcomConfig()->NotForSaleMessage;
            }
        }
        return DBField::create_field("HTMLText", $note);
    }


    /**
     * provides information on where to buy a product
     * if it can not be bought online...
     * @return StockistCountryPage | Null
     */
    function MyStockistCountryPage($attempts = 0){
        $exit = false;
        switch ($attempts) {
            case 0:
                $countryCode = EcommerceCountry::get_country();
                break;
            case 1:
                $countryCode = EcommerceCountryDOD::get_distributor_country();
                break;
            case 2:
                 $countryCode = EcommerceConfig::get('EcommerceCountry', 'default_country_code');
                break;
                break;
            default:
                $countryCode = "NZ";
                $exit = true;
        }
        if($countryCode) {
            $countryStockistPage = StockistCountryPage::get()->filter(array("Country" => $countryCode))->first();
            if(!$countryStockistPage) {
                if($countryObject = DataObject::get_one("EcommerceCountry", "Code = '$countryCode'")) {
                    $rows = DB::query("
                        SELECT \"StockistCountryPageID\"
                        FROM \"StockistCountryPage_AdditionalCountries\"
                        WHERE \"EcommerceCountryID\" = ".$countryObject->ID
                    );
                    if($rows) {
                        foreach($rows as $row) {
                            $countryStockistPage = StockistCountryPage::get()->byID($rows["StockistCountryPageID"]);
                            if($countryStockistPage) {
                                break;
                            }
                        }
                    }
                }
            }
            if($countryStockistPage || $exit) {
                return $countryStockistPage;
            }
        }
        $attempts++;
        return $this->MyStockistCountryPage($attempts);
    }

    /**
     *
     * @return EcommerceCountry
     */
    public function MyDistributorCountry() {
        $countryCode = EcommerceCountryDOD::get_distributor_country();
        $countryObject = EcommerceCountry::get()
            ->filter(array("Code" => $countryCode))
            ->First();
        return $countryObject;
    }

    /**
     *
     * @return StockistSearchPage
     */
    public function StockistSearchPage(){
        return StockistSearchPage::get()->filter(array("ClassName" => "StockistSearchPage"))->first();
    }


    public function MyStockistSearchPage(){
        $country = EcommerceCountry::get_country();
        $countryPage = StockistCountryPage::get()->filter(array("Country" => $country))->First();
        $sql = "
            SELECT StockistCountryPageID
            FROM StockistCountryPage_AdditionalCountries
            WHERE EcommerceCountryID = '".EcommerceCountry::get_country_id($country)."'";
        $idForPage = DB::query($sql)->value();
        if($idForPage) {
            StockistCountryPage::get()->byID($idForPage);
        }
        if(!$countryPage) {
            if(!$countryPage) {
                $countryPage = $this->StockistSearchPage();
            }
        }
        return $countryPage;
    }

    /**
     *
     * returns the DistributorFAQPage ONLY if Specific Content has been entered
     * for the country.
     * @return DistributorFAQPage
     */
    function MyCountryFAQPage() {
        $country = $this->MyDistributorCountry();
        if($country && $country->FAQContent) {
            return DistributorFAQPage::get()->First();
        }
    }

}
