<?php

/**
 * A country can only be sent goods to from 1 distributor + the default distributor which can send anywhere in the world.
 * The default distributor shows prices of the default currency.
 * Precondition : There is always a default distributor.
 */
class Distributor extends DataObject implements PermissionProvider {

    private static $db = array(
        'Name' => 'Varchar(255)',
        'IsDefault' => 'Boolean',
        'Email' => 'Varchar(200)',
        'Address1' => "Varchar((255)",
        'Address2' => "Varchar(255)",
        'Address3' => "Varchar(255)",
        'Address4' => "Varchar(255)",
        'Address5' => "Varchar(255)",
        'Phone' => "Varchar(50)",
        'DisplayEmail' => "Varchar(50)",
        'WebAddress' => "Varchar(255)",
        'DeliveryCostNote' => 'Varchar(255)',
        'ShippingEstimation' => 'Varchar(255)',
        'ReturnInformation' => 'Varchar(255)',
        'ProductNotAvailableNote' => 'HTMLText'
    );

    private static $has_one = array(
        'PrimaryCountry' => 'EcommerceCountry'
    );

    private static $has_many = array(
        'Countries' => 'EcommerceCountry',
        'Members' => 'Member',
        'Updates' => 'CheckAllCountriesAndAllPrices_Log'
    );

    private static $field_labels = array(
        'IsDefault' => 'Is Default Distributor? '
    );

    private static $field_labels_right = array(
        'IsDefault' => 'Use this only for the distributor that is applicable when no other distributor applies (e.g. a country that does not have a distributor).',
        'Phone' => 'Please format as +64 8 555 5555'
    );

    private static $extensions = array("Versioned('Stage')");

    private static $summary_fields = array(
        "Name" => "Name",
        "IsDefault.Nice" => "Default"
    );

    private static $default_sort = array(
        "IsDefault" => "DESC",
        "Name" => "Asc"
    );

    /**
     * returns the Distributor for the country OR the default Distributor.
     * @param String $country = the country code.
     * @return Distributor
     */
    public static function get_one_for_country($countryCode) {
        $countryCode = CountryPrice_EcommerceCountry::get_real_country($countryCode);
        if($countryCode) {
            $countryObject = EcommerceCountry::get()->filter(array("Code" => $countryCode))->First();
            if($countryObject) {
                $distributor = $countryObject->Distributor();
                if($distributor->exists()) {
                    return $distributor;
                }
            }
        }
        return Distributor::get()
            ->filter(array("IsDefault" => 1))
            ->First();
    }

    function getCMSFields() {
        $fields = parent::getCMSFields();
        $fieldLabels = $this->FieldLabels();
        $fieldLabelsRight = $this->Config()->get("field_labels_right");
        $listOfCountriesCovered = EcommerceCountry::get()->exclude(array("DistributorID" => 0))->map("Code", "Title");
        //secondary for another country
        $fields->removeByName('Versions');
        if($listOfCountriesCovered && $listOfCountriesCovered->count()) {
            $countryArray =  array(" -- please select --") + $listOfCountriesCovered->toArray();
            $fields->addFieldToTab("Root.Countries", DropdownField::create("PrimaryCountryID", "Primary Country", $countryArray));
        }
        else {
            $fields->removeByName('PrimaryCountryID');
        }
        if($this->IsDefault) {
            $fields->removeByName('Countries');
        }
        else {
            if(empty($this->ID)) {
                $id = 0;
            }
            else {
                $id = $this->ID;
            }
            if($this->ID) {
                $config = GridFieldConfig_RelationEditor::create();
                $config->removeComponentsByType("GridFieldAddNewButton");
                $gridField = new GridField('pages', 'All pages', SiteTree::get(), $config);
                $countryField = new GridField(
                    'Countries',
                    'Countries',
                    $this->Countries(),
                    $config
                );
                $fields->addFieldToTab("Root.Countries", $countryField);
                $fields->removeByName('Members');
                if($this->Version > 1) {
                    $columns = array(
                        'Version' => 'Version',
                        'LastEdited' => 'Date',
                        'Name' => 'Name',
                        'IsDefault' => 'Default',
                        'Email' => 'Email'
                    );
                    $table = '<table class="versions"><thead><tr><th>' . implode('</th><th>', $columns) . '</th></tr></thead><tbody>';
                    $version = $this->Version - 1;
                    while($version > 0) {
                        $versionDO = Versioned::get_version('Distributor', $this->ID, $version--);
                        $values = array();
                        foreach($columns as $column => $title) {
                            $values[] = $versionDO->$column;
                        }
                        $table .= '<tr><td>' . implode('</td><td>', $values) . '</td></tr>';
                    }
                    $table .= '</tbody></table>';
                    $table .= "<style type=\"text/css\">
                                table.versions {border: 1px solid black; width:100%; border-collapse: collapse;}
                                table.versions tr {border: 1px solid black;}
                                table.versions tr th, table.versions tr td {border: 1px solid black; width: auto; text-align: center;}
                               </style>";
                    $fields->addFieldToTab('Root.Versions', new LiteralField('VersionsTable', $table));
                }
            }
        }
        $fields->addFieldsToTab(
            "Root.ContactDetails",
            array(
                new TextField("DisplayEmail"),
                new TextField("Phone"),
                new TextField("Address1"),
                new TextField("Address2"),
                new TextField("Address3"),
                new TextField("Address4"),
                new TextField("Address5")
            )
        );
        $fields->addFieldsToTab(
            "Root.EcomInfo",
            array(
                new TextField("DeliveryCostNote"),
                new TextField("ShippingEstimation"),
                new TextField("ReturnInformation"),
                new HTMLEditorField("ProductNotAvailableNote")
            )
        );
        foreach($fieldLabelsRight as $key => $value) {
            $field = $fields->dataFieldByName($key);
            if($field) {
                $field->setRightTitle($value);
            }
        }
        return $fields;
    }

