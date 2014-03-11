<?php
/**
 * @package     Gemgento_Globalcollect
 * @copyright   Copyright © 2014 Gemgento LLC
 */

/**
 * Global collect payment method abstract
 *
 */
abstract class Gemgento_Globalcollect_Model_Method_Abstract extends Smile_Globalcollect_Model_Method_Abstract
{
    
    /**
     * Assigns data to info object
     *
     * @param array $data
     * @return Smile_Globalcollect_Model_Method_Abstract
     * @throws Smile_Globalcollect_Exception_Validation
     */
    public function assignData($data)
    {
        parent::assignData($data);
        
        if (isset($data['cc_type']) && !isset($data['payment_product_id'])) {
            $data['payment_product_id'] = $data['cc_type']; 
        }

        if (isset($data['payment_product_id'])) {
            // in case of saved CC payment_product_id will be 'token_{token_id}'
            if(isset($data[$data['payment_product_id']])) {
                $token = Mage::getModel('globalcollect/token')->validate($data[$data['payment_product_id']]);

                $this->getInfoInstance()->setAdditionalInformation('token_id', $token->getId());
                $this->getInfoInstance()->setAdditionalInformation('payment_product_id', $token->getPaymentProductId());
                $this->getInfoInstance()->setAdditionalInformation('payment_method_id',
                        Mage::helper('globalcollect')->getCreditCardMethodId());
            } else {
                $this->getInfoInstance()->setAdditionalInformation('token_id', null);
                $paymentProduct = $this->getPaymentProducts()->getItemById($data['payment_product_id']);
                if (!$paymentProduct) {
                    Mage::throwException(Mage::helper('globalcollect')->__('Please select Payment Product'));
                }
                $this->getInfoInstance()->setAdditionalInformation('payment_product_id', $paymentProduct->getId());
                $this->getInfoInstance()->setAdditionalInformation('payment_method_id',
                        $paymentProduct->getPaymentMethodId());
            }
        } elseif (!$this->getInfoInstance()->getAdditionalInformation('payment_product_id')
                || !$this->getInfoInstance()->getAdditionalInformation('payment_method_id')
        ) {
            Mage::throwException(Mage::helper('globalcollect')->__('Please sekect Payment Product'));
        }
        if (isset($data['method_fields']) && is_array($data['method_fields'])) {
            $this->getInfoInstance()->setAdditionalInformation('method_fields', $data['method_fields']);
        }
        if (isset($data['save_cc_data']) && $data['save_cc_data']) {
            $this->getInfoInstance()->setAdditionalInformation('save_cc_data', true);
        } else {
            $this->getInfoInstance()->setAdditionalInformation('save_cc_data', false);
        }
        return $this;
    }
}
