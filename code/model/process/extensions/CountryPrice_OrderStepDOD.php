<?php

/**
 * Adds individual country messages
 * for ordersteps
 *
 */

class CountryPrice_OrderStepDOD extends DataExtension
{
    private static $db = array(
        'SendEmailToDistributor' => 'Boolean'
    );

    private static $has_many = array(
        'EcommerceOrderStepCountryDatas' => 'EcommerceOrderStepCountryData',
    );

    private static $field_labels = array(
        'EcommerceOrderStepCountryDatas' => 'Regionalisation',
    );

    /**
     * Update Fields
     * @return FieldList
     */
    public function updateCMSFields(FieldList $fields)
    {
        $owner = $this->owner;
        $bccExplanation = _t(
            'CountryPrice_OrderStepDOD.BCC_EXPLANATION',
            '
                E-mails during this step (if any) will be copied to distributor?
                <br />These emails can be customised.
        ');
        $gridField = $fields->dataFieldByName('EcommerceOrderStepCountryDatas');
        $fields->removeFieldFromTab(
            'Root.EcommerceOrderStepCountryDatas',
            'EcommerceOrderStepCountryDatas'
        );
        $fields->addFieldsToTab(
            'Root.EcommerceOrderStepCountryDatas',
            array(
                CheckboxField::create(
                    'SendEmailToDistributor',
                    _t('CountryPrice_OrderStepDOD.SEND_EMAIL_TO_DISTRIBUTOR', 'BCC to Distributor')
                )->setDescription(
                    $bccExplanation
                ),
            )
        );
        $customisation = _t(
            'CountryPrice_OrderStepDOD.CUSTOMISATION_PER_COUNTRY',
            'Customisation per country');
        $gridField->setTitle($customisation);
        $fields->addFieldsToTab(
            'Root.EcommerceOrderStepCountryDatas',
            $gridField
        );
    }

}
