<?php
/**
 * @package     Gemgento_Globalcollect
 * @copyright   Copyright © 2014 Gemgento LLC
 */

/**
 * Global Collect payment module API
 *
 */
class Gemgento_Globalcollect_Model_Api extends Smile_Globalcollect_Model_Api
{
    
    /**
     * Initialize request for account
     *
     *
     * @param string|int $account account name or merchant id
     * @param string $version
     * @return Varien_Simplexml_Element
     */
    protected function _initRequest($store_id = null)
    {
        $request = new Varien_Simplexml_Element('<XML><REQUEST></REQUEST></XML>');

        if ($store_id !== NULL){
            $merchantId = Mage::getStoreConfig('payment/globalcollect/merchant_id', $store_id);
        } else {
            $merchantId = Mage::getStoreConfig('payment/globalcollect/merchant_id');
        }
        

        $this->_addDataToRequest($request, 'META/MERCHANTID', $merchantId);
        $this->_addDataToRequest($request, 'META/IPADDRESS', $this->getServerIp());
        return $request;
    }

    /**
     * add data to INSERT_ORDERWITHPAYMENT request
     *
     * @param Varien_Simplexml_Element $request
     * @param Mage_Sales_Model_Order $sourceObject
     * @return Smile_Globalcollect_Model_Api
     */
    protected function _buildInsertOrderwithpayment($request,  $sourceObject)
    {
        $this->_addDataToRequest($request, 'PARAMS/ORDER/ORDERID', $sourceObject->getIncrementId());
        $this->_addDataToRequest($request, 'PARAMS/ORDER/AMOUNT', (int) round($sourceObject->getBaseGrandTotal() * 100));
        if ($sourceObject->getShouldSaveCC()) {
            $this->_addDataToRequest($request, 'PARAMS/ORDER/ORDERTYPE', self::ORDER_TYPE_RECURRING);
        }
        $this->_addDataToRequest($request, 'PARAMS/ORDER/CURRENCYCODE', $sourceObject->getBaseCurrencyCode());

        Mage::app()->getLocale()->emulate($sourceObject->getStoreId());
        $language = Mage::app()->getLocale()->getLocale()->getLanguage();
        Mage::app()->getLocale()->revert();
        $this->_addDataToRequest($request, 'PARAMS/ORDER/LANGUAGECODE', $language);
        $this->_addDataToRequest($request, 'PARAMS/ORDER/LANGUAGE', $language);

        $this->_addDataToRequest($request, 'PARAMS/ORDER/COUNTRYCODE', strtoupper($sourceObject->getBillingAddress()->getCountry()));
        $this->_addDataToRequest($request, 'PARAMS/ORDER/MERCHANTREFERENCE', $sourceObject->getPayment()->getMethodInstance()->getMerchantReference());
        
        // set the customer's ip address from the remote_ip attribute if it is available
        $remoteIp = $sourceObject->getRemoteIp();
         
        if ($remoteIp == null) {
           $remoteIp = Mage::helper('core/http')->getRemoteAddr(); 
        }
         
        $this->_addDataToRequest($request, 'PARAMS/ORDER/IPADDRESSCUSTOMER', $remoteIp);

        $address = $sourceObject->getBillingAddress();

        if ($customerId = $sourceObject->getCustomerId()) {
            $this->_addDataToRequest($request, 'PARAMS/ORDER/CUSTOMERID', $customerId);
        }

        $this->_addAddressToRequest($request, $address, 'PARAMS/ORDER');

        $shippingAddress = $sourceObject->getShippingAddress();
        $this->_addShippingAddressToRequest($request, $shippingAddress, 'PARAMS/ORDER');

        if ($field = $sourceObject->getCustomerTaxvat()) {
            $this->_addDataToRequest($request, 'PARAMS/ORDER/VATNUMBER', $field);
        }
        if ($field = $sourceObject->getCustomerPrefix()) {
            $this->_addDataToRequest($request, 'PARAMS/ORDER/TITLE', $field);
        }

        // ORDER ITEMS
        foreach ($sourceObject->getAllVisibleItems() as $i => $item) {
            $node = $this->_addNode($request, 'PARAMS/ORDERLINES/ORDERLINE');

            $node->LINENUMBER = $i+1;
            $node->INVOICELINEDATA = $item->getName().' '.$item->getQtyOrdered().' '.$item->getPrice();
            $node->LINEAMOUNT = round($item->getRowTotalInclTax()*100);
        }

        if ($sourceObject->getTotals()) {
            $i++;
            foreach ($sourceObject->getTotals() as $total) {
                if ($total['code'] == 'subtotal' || $total['code'] == 'grand_total') {
                    continue;
                }
                $node = $this->_addNode($request, 'PARAMS/ORDERLINES/ORDERLINE');
                $node->LINENUMBER = ++$i;
                $node->INVOICELINEDATA = $total['title'];
                $node->LINEAMOUNT = round($total['value'] * 100);
            }
        } else {
            $i++;
            if ($sourceObject->getShippingAmount()) {
                $node = $this->_addNode($request, 'PARAMS/ORDERLINES/ORDERLINE');
                $node->LINENUMBER = ++$i;
                $node->INVOICELINEDATA = Mage::helper('globalcollect')->__('Shipping & handling');
                $node->LINEAMOUNT = round($sourceObject->getShippingAmount()*100);
            }

            if ($sourceObject->getTaxAmount()) {
                $node = $this->_addNode($request, 'PARAMS/ORDERLINES/ORDERLINE');
                $node->LINENUMBER = ++$i;
                $node->INVOICELINEDATA = Mage::helper('globalcollect')->__('Tax');
                $node->LINEAMOUNT = round($sourceObject->getTaxAmount()*100);
            }

            if ($sourceObject->getDiscountAmount()) {
                if ($this->getSource()->getDiscountDescription()) {
                    $discountLabel = $this->helper('globalcollect')->__('Discount (%s)', $this->getSource()->getDiscountDescription());
                } else {
                    $discountLabel = $this->helper('globalcollect')->__('Discount');
                }
                $node = $this->_addNode($request, 'PARAMS/ORDERLINES/ORDERLINE');
                $node->LINENUMBER = ++$i;
                $node->INVOICELINEDATA = $discountLabel;
                $node->LINEAMOUNT = round($sourceObject->getDiscountAmount()*100);
            }
        }


        // PAYMENT
         $this->_buildDoPayment($request,  $sourceObject, $language);

        return $this;
    }
    
