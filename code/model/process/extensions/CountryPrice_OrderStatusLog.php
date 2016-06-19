<?php

class CountryPrice_OrderStatusLog extends DataExtension
{
    function canCreate($member = null)
    {
        if($member && $member->DistributorID) {
            return true;
        }
    }
    function canView($member = null)
    {
        return $this->canEdit($member);
    }

    function canEdit($member = null)
    {
        return true;
        if($order = $this->owner->Order()) {
            if($order->exists()) {
                return $order->canEdit($member);
            }
        }
        return $this->canCreate($member);
    }
}
