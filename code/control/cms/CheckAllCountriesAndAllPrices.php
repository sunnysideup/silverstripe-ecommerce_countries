<?php

/**
 * Controller that allows checking of all information.
 * @author Nicolaas @ sunnysideup .co .nz
 *
 *
 */

class CheckAllCountriesAndAllPrices extends Controller {

    private static $allowed_actions = array(
        "setcountryprice",
        "setobjectfield"
    );

    /**
     * Holds the current distributor, if any
     *
     * @Var Distributor | Null
     */
    private $distributor = null;

    /**
     * List of Countries that the current member is allowed to see.
     * like so:
     * ID => Code
     * 1 => NZ
     *
     * @Var Array
     */
    private $countryArray = array(0 => "--");

    /**
     * determine level of access
     */
    function init(){
        parent::init();
        $member = Member::currentUser();
        $canViewAndEdit = false;
        $countries = null;
        if($member) {
            if(Permission::check('ADMIN')) {
                $countries = EcommerceCountry::get()
                    ->exclude(array("DistributorID" => 0));
                $canViewAndEdit = true;
            }
            else {
                $distributor = $member->Distributor();
                if($distributor->exists()) {
                    $this->distributor = $distributor;
                    $countries = $distributor->Countries();
                    $canViewAndEdit = true;
                }
            }
            if($countries && $countries->count()) {
                $this->countryArray = $countries->map("ID", "Code")->toArray();
            }
        }
        if(!$canViewAndEdit) {
            Security::permissionFailure($this, 'Please log in first or log in as a distributor');
        }
    }



    /********************
     * actions
     ********************/

    /**
     *
     *
     * NOT CURRENTLY IN USE!!!
     */
    function setcountryprice() {
        return "NOT IN USE";
        // 1) Check that all parameters have been specified

        $fields = $this->getCountryPriceIndexes();
        $fields['Price'] = 'Price';

        foreach($fields as $field) {
            if(! isset($_REQUEST[$field])) {
                return "$field value missing";
            }
        }

        // 2 Check the parameters values

        $objectClass = $_REQUEST['ObjectClass'];
        $valid = false;
        if(class_exists($objectClass)) {
            $object = singleton($objectClass);
            if(is_a($object, 'DataObject') && $object->hasExtension('CountryPrice_BuyableExtension')) {
                $valid = true;
            }
        }
        if(! $valid) {
            return 'ObjectClass value incorrect';
        }

        $objectID = intval($_REQUEST['ObjectID']);
        $object = $objectClass::get()->byID($objectID);
        if(! $object) {
            return 'ObjectID value incorrect';
        }

        $country = strtoupper($_REQUEST['Country']);
        $valid = false;
        $currencyPerCountry = EcommerceCountry::get()->map("Ecommerce");
        if(isset($currencyPerCountry[$country])) {
            $valid = true;
            if($this->distributor) {
                $countries = $this->distributor->Countries()->map('Code', 'Code')->toArray();
                if(! in_array($country, $countries)) {
                    $valid = false;
                }
            }
        }
        if(! $valid) {
            return 'Country value incorrect';
        }

        $currency = strtoupper($_REQUEST['Currency']);
        if(strlen($currency) != 3) {
            return 'Currency value incorrect';
        }

        $price = $_REQUEST['Price'];
        if(! is_numeric($price)) {
            return 'Price value incorrect';
        }

        DB::query("
            INSERT INTO \"CountryPrice\" (\"Created\",\"LastEdited\",\"Price\",\"Country\",\"Currency\",\"ObjectClass\",\"ObjectID\") VALUES (NOW(),NOW(),$price,'$country','$currency','$objectClass',$objectID)
            ON DUPLICATE KEY UPDATE \"LastEdited\" = VALUES(\"LastEdited\"), \"Price\" = VALUES(\"Price\")");

        return true;
    }


