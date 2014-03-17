<?php

class Gemgento_Globalcollect_Checkout_Model_Cart_Payment_Api_V2 extends Gemgento_Globalcollect_Checkout_Model_Cart_Payment_Api {
    
    protected function _preparePaymentData($data) {
        if (null !== ($_data = get_object_vars($data))) {
            return parent::_preparePaymentData($_data);
        }

        return array();
    }

}
