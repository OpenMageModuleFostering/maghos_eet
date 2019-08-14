<?php
 /**
  * Magento
  *
  * NOTICE OF LICENSE
  *
  * This source file is subject to the Open Software License (OSL 3.0)
  * that is bundled with this package in the file LICENSE.txt.
  * It is also available through the world-wide-web at this URL:
  * http://opensource.org/licenses/osl-3.0.php
  * If you did not receive a copy of the license and are unable to
  * obtain it through the world-wide-web, please send an email
  * to license@magentocommerce.com so we can send you a copy immediately.
  *
  * @category    Maghos
  * @package     Maghos_Eet
  * @copyright   Copyright (c) 2017 Maghos.com  (http://maghos.com)
  * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
  */
class Maghos_Eet_Model_Record_Create
{

    /**
     * Create new record from invoice model
     *
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @return bool
     * @throws Exception
     */
    public function fromInvoice($invoice)
    {
        return $this->fromOrder($invoice->getOrder());
    }
    
    /**
     * Create new record from order model
     *
     * @param Mage_Sales_Model_Order $order
     * @param bool $isRefund
     * @return Maghos_Eet_Model_Record
     * @throws Exception
     */
    public function fromOrder($order, $isRefund = false)
    {
        $helper = Mage::helper('eet');
        $storeId = $order->getStoreId();
        $record = Mage::getModel('eet/record');

        if (!$helper->isActive($storeId) || !$helper->isValidOrder($order)) {
            return $record;
        }
        
        $record->loadByOrderId($order->getId(), $isRefund);
        
        if($record->getId()){
            return $record;
        }

        $key = $helper->getCertificateKey($storeId);
        $pem = $helper->getCertificatePem($storeId);
        $vat = $helper->getVatId($storeId);
        $shop = $helper->getShopId($storeId);
        $checkout = $helper->getCheckoutId($storeId);
        $eetMode = $helper->getMode($storeId);

        if (!$key || !$pem || !$vat || !$shop || !$checkout) {
            throw new Exception($helper->__('EET general settings are not configured'));
        }

        $record->setOrderId($order->getId());
        $record->setStatus(Maghos_Eet_Model_Record::STATUS_NEW);
        $record->setKey(null);
        $record->setCode(null);
        $record->setIsRefund($isRefund);
        $record->setVatId($vat);
        $record->setShopId($shop);
        $record->setCheckoutId($checkout);
        $record->setMode($eetMode);
        $record->setCreatedAt(Mage::getModel('core/date')->gmtDate('Y-m-d H:i:s'));
        $record->save();
        
        $receipt = $this->_createReceipt($record->getId(), $order, $isRefund, false);
        $response = $this->_sendReceipt($receipt, $storeId);
        
        if($response) {
            $record->setKey($response['fik']);
            $record->setCode($response['bkp']);
            $record->setStatus(Maghos_Eet_Model_Record::STATUS_SEND);
        } else {
            $record->setStatus(Maghos_Eet_Model_Record::STATUS_ERROR);
            $record->setKey(null);
            $record->setCode(null);
        }
        
        $record->save();
            
        return $record;
    }
    
    /**
     * Retry sending receipt
     *
     * @param Maghos_Eet_Model_Record
     * @return Maghos_Eet_Model_Record
     * @throws Exception
     */
    public function retry($record)
    {
        if ($record->getStatus() == Maghos_Eet_Model_Record::STATUS_SEND) {
            return $record;
        }
        
        $order = $record->getOrder();
        if (!$order) {
            return $record;
        }
        
        $helper = Mage::helper('eet');
        $storeId = $order->getStoreId();
            
        if (!$helper->isActive($storeId) || !$helper->isValidOrder($order)) {
            return $record;
        }

        $key = $helper->getCertificateKey($storeId);
        $pem = $helper->getCertificatePem($storeId);


        if (!$key || !$pem) {
            throw new Exception($helper->__('EET general settings are not configured'));
        }
        
        $receipt = $this->_createReceipt($record->getId(), $order, $record->getIsRefund(), true);
        $response = $this->_sendReceipt($receipt, $storeId);
        
        if($response) {
            $record->setKey($response['fik']);
            $record->setCode($response['bkp']);
            $record->setStatus(Maghos_Eet_Model_Record::STATUS_SEND);
        } else {
            $record->setStatus(Maghos_Eet_Model_Record::STATUS_ERROR);
            $record->setKey(null);
            $record->setCode(null);
        }
        
        $record->save();
            
        return $record;
    }

