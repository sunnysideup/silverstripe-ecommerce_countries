<?php

/**
 * adds a member to a distributor
 *
 */

class Distributor_MemberDOD extends DataExtension {

    private static $has_one = array('Distributor' => 'Distributor');

    function updateCMSFields(FieldList $fields) {
        $distributors = Distributor::get()->map('ID', 'Name')->toArray();
        $fields->addFieldToTab('Root.Distributor', new DropdownField('DistributorID', 'Distributor', $distributors, '', null, '-- Select --'));
    }
}
