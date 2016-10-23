<?php

/**
 * A country can only be sent goods to from 1 distributor + the default distributor which can send anywhere in the world.
 * The default distributor shows prices of the default currency.
 * Precondition : There is always a default distributor.
 */
class Distributor extends DataObject implements PermissionProvider
{
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
        'ReturnInformation' => 'Varchar(255)'
    );

    private static $has_one = array(
        'PrimaryCountry' => 'EcommerceCountry'
    );

    private static $has_many = array(
        'Countries' => 'EcommerceCountry',
        'Members' => 'Member',
        'Updates' => 'CountryPrice_DistributorManagementTool_Log'
    );

    private static $field_labels = array(
        'IsDefault' => 'Is Default Distributor?'
    );

    private static $field_labels_right = array(
        'IsDefault' => 'Use this only for the  distributor that is applicable when no other distributor applies (e.g. a country that does not have a distributor).',
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


    private static $singular_name = "Distributor";

    /**
     * Return the translated Singular name.
     *
     * @return string
     */
    public function i18n_singular_name()
    {
        return _t('Distributor.SINGULAR_NAME', 'Distributor');
    }


    private static $plural_name = "Distributors";

    /**
     * Return the translated Singular name.
     *
     * @return string
     */
    public function i18n_plural_name()
    {
        return _t('Distributor.PLURAL_NAME', 'Distributors');
    }


    /**
     * returns the Distributor for the country OR the default Distributor.
     * @param String $countryCode = the country code.
     *
     * @return Distributor
     */
    public static function get_one_for_country($countryCode = '')
    {
        $countryObject = CountryPrice_EcommerceCountry::get_real_country($countryCode);
        if ($countryObject) {
            $distributor = $countryObject->Distributor();
            if ($distributor && $distributor->exists()) {
                return $distributor;
            }
        }
        return Distributor::get()
            ->filter(array("IsDefault" => 1))
            ->First();
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fieldLabels = $this->FieldLabels();
        $fieldLabelsRight = $this->Config()->get("field_labels_right");
        $listOfCountriesCovered = EcommerceCountry::get()->exclude(array("DistributorID" => 0))->map("ID", "Title");
        //secondary for another country
        $fields->removeByName('Versions');
        if ($listOfCountriesCovered && $listOfCountriesCovered->count()) {
            $countryArray =  array(" -- please select --") + $listOfCountriesCovered->toArray();
            $fields->addFieldToTab("Root.CountryDetails", DropdownField::create("PrimaryCountryID", "Primary Country", $countryArray));
        } else {
            $fields->removeByName('PrimaryCountryID');
        }
        if ($this->IsDefault) {
            $fields->removeByName('Countries');
        } else {
            if (empty($this->ID)) {
                $id = 0;
            } else {
                $id = $this->ID;
            }
            if ($this->ID) {
                $config = GridFieldConfig_RelationEditor::create();
                $config->removeComponentsByType("GridFieldAddNewButton");
                $gridField = new GridField('pages', 'All pages', SiteTree::get(), $config);
                $countryField = new GridField(
                    'Countries',
                    'Countries',
                    $this->Countries(),
                    $config
                );
                $fields->addFieldToTab("Root.CountryDetails", $countryField);
                if ($this->Version > 1) {
                    $columns = array(
                        'Version' => 'Version',
                        'LastEdited' => 'Date',
                        'Name' => 'Name',
                        'IsDefault' => 'Default',
                        'Email' => 'Email'
                    );
                    $table = '<table class="versions"><thead><tr><th>' . implode('</th><th>', $columns) . '</th></tr></thead><tbody>';
                    $version = $this->Version - 1;
                    while ($version > 0) {
                        $versionDO = Versioned::get_version('Distributor', $this->ID, $version--);
                        $values = array();
                        foreach ($columns as $column => $title) {
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
        foreach ($fieldLabelsRight as $key => $value) {
            $field = $fields->dataFieldByName($key);
            if ($field) {
                $field->setRightTitle($value);
            }
        }
        $fields->removeFieldFromTab(
            'Root',
            'Countries'
        );
        return $fields;
    }

    /**
     * returns EcommerceCountries that this Distributor is responsible for.
     * return ArrayList
     */
    public function getCountryList()
    {
        $filter = $this->IsDefault ? array("ID" => 0) : array("DistributorID" => $this->ID);
        return EcommerceCountry::get()
            ->filter($array);
    }

    /**
     * link to edit the record
     * @param String | Null $action - e.g. edit
     * @return String
     */
    public function CMSEditLink($action = null)
    {
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
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (Distributor::get()->filter(array("IsDefault" => 1))->count() == 0) {
            $this->IsDefault = 1;
        }
        if ($this->PrimaryCountryID > 0 && EcommerceCountry::get()->byID(intval($this->PrimaryCountryID))) {
            $primaryCountry = $this->PrimaryCountry();
            if (! $this->Countries()->byID($this->PrimaryCountryID)) {
                $this->Countries()->add($primaryCountry);
            }
        } else {
            if ($firstCountry = $this->Countries()->First()) {
                self::$_ran_after_write = true;
                $this->PrimaryCountryID = $firstCountry->ID;
            }
        }
    }

    private static $_ran_after_write = false;
    /**
     * ensure there is one default Distributor.
     *
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();
        if (! self::$_ran_after_write) {
            self::$_ran_after_write = true;
            $this->setupUser();
        }
    }

    /**
     * @param Member $member
     * @return Boolean
     */
    public function canDelete($member = null)
    {
        return $this->IsDefault ? false : parent::canEdit($member);
    }

    public function requireDefaultRecords()
    {
        parent::RequireDefaultRecords();
        $distributorTitleSingular = _t('Distributor.SINGULAR_NAME', 'Distributor');
        $distributorTitlePlural = _t('Distributor.PLURAL_NAME', 'Distributors');
        $filter = array("Title" => $distributorTitleSingular);
        $role = PermissionRole::get()->filter($filter)->first();
        if (!$role) {
            $role = PermissionRole::create($filter);
            $role->write();
            DB::alteration_message("Creating ".$distributorTitleSingular." role", 'created');
        }
        $codes = array(
            'CMS_ACCESS_SalesAdmin',
            'CMS_ACCESS_SalesAdminExtras',
            'CMS_ACCESS_SalesAdmin_PROCESS'
        );
        foreach ($codes as $code) {
            $filter = array(
                "RoleID" => $role->ID,
                "Code" => $code
            );
            $code = PermissionRoleCode::get()->filter($filter)->first();
            if (!$code) {
                DB::alteration_message("Adding code to ".$distributorTitleSingular." role", 'created');
                $code = PermissionRoleCode::create($filter);
                $code->write();
            }
        }

        $distributorGroup = self::get_distributor_group();
        $distributorPermissionCode = Config::inst()->get('Distributor', 'distributor_permission_code');
        if (!$distributorGroup) {
            $distributorGroup = new Group();
            $distributorGroup->Code = $distributorPermissionCode;
            $distributorGroup->Title = $distributorTitlePlural;
            $distributorGroup->write();
            DB::alteration_message($distributorTitlePlural.' Group created', "created");
            Permission::grant($distributorGroup->ID, $distributorPermissionCode);
        } elseif (DB::query("SELECT * FROM \"Permission\" WHERE \"GroupID\" = '".$distributorGroup->ID."' AND \"Code\" LIKE '".$distributorPermissionCode."'")->numRecords() == 0) {
            Permission::grant($distributorGroup->ID, $distributorPermissionCode);
            DB::alteration_message($distributorTitlePlural.' group permissions granted', "created");
        }
        $distributorGroup->Roles()->add($role);
        $distributorGroup = self::get_distributor_group();
        if (!$distributorGroup) {
            user_error("could not create user group");
        } else {
            DB::alteration_message('distributor group is ready for use');
        }
        $distributors = Distributor::get();
        if ($distributors && $distributors->count()) {
            foreach ($distributors as $distributor) {
                $distributor->setupUser();
            }
        }
    }


    /**
     * @return DataObject (Group)
     **/
    public static function get_distributor_group()
    {
        $distributorPermissionCode = Config::inst()->get('Distributor', 'distributor_permission_code');
        return Group::get()
            ->where("\"Code\" = '".$distributorPermissionCode."'")
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
                'name' => _t('Distributor.PLURAL_NAME', 'Distributors'),
                'category' => 'E-commerce',
                'help' => _t('Distributor.SINGULAR_NAME', 'Distributor').' access to relevant products and sales data.',
                'sort' => 98,
            )
        );
    }

    public function setupUser()
    {
        $group = Group::get()->filter(array("Code" => $this->Config()->get('distributor_permission_code')))->first();
        if ($this->Email) {
            $member = Member::get()
                ->filter(array("Email" => $this->Email))
                ->First();
            if (!$member) {
                $member = new Member();
                $member->Email = $this->Email;
                //$thisMember->SetPassword = substr(session_id, 0, 8);
            }
            $member->FirstName = _t('Distributor.SINGULAR_NAME', 'Distributor') . ' For';
            $member->Surname = $this->Name;
            $member->DistributorID = $this->ID;
            $member->write();
            if ($group) {
                $member->addToGroupByCode($group->Code);
            }
            $member->write();
        }
        if ($group) {
            foreach ($this->Members() as $member) {
                $member->addToGroupByCode($group->Code);
            }
        }
    }
}