    /**
     * Create EET receipt object from order data
     *
     * @param integer $recordId
     * @param Mage_Sales_Model_Order $order
     * @param bool $isRefund
     * @param bool $isRetry
     * @return EET_Receipt $receipt
     * @throws Exception
     */
    protected function _createReceipt($recordId, $order, $isRefund = false, $isRetry = false)
    {
        $helper = Mage::helper('eet');
        $storeId = $order->getStoreId();

        $vat = $helper->getVatId($storeId);
        $shop = $helper->getShopId($storeId);
        $checkout = $helper->getCheckoutId($storeId);
        $vatMode = $helper->getVatMode($storeId);
        $eetMode = $helper->getMode($storeId);

        if (!$vat || !$shop || !$checkout) {
            throw new Exception($helper->__('EET general settings are not configured'));
        }

        $receipt = new EET_Receipt();
        $receipt->vat_id = $vat;
        $receipt->shop_id = $shop;
        $receipt->checkout_id = $checkout;
        $receipt->id = $recordId;
        $receipt->date = time();
        $receipt->total = ($isRefund ? -1 : 1) * $order->getGrandTotal();
        $receipt->first_attempt = $isRetry;
        $receipt->mode = ($eetMode == Maghos_Eet_Helper_Data::EET_MODE_SIMPLE) ? EET_Receipt::MODE_SIMPLE : EET_Receipt::MODE_NORMAL;

        if ($vatMode == Maghos_Eet_Helper_Data::VAT_MODE_PAY) {
            $this->_setVatData($receipt, $order, $isRefund);
        }

        return $receipt;
    }

    /**
     * Send EET receipt
     *
     * @param EET_Receipt $receipt
     * @param integer $storeId
     * @return array|null
     */
    protected function _sendReceipt($receipt, $storeId)
    {
        $helper = Mage::helper('eet');

        $key = $helper->getCertificateKey($storeId);
        $pem = $helper->getCertificatePem($storeId);
        $test = $helper->isTestMode($storeId);

        if (!$key || !$pem) {
            throw new Exception($helper->__('EET general settings are not configured'));
        }

        $dispatcher = new EET_Dispatcher($key, $pem, $test ? EET_Dispatcher::SERVICE_PLAYGROUND : EET_Dispatcher::SERVICE_PRODUCTION);
        try {
            $response = $dispatcher->send($receipt);
        } catch (Exception $ex) {
            return null;
        }

        return $response;
    }

    /**
     * Add VAT data to the EET receipt
     *
     * @param EET_Receipt $receipt
     * @param Mage_Sales_Model_Order $order
     * @param bool $isRefund
     * @return void
     * @throws Exception
     */
    protected function _setVatData($receipt, $order, $isRefund = false)
    {
        $helper = Mage::helper('eet');
        $storeId = $order->getStoreId();
        $tax = $helper->getTaxClasses($storeId);

        $zero = array(0, 0);
        $base = array(0, 0);
        $lower1 = array(0, 0);
        $lower2 = array(0, 0);

        foreach ($order->getAllItems() as $item) {
            $taxClass = $item->getProduct()->getTaxClassId();
            if ($taxClass == 0) {
                $zero[0]+= $item->getRowTotal() - $item->getTaxAmount();
                $zero[1]+= $item->getTaxAmount();
            } else if ($taxClass == $tax['base']) {
                $base[0]+= $item->getRowTotal() - $item->getTaxAmount();
                $base[1]+= $item->getTaxAmount();
            } else if ($taxClass == $tax['lower'][0]) {
                $lower1[0]+= $item->getRowTotal() - $item->getTaxAmount();
                $lower1[1]+= $item->getTaxAmount();
            } else if ($taxClass == $tax['lower'][1]) {
                $lower2[0]+= $item->getRowTotal() - $item->getTaxAmount();
                $lower2[1]+= $item->getTaxAmount();
            } else {
                throw new Exception($helper->__('Tax class form product %s is not defined', $item->getSku()));
            }
        }

        $sign = ($isRefund ? -1 : 1);
        
        $receipt->setTax(EET_Receipt::TAX_ZERO, $sign * $zero[0], $sign * $zero[1]);
        $receipt->setTax(EET_Receipt::TAX_BASE, $sign * $base[0], $sign * $base[1]);
        $receipt->setTax(EET_Receipt::TAX_LOWER1, $sign * $lower1[0], $sign * $lower1[1]);
        $receipt->setTax(EET_Receipt::TAX_LOWER2, $sign * $lower2[0], $sign * $lower2[1]);
    }
}