    /**
     * Call Global Collect xml api request
     *
     * @param string $action
     * @param string $account
     * @param Varien_Object $object
     * @param array $options should be an array with keys as name of variable
     * @return Varien_Simplexml_Element|boolean
     */
    public function call($action, $object, $options = array())
    {
        $gatewayUrl = Mage::getStoreConfig('payment/globalcollect/gateway_url');
        if (!$gatewayUrl) {
            return false;
        }

        $request = $this->_initRequest($object->getStoreId());

        $this->_buildRequest($request, $action, $object, $options);

        $rawResponse = $this->_postRequest($gatewayUrl, $request);

        if (strpos($rawResponse, '<XML>') === false || strpos($rawResponse, '</XML>') === false) {
            return false;
        }

        $xmlResponse = simplexml_load_string($rawResponse, 'Varien_Simplexml_Element');

        if ($this->_getConfig()->isDebug()) {
            Mage::log($action, 0, "global_collect.log");
            Mage::log($request->asNiceXml(), 0, "global_collect.log");
            Mage::log(var_export($xmlResponse->descend('REQUEST/RESPONSE'), true), 0, "global_collect.log");
        }

        return $xmlResponse;
    }
    
    /**
     * Get payment tokens for a customer.
     * 
     * @param int $customerId Customer Id
     * @return array
     */
    public function tokens($customerId) {
        $result = array();
        $customer = Mage::getModel('customer/customer')->load($customerId);       
        $tokens = Mage::getModel('globalcollect/token')->getSavedCards($customer);

        foreach($tokens as $token) {
            $result[] = array(
                'token_id' => $token->getId(),
                'customer_id' => $customerId,
                'token' => $token->getToken(),
                'cc_number' => $token->getCcNumber(),
                'expire_date' => $token->getExpireDate(),
                'payment_product_id' => $token->getPaymentProductId(),
                'effort_id' => $token->getEffortId()
            );
        }
        
        return $result;
    }
}
