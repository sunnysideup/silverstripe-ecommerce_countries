<?php

class CountryPrice_OrderStatusLog_AND_OrderAtribute extends DataExtension
{
    public function canCreate($member = null)
    {
        return true;
    }
    public function canView($member = null)
    {
        if (! $this->owner->ID) {
            return true;
        } elseif ($member && $member->DistributorID) {
            if ($order = $this->owner->Order()) {
                $distributor = $order->Distributor();
                if ($distributor) {
                    if ($distributor->ID == $member->DistributorID) {
                        return true;
                    }
                }
            }
        }
    }

    public function canEdit($member = null)
    {
        return $this->canView($member);
    }
}
