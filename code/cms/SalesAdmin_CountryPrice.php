<?php

class SalesAdmin_CountryPrice extends Extension
{
    function updateGetList($list) {
        $distributor = 0;
        $member = Member::currentUser();
        if ($member) {
            $distributorID = $member->DistributorID;
        }
        if($distributorID) {
            $list = $list->filter(array('DistributorID' => $distributorID));
            return $list;
        }
        return null;
    }
}