    /**
     *
     *
     * update field
     */
    function setobjectfield() {

        if(isset($_REQUEST["F"]) && $_REQUEST["F"]== "TESTONLY") {
            return "THIS IS FOR TESTING ONLY";
        }
        // 1) Check that all parameters have been specified

        foreach(array('T', 'I', 'F', 'V') as $field) {
            if(! isset($_REQUEST[$field])) {
                return "$field value missing";
            }
        }

        // 2 Check the parameters values

        $result = 1; // Updated

        $objectClass = $_REQUEST['T'];
        if(! class_exists($objectClass)) {
            return 'ObjectClass value incorrect';
        }

        $objectID = $_REQUEST['I'];
        if($objectClass == 'CountryPrice' && is_array($objectID)) {
            $object = new $objectClass($objectID);
            $objectID = $object->write();
            $result = 2; // Added
        }
        else if(is_int($objectID)) {
            $objectID = intval($objectID);
            $object = $objectClass::get()->byID($objectID);
            if(! $object) {
                return 'ObjectID value incorrect';
            }
        }
        else {
            return 'ObjectID value incorrect';
        }

        //check if object can be edited!
        if(!$this->canEditThisObject($objectClass, $objectID)) {
            return 'You can not edit this object';
        }
        $fieldName = Convert::raw2sql($_REQUEST['F']);
        $value = $_REQUEST['V'];

        if($fieldName == 'Price' && (! is_numeric($value) || $value < 0)) {
            return 'Price value incorrect';
        }

        $object->$fieldName = $value;
        $object->write();


        //create log
        $log = new CheckAllCountriesAndAllPrices_Log();
        $member = Member::currentUser();
        $log->UserEmail = $member->Email;
        $log->ObjectClass = $objectClass;
        $log->ObjectID = $objectID;
        $log->FieldName = $fieldName;
        $log->NewValue = $value;
        if($this->distributor) {
            $log->DistributorID = $this->distributor->ID;
        }
        $log->write();
        return $result;
    }


    /**
     * Title for page
     * @return String
     */
    function DistributorTitle() {
        return Permission::check('ADMIN') ? 'Shop Administrator' : $this->distributor->Name;
    }

    function DistributorFilterList(){
        $html = "";
        if(!$this->distributor) {
            $distributors = Distributor::get();
            foreach($distributors as $distributor) {
                $countries = $distributor->Countries();
                if($countries && $countries->count()) {
                    $html .= "
                        <li>
                            <a href=\"#Distributor".$distributor->ID."\" data-name=\"Distributor".$distributor->ID."\" data-countries=\"".implode(",", $countries->map("ID", "ID")->toArray())."\">".
                                $distributor->Name.
                            "</a>
                        </li>
                    ";
                }
            }
        }
        return $html;
    }

    /**
     * returns list of Distributor Fields
     * @return String
     */
    function Distributors() {
        $html = "";
        $where = "";
        if($this->distributor) {
            $where = "ID = {$this->distributor->ID}";
        }
        $distributors = Distributor::get()
            ->where($where);
        if($distributors && $distributors->count()) {
            foreach($distributors as $distributor) {
                $data = array('T' => 'Distributor', 'I' => $distributor->ID);
                $html .= $this->createTreeNode(
                    $distributor->Name,
                    "",
                    array($distributor)
                );
                $html .= $this->createEditNode(
                    "Name",
                    "",
                    $distributor->Name,
                    $data + array("F" => "Name")
                );
                $html .= $this->createEditNode(
                    "Email",
                    "",
                    $distributor->Email,
                    $data + array("F" => "Email")
                );
                if(Permission::check("ADMIN")) {
                    $html .= $this->createEditNode(
                        "Default Distributor",
                        $distributor->IsDefault ? "YES" : "NO"
                    );
                }

                //countries
                $countryList = array();
                $countries = $distributor->Countries();
                foreach($countries as $country) {
                    $countryList[] = $country->Name . ($country->DoNotAllowSales ? ' (Sales not allowed)' : '');
                }
                $html .= $this->createEditNode(
                    'Countries',
                    implode(', ', $countryList)
                );

                //users
                $memberList = "";
                if($members = $distributor->Members()) {
                    if($members->count()) {
                        $memberList = implode(", ", $members->map("ID", "Email")->toArray());
                    }
                }
                $html .= $this->createEditNode(
                    "Registered editors",
                    $memberList
                );
                $html .= $this->closeTreeNode();
            }
        }
        return $html;
    }


