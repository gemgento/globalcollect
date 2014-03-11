<?php
/**
 * Disclaimer
 * All trademarks, service marks and trade names referenced in this material
 * are the property of their respective owners This software is not intended
 * to be a complete solution of all applicable rules, policies and procedures.
 * The matters referenced are subject to change from time to time, and
 * individual circumstances may vary. Global Collect Services B.V. shall not
 * be responsible for any inaccurate or incomplete coding.
 *
 * Global Collect Services B.V. has given extensive attention to the quality
 * of the software but makes no warranties or representations about the accuracy
 * or completeness of it. Neither Global Collect Services B.V. nor any of its
 * affiliates shall be liable for any costs, losses and/or damages arising out
 * of access to or use of this software. Because of the complexity of the process
 * and the right of Banks to alter conditions, this software can only serve
 * as a quick-start in development is subject to further modifications.
 *
 * The Magento extension was developed as a generic solution.
 * In the event that the cartridge is modified by a user in any way,
 * Global Collect Services B.V. shall not be responsible for any damages that
 * are caused by the modified extension. Global Collect Services B.V. makes
 * no warranties or representations about the use or operation of the extension.
 * Neither Global Collect Services B.V. nor any of its affiliates shall be
 * liable for any costs, losses and/or damages arising out of access to
 * or use of the extension.
 *
 * Suggestions
 * Suggestions regarding the extension are welcome and may be forwarded to
 * global.partnerships@globalcollect.com
 *
 * @package     Smile_Globalcollect
 * @copyright   Copyright © 2012 Global Collect Services B.V.
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
