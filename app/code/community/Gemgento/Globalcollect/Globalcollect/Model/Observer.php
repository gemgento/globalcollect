<?php

/**
 * Global Collect observer
 *
 */
class Gemgento_Globalcollect_Globalcollect_Model_Observer extends Smile_Globalcollect_Model_Observer {

    /**
     * When shipment is added to an order, settle the payment if it is 
     * authorized.
     * 
     * @param \Mage_Sales_Model_Observer $observer
     */
    public function settlePayment($observer) {
        $shipment = $observer->getEvent()->getShipment();
        $order = $shipment->getOrder();
        $methodInstance = $order->getPayment()->getMethodInstance();

        if ($methodInstance instanceof Smile_GlobalCollect_Model_Method_Abstract && $methodInstance->isAuthorized()) {
            try {
                $methodInstance->processSetPayment();
                $order->save();
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }
    }

}