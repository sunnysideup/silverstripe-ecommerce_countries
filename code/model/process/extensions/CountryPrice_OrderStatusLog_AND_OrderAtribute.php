<?php

class CountryPrice_OrderStatusLog_AND_OrderAtribute extends DataExtension
{
    function canCreate($member = null)
    {
        return true;
    }
    function canView($member = null)
    {
        if( ! $this->owner->ID) {
            return true;
        }
        elseif($member && $member->DistributorID) {
            if($order = $this->owner->Order()) {
                $distributor = $order->Distributor();
                if($distributor) {
                    if($distributor->ID == $member->DistributorID) {
                        return true;
                    }
                }
            }
        }
    }

    function canEdit($member = null)
    {
        return $this->canView($member);
    }
}
