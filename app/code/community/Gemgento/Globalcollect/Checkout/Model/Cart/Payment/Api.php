<?php

class Gemgento_Globalcollect_Checkout_Model_Cart_Payment_Api extends Gemgento_Checkout_Model_Cart_Payment_Api
{
    
    protected function _preparePaymentData($data)
    {
        if (!(is_array($data) && is_null($data[0]))) {
            return array();
        }
        
        if (strpos($data['cc_type'], 'token_') !== FALSE) {
            $id = explode('_', $data['cc_type']);
            
            $data[$data['cc_type']] = array(
                'id' => $id[1],
                'cc_exp_month' => $data['cc_exp_month'],
                'cc_exp_year' => $data['cc_exp_year']
            );
        }
        
        return $data;
    }

}