    /**
     * returns EcommerceCountries that this Distributor is responsible for.
     * return ArrayList
     */
    function getCountryList() {
        return EcommerceCountry::get()
            ->filter($this->IsDefault ? array("ID" => 0) : array("DistributorID" => $this->ID));
    }

    /**
     * link to edit the record
     * @param String | Null $action - e.g. edit
     * @return String
     */
    public function CMSEditLink($action = null) {
        return Controller::join_links(
            Director::baseURL(),
            "/admin/shop/".$this->ClassName."/EditForm/field/".$this->ClassName."/item/".$this->ID."/",
            $action
        );
    }

    /**
     * ensure there is one default Distributor.
     *
     */
    function onBeforeWrite() {
        parent::onBeforeWrite();
        if(Distributor::get()->filter(array("IsDefault" => 1))->count() == 0) {
            $this->IsDefault = 1;
        }
    }

    private static $_ran_after_write = false;
    /**
     * ensure there is one default Distributor.
     *
     */
    function onAfterWrite() {
        parent::onAfterWrite();
        if( ! self::$_ran_after_write) {
            self::$_ran_after_write = true;
            if( ! $this->PrimaryCountryID) {
                if($firstCountry = $this->Countries()->First()) {
                    $this->PrimaryCountryID = $firstCountry->ID;
                }
            }
            if($this->PrimaryCountryID) {
                if( ! $this->Countries()->byID($this->PrimaryCountryID)) {
                    $this->Countries()->add($this-PrimaryCountry());
                }
            }
        }
    }

    /**
     * @param Member $member
     * @return Boolean
     */
    function canDelete($member = null) {
        return $this->IsDefault ? false : parent::canEdit($member);
    }

    function requireDefaultRecords() {
        parent::RequireDefaultRecords();
        $distributorGroup = self::get_distributor_group();
        $distributorPermissionCode = "distributors";
        if(!$distributorGroup) {
            $distributorGroup = new Group();
            $distributorGroup->Code = "distributors";
            $distributorGroup->Title = "distributors";
            $distributorGroup->write();
            Permission::grant( $distributorGroup->ID, $distributorPermissionCode);
            DB::alteration_message('Distributor Group created',"created");
        }
        elseif(DB::query("SELECT * FROM \"Permission\" WHERE \"GroupID\" = '".$distributorGroup->ID."' AND \"Code\" LIKE '".$distributorPermissionCode."'")->numRecords() == 0 ) {
            Permission::grant($distributorGroup->ID, $distributorPermissionCode);
            DB::alteration_message('Distributor group permissions granted',"created");
        }
        $distributorGroup = self::get_distributor_group();
        if(!$distributorGroup) {
            user_error("could not create user group");
        }
        else {
            DB::alteration_message('distributor group is ready for use',"created");
        }
        $distributors = Distributor::get();
        if($distributors && $distributors->count()) {
            foreach($distributors as $distributor) {
                if($distributor->Email) {
                    $distributorMember = Member::get()
                        ->filter(array("Email" => $distributor->Email))
                        ->First();
                    if(!$distributorMember) {
                        $distributorMember = new Member();
                        $distributorMember->Email = $distributor->Email;
                        //$distributorMember->SetPassword = substr(session_id, 0, 8);
                    }
                    $distributorMember->FirstName = "Distributor";
                    $distributorMember->Surname = $distributor->Name;
                    $distributorMember->write();
                    $distributorMember->addToGroupByCode($distributorGroup->Code);
                    $distributorMember->write();
                    DB::alteration_message('distributor member '.$distributorMember->Surname.' is ready for use',"created");
                }
            }
        }
    }

    /**
     * @return DataObject (Group)
     **/
    public static function get_distributor_group() {
        $distributorCode = "distributors";
        $distributorName = "Distributors";
        return Group::get()
            ->where("\"Code\" = '".$distributorCode."' OR \"Title\" = '".$distributorName."'")
            ->First();
    }


    /**
     * @var string
     */
    private static $distributor_permission_code = "distributors";

    /**
     * {@inheritdoc}
     */
    public function providePermissions()
    {
        return array(
            $perms[Config::inst()->get('Distributor', 'distributor_permission_code')] = array(
                'name' => 'Distributors',
                'category' => 'E-commerce',
                'help' => 'Distribuor access to relevant products and sales data.',
                'sort' => 98,
            )
        );
    }
}