    /**
     * returns list of Country Fields
     * @return String
     */
    function AllowSalesTo(){
        $html = "";
        $countries = EcommerceCountry::get()
            ->filter(
                array(
                    "DoNotAllowSales" => 0,
                    "Code" => $this->countryArray
                )
            )
            ->exclude(array("DistributorID" => 0));
        if($countries && $countries->count()) {
            foreach($countries as $country) {

                //delivery options
                $deliveryOptionItems = PickUpOrDeliveryModifierOptions::get_all_as_country_array();
                $deliveryOptions = "";
                if($deliveryOptionItems) {
                    foreach($deliveryOptionItems as $deliveryOptionCode => $countryCodes) {
                        foreach($countryCodes as $countryCode) {
                            if($countryCode == $country->Code) {
                                $deliveryOptions .= $deliveryOptionCode.", ";
                            }
                        }
                    }
                }

                //tax options
                $taxesObjects = GSTTaxModifierOptions::get()
                    ->filter(
                        array(
                            "CountryCode" => $country->Code
                        )
                    );
                if($taxesObjects && $taxesObjects->count()) {
                    $taxes = implode(",", $taxesObjects->map("ID", "Name")->toArray());
                }
                else {
                    $taxes = "";
                }

                $countrySpecificMessages = "tba";
                //compile
                $data = array('T' => 'EcommerceCountry', 'I' => $country->ID);
                $distributorName = $country->Distributor()->Name;
                if(!$distributorName) {
                    $distributorName = "<p class=\"message bad\">No Distributor has been assigned to this country.</p>";
                }
                $html .= $this->createTreeNode($country->Code." - ".$country->Name, $country->Code, array($country));

                $html .= $this->createEditNode("Distributor", $distributorName);
                $html .= $this->createEditNode("FAQ Content", "", $country->FAQContent, $data + array("F" => "FAQContent"), "textarea");
                $html .= $this->createEditNode("Top Bar Message","",  $country->TopBarMessage, $data + array("F" => "TopBarMessage"), "");
                $html .= $this->createEditNode("Country Specific Messages", $countrySpecificMessages);
                $html .= $this->createEditNode("Delivery Options", $deliveryOptions);
                $html .= $this->createEditNode("Taxes", $taxes);
                $html .= $this->createEditNode("Payment Options", $country->Code == 'AU' ? 'Eway' : 'DPS (Credit Card), Direct Credit');

                $html .= $this->closeTreeNode();
            }
        }
        $countries =  DataObject::get("EcommerceCountry", " DistributorID = 0 AND DoNotAllowSales = 0");
        if($countries && $countries->count()){
            $list = implode(", ", $countries->map("ID", "Code")->toArray());
            $html .= $this->createEditNode("Countries without distributor that allow sales", $list);
        }
        $countries =  DataObject::get("EcommerceCountry", " DistributorID = 0 AND DoNotAllowSales = 1");
        if($countries && $countries->count()){
            $list = implode(", ", $countries->map("ID", "Code")->toArray());
            $html .= $this->createEditNode("Countries without distributor that do not allow sales", $list);
        }
        return $html;
    }


