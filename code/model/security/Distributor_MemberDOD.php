<?php

/**
 * adds a member to a distributor
 *
 */

class Distributor_MemberDOD extends DataExtension
{
    private static $has_one = array(
        'Distributor' => 'Distributor'
    );

    public function updateCMSFields(FieldList $fields)
    {
        $distributors = Distributor::get()->map('ID', 'Name')->toArray();
        $fields->addFieldToTab('Root.Distributor', new DropdownField('DistributorID', _t('Distributor.SINGULAR_NAME', 'Distributor'), $distributors, '', null, '-- Select --'));
    }

    public function onAfterWrite()
    {
        if ($this->owner->DistributorID) {
            $distributor = $this->owner->Distributor();
            if ($distributor && $distributor->exists()) {
                $group = Group::get()->filter(array("Code" => Config::inst()->get('Distributor', 'distributor_permission_code')))->first();
                if ($group) {
                    $this->owner->addToGroupByCode($group->Code);
                }
            }
        }
    }
}
