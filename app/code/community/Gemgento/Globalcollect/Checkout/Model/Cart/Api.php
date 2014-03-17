<?php

class Gemgento_Globalcollect_Checkout_Model_Cart_Api extends Gemgento_Checkout_Model_Cart_Api {

    protected function _preparePaymentData($data) {
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
            
            $data['token_id'] = $id[1];
        }

        return $data;
    }
    
    /**
     * Create an order from the shopping cart (quote)
     *
     * @param  $quoteId
     * @param  $store
     * @param  $paymentData array
     * @param  $agreements array
     * @return string
     */
    public function createOrder($quoteId, $store = null, $agreements = null, $paymentData = null, $remoteIp = null) {
        $requiredAgreements = Mage::helper('checkout')->getRequiredAgreementIds();
        
        if (!empty($requiredAgreements)) {
            $diff = array_diff($agreements, $requiredAgreements);
            if (!empty($diff)) {
                $this->_fault('required_agreements_are_not_all');
            }
        }

        $quote = $this->_getQuote($quoteId, $store);
        if ($quote->getIsMultiShipping()) {
            $this->_fault('invalid_checkout_type');
        }
        if ($quote->getCheckoutMethod() == Mage_Checkout_Model_Api_Resource_Customer::MODE_GUEST && !Mage::helper('checkout')->isAllowedGuestCheckout($quote, $quote->getStoreId())) {
            $this->_fault('guest_checkout_is_not_enabled');
        }
        // set payment data again so that credit cards can be processed since they are not stored
        if ($paymentData != null) {
            $paymentData = $this->_preparePaymentData($paymentData);
            $quote->getPayment()->importData($paymentData);    
        }
        
        // set the customers ip 
        if ($remoteIp == null) {
            $remoteIp = Mage::helper('core/http')->getRemoteAddr();
        }
        
        $quote->setRemoteIp($remoteIp)->save();

        /** @var $customerResource Mage_Checkout_Model_Api_Resource_Customer */
        $customerResource = Mage::getModel("checkout/api_resource_customer");
        $isNewCustomer = $customerResource->prepareCustomerForQuote($quote);

        try {
            $quote->collectTotals();
            /** @var $service Mage_Sales_Model_Service_Quote */
            $service = Mage::getModel('sales/service_quote', $quote);
            $service->submitAll();

            if ($isNewCustomer) {
                try {
                    $customerResource->involveNewCustomer($quote);
                } catch (Exception $e) {
                    Mage::logException($e);
                }
            }
            
            $order = $service->getOrder();
            if ($order) {
                Mage::dispatchEvent('checkout_type_onepage_save_order_after', array('order' => $order, 'quote' => $quote));

                try {
                    $order->sendNewOrderEmail();
                } catch (Exception $e) {
                    Mage::logException($e);
                }
            }

            Mage::dispatchEvent(
                    'checkout_submit_all_after', array('order' => $order, 'quote' => $quote)
            );
        } catch (Mage_Core_Exception $e) {
            $this->_fault('create_order_fault', $e->getMessage());
        }

        return $order->getIncrementId();
    }

}