    /**
     * returns list of Product Fields
     * @return String
     */
    function Products() {
        $html = "";
        $where = "";
        $countryArray = array();
        $products = ProductPage::get()
            ->filter(array("AllowPurchase" => 1))
            ->sort("FullSiteTreeSort", "ASC");
        $defaultPriceText = ' (Default for new variations)';
        if($products && $products->count()) {
            foreach($products as $product) {
                $html .= $this->createTreeNode($product->FullName, "pricing", array($product));

                //country exceptions
                if(Permission::check('ADMIN')) {
                    $includedCountries = $product->IncludedCountries();
                    if($includedCountries && $includedCountries->Count()) {
                        $html .= $this->createEditNode(
                            "Additional countries this product is sold ... ",
                            implode(", ", $includedCountries->map("ID", "Code")->toArray())
                        );
                    }
                    $excludedCountries = $product->ExcludedCountries();
                    if($excludedCountries && $excludedCountries->Count()) {
                        $html .= $this->createEditNode(
                            "This product is not sold in ... ",
                            implode(", ", $excludedCountries->map("ID", "Code")->toArray())
                        );
                    }
                    //only show default price for ShopAdmin
                    $html .= $this->createEditNode(
                        'Default Price' . ($product->hasVariations() ? $defaultPriceText : ''),
                        EcommercePayment::site_currency(),
                        $product->Price,
                        array(
                            "T" => "Product",
                            "F" => "Price",
                            "I" => $product->ID
                        )
                    );
                }

                //country prices
                $outstandingCountries = $this->countryArray;
                $countryPricesObjects = $product->CountryPrices();
                $html .= $this->createTreeNode(
                    'Country Prices' . ($product->hasVariations() ? $defaultPriceText : ''),
                    " pricing countryPrices"
                );
                $arrayOfProductCountryCurencyPrices = array();
                $countriesWithProductPrices = array();
                if($countryPricesObjects->count()) {
                    foreach($countryPricesObjects as $countryPricesObject) {
                        $arrayOfProductCountryCurencyPrices[$countryPricesObject->Currency.$countryPricesObject->Country] = $countryPricesObject->Currency.$countryPricesObject->Country;
                        $countryObject = $countryPricesObject->CountryObject();
                        if(!$countryObject) {
                            break;
                        }
                        $countryID = array_search($countryPricesObject->Country, $outstandingCountries);
                        if(Permission::check('ADMIN') || $countryID !== false) {
                            $data = array("T" => "CountryPrice", "I" => $countryPricesObject->ID, "F" => "Price");
                            $html .= $this->createEditNode(
                                $countryPricesObject->Country,
                                $countryPricesObject->Currency,
                                $countryPricesObject->Price,
                                $data,
                                "input",
                                array($countryObject)
                            );
                            unset($outstandingCountries[$countryID]);
                        }
                    }
                }
                $addText = 'Add price to start selling';
                foreach($outstandingCountries as $countryCode) {
                    $countryObject = EcommerceCountry::get()->filter(array("Code" =>$countryCode));
                    if(!$countryObject) {user_error("country not found");}
                    $currencyObject = $countryObject->BestEcommerceCurrency();
                    $data = array(
                        "T" => "CountryPrice",
                        "I" => array(
                            'Country' => $countryCode,
                            'Currency' => $currencyObject->Code,
                            'ObjectClass' => $product->ClassName,
                            'ObjectID' => $product->ID
                        ),
                        'F' => 'Price'
                    );
                    $html .= $this->createEditNode(
                        $countryCode,
                        $currency->Code,
                        $addText,
                        $data,
                        "input",
                        array($countryObject)
                    );
                }
                $html .= $this->closeTreeNode();
                if($product->hasVariations()) {
                    $variations = $product->Variations();
                    if($variations && $variations->count()) {
                        $html .= $this->createTreeNode("Variations", "variations pricing");
                        $variations = $product->Variations();
                        foreach($variations as $variation) {
                            if($variation->AllowPurchase) {
                                $html .= $this->createTreeNode($variation->getTitle());
                                if(Permission::check('ADMIN')) {
                                    $html .= $this->createEditNode(
                                        "Default Price",
                                        EcommercePayment::site_currency(),
                                        $variation->Price,
                                        array(
                                            "T" => "ProductVariation",
                                            "F" => "Price",
                                            "I" => $variation->ID
                                        )
                                    );
                                }
                                $outstandingCountries = $this->countryArray;
                                $countryPricesObjects = $variation->CountryPrices();
                                $html .= $this->createTreeNode("Variation Country Prices", " pricing countryPrices variationCountryPrices");
                                if($countryPricesObjects->count()) {
                                    $lowestPrice = 999999;
                                    foreach($countryPricesObjects as $countryPricesObject) {
                                        $countryObject = $countryPricesObject->CountryObject();
                                        if(!$countryObject) {
                                            break;
                                        }
                                        $countryID = array_search($countryPricesObject->Country, $outstandingCountries);
                                        if(Permission::check('ADMIN') || $countryID !== false) {
                                            $data = array(
                                                "T" => "CountryPrice",
                                                "I" => $countryPricesObject->ID,
                                                "F" => "Price"
                                            );
                                            $html .=  $this->createEditNode(
                                                $countryPricesObject->Country,
                                                $countryPricesObject->Currency,
                                                $countryPricesObject->Price,
                                                $data,
                                                "input",
                                                array($countryObject)
                                            );
                                            if($countryPricesObject->Price < $lowestPrice) {
                                                $lowestPrice = $countryPricesObject->Price;
                                            }
                                            unset($outstandingCountries[$countryID]);
                                        }
                                    }
                                    if($lowestPrice > 0 && $lowestPrice < 999999) {
                                        if(!in_array($countryPricesObject->Currency.$countryPricesObject->Country, $arrayOfProductCountryCurencyPrices)) {
                                            $sql = '
                                                INSERT INTO "CountryPrice" ("Created","LastEdited","Price","Country","Currency","ObjectClass","ObjectID")
                                                VALUES (NOW(), NOW(), \''.$lowestPrice.'\',\''.$countryPricesObject->Country.'\',\''.$countryPricesObject->Currency.'\',\''.$product->ClassName.'\','.$product->ID.')
                                                ON DUPLICATE KEY
                                                    UPDATE "LastEdited" = VALUES("LastEdited"), "Price" = VALUES("Price")
                                            ';
                                            DB::alteration_message("adding product price for ".$product->Title." in ".$countryPricesObject->Country." because there are variation prices");
                                            DB::query($sql);
                                        }
                                    }
                                }
                                foreach($outstandingCountries as $countryCode) {
                                    $countryObject = DataObject::get_one("EcommerceCountry", "Code = '".$countryCode."'");
                                    if(!$countryObject) {user_error("country not found");}
                                    $currency = $countryObject->BestEcommerceCurrency();
                                    $data = array(
                                        "T" => "CountryPrice",
                                        "I" => array(
                                            'Country' => $countryCode,
                                            'Currency' => $currency->Code,
                                            'ObjectClass' => $variation->ClassName,
                                            'ObjectID' => $variation->ID
                                        ),
                                        'F' => 'Price'
                                    );
                                    $html .= $this->createEditNode(
                                        $countryCode,
                                        $currency,
                                        $addText,
                                        $data,
                                        "input",
                                        array($countryObject)
                                    );
                                }
                                $html .= $this->closeTreeNode();
                                $html .= $this->closeTreeNode();
                            }
                        }
                        $html .= $this->closeTreeNode();
                    }
                }
                $html .= $this->closeTreeNode();
            }
        }
        else {
            $html .= $this->createEditNode("Products");
        }
        return $html;
    }

    /**
     * returns list of Currencies with info
     * @return String
     */
    function Currencies() {
        $html = "";
        $currencies = EcommerceCurrency::get();
        foreach($currencies as $currency) {
            $priceCount = CountryPrice::get()->filter(array("Currency" => $currency->Code))->count();
            if($priceCount) {
                $name = $currency->Code." ($priceCount price points)";
                if($currency->IsDefault()) {
                    $name .= " [DEFAULT CURRENCY]";
                }
                $html .= $this->createTreeNode($name, "currencies");
                $html .= $this->createEditNode(
                    'Countries',
                    "tba"
                );
                $html .= $this->closeTreeNode();
            }
        }
        //$html .= $this->createTreeNode("Currencies");
        return $html;
    }


    /********************
     * internal methods
     ********************/


    /**
     * returns the opening of a list (<li>Title<ul>)
     * @param String $title - e.g. My items
     * @param String $classOrCountryCode -e.g. myCSSClass or NZ
     * @param Array list of objects used...
     * @return String
     */
    private function createTreeNode($title, $classOrCountryCode = "", $objectArray = array()) {
        $class = "";
        if(strlen($classOrCountryCode) == 2 && $img = $this->codeToFlag($classOrCountryCode)) {
            $title = $img.$title;
            $classOrCountryCode = "";
        }
        elseif($classOrCountryCode) {
            $class = " class=\"$classOrCountryCode\"";
        }
        $filterClass = '';
        foreach($objectArray as $object) {
            $filterClass .= ' '.$object->ClassName.$object->ID.' gp'.$object->ClassName;
        }
        return "<li class=\"$filterClass\"><strong>$title</strong><ul{$class}>";
    }


    /**
     * returns the opening of a list (<li>Title<ul>)
     * @return String
     */
    private function closeTreeNode() {
        return "</ul></li>";
    }

    /**
     * returns the opening of a list (<li>Title<ul>)
     * @param String $label - e.g. My Price
     * @param String $nonEditText - e.g. USD
     * @param String $editText - e.g. 99.95
     * @param Arra $data - should include the following: "T", "I", "F"
     * @param String $fieldType -e.g. input, textarea, checkbox
     * @return String (<li>edit me</li>)
     */
    private function createEditNode(
        $label,
        $nonEditText = "",
        $editText = "",
        $data = null,
        $fieldType = "input",
        $objectArray = array()
    ) {
        if(!$data) {
            $ddClass = "readonly";
            $editTextNode = "";
        }
        else {
            $ddClass = "editable";
            $editTextNode = "<a href=\"#\" class=\"edit\">$editText</a>";
        }
        if(strlen($label) == 2 && $img = $this->codeToFlag($label)) {
            $label = $img.$label;
        }
        //can be edited but there is no data
        if( ($data && !$editText)) {
            $editText = "[NONE]";;
        }
        //can not be edited and there is no data
        elseif( !$data && !$editText && !$nonEditText) {
            $nonEditText = "[NONE]";
        }
        $fieldTypeString = "";
        if($fieldType && $fieldType != "input") {
            $fieldTypeString = " data-$fieldType=\"1\"";
        }
        $nonEditTextNode = "";
        if($nonEditText) {
            $nonEditTextNode = "<div class=\"nonEditablePart\">$nonEditText</div>";
        }
        if($editTextNode) {
            $editTextNode = "<div class=\"editablePart\">$editTextNode</div>";
        }
        if($data) {
            foreach(array("T", "F", "I") as $key) {
                if(!isset($data[$key])) { user_error("data must contain T (table), F (field) and I (ID) value"); }
            }
            $data = " data-name=\"".Convert::raw2att(Convert::raw2json($data))."\"";
        }
        $filterClass = '';
        foreach($objectArray as $object) {
            $filterClass .= ' '.$object->ClassName.$object->ID.' gp'.$object->ClassName;
        }
        return "
                    <li{$data}{$fieldTypeString} class=\"$filterClass\">
                        <dl>
                            <dt>$label</dt>
                            <dd class=\"$ddClass valueHolder\">
                                $nonEditTextNode
                                $editTextNode
                            </dd>
                        </dl>
                    </li>";
    }

    private static $flag_cache = array();

    /**
     * turn code into image flag
     * @param String $code
     * @return String html for flag
     */
    private function codeToFlag($code){
        if(isset(self::$flag_cache[$code])) {
            return self::$flag_cache[$code];
        }
        $wwwLocation = "/themes/main/images/flags/".strtolower($code).".png";
        $fileLocation = Director::baseFolder().$wwwLocation;
        if(file_exists($fileLocation)) {
            self::$flag_cache[$code] = "<img src=\"$wwwLocation\" alt=\"$code\" /> ";
        }
        else {
            self::$flag_cache[$code] = false;
        }
        return self::$flag_cache[$code];
    }


    /**
     * turn code into image flag
     * @param String $table
     * @param Int $id
     * @return Boolean
     */
    private function canEditThisObject($table, $id){
        if(Permission::check('ADMIN')) {
            return true;
        }
        switch ($table) {
            case 'Distributor':
                if($this->distributor->ID == $id) {
                    return true;
                }
                return false;
                break;
            case 'EcommerceCountry':
                $obj = EcommerceCountry::get()->byID($id);
                return in_array($obj->Code, $this->countryArray);
                break;
            case 'CountryPrice':
                $obj = CountryPrice::get()->byID($id);
                return in_array($obj->Country, $this->countryArray);
                break;
            case 'Currency':
                return false;
                break;
            default:
                return false;
        }
        return false;
    }

}