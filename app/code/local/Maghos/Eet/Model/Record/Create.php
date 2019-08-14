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
        $helper  = Mage::helper('eet');
        $storeId = $order->getStoreId();
        $record  = Mage::getModel('eet/record');

        if (!$helper->isActive($storeId) || !$helper->isValidOrder($order)) {
            return $record;
        }

        $record->loadByOrderId($order->getId(), $isRefund);

        if ($record->getId()) {
            return $record;
        }

        $key      = $helper->getCertificateKey($storeId);
        $pem      = $helper->getCertificatePem($storeId);
        $vat      = $helper->getVatId($storeId);
        $shop     = $helper->getShopId($storeId);
        $checkout = $helper->getCheckoutId($storeId);
        $eetMode  = $helper->getMode($storeId);

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

        $receipt  = $this->_createReceipt($record->getId(), $order, $isRefund, false);
        $response = $this->_sendReceipt($receipt, $storeId);

        if ($response) {
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

        $helper  = Mage::helper('eet');
        $storeId = $order->getStoreId();

        if (!$helper->isActive($storeId) || !$helper->isValidOrder($order)) {
            return $record;
        }

        $key = $helper->getCertificateKey($storeId);
        $pem = $helper->getCertificatePem($storeId);


        if (!$key || !$pem) {
            throw new Exception($helper->__('EET general settings are not configured'));
        }

        $receipt  = $this->_createReceipt($record->getId(), $order, $record->getIsRefund(), true);
        $response = $this->_sendReceipt($receipt, $storeId);

        if ($response) {
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
        $helper  = Mage::helper('eet');
        $storeId = $order->getStoreId();

        $vat      = $helper->getVatId($storeId);
        $shop     = $helper->getShopId($storeId);
        $checkout = $helper->getCheckoutId($storeId);
        $vatMode  = $helper->getVatMode($storeId);
        $eetMode  = $helper->getMode($storeId);

        if (!$vat || !$shop || !$checkout) {
            throw new Exception($helper->__('EET general settings are not configured'));
        }

        $receipt                = new EET_Receipt();
        $receipt->vat_id        = $vat;
        $receipt->shop_id       = $shop;
        $receipt->checkout_id   = $checkout;
        $receipt->id            = $recordId;
        $receipt->date          = time();
        $receipt->total         = ($isRefund ? -1 : 1) * $order->getGrandTotal();
        $receipt->first_attempt = $isRetry;
        $receipt->mode          = ($eetMode == Maghos_Eet_Helper_Data::EET_MODE_SIMPLE) ? EET_Receipt::MODE_SIMPLE : EET_Receipt::MODE_NORMAL;

        if ($vatMode == Maghos_Eet_Helper_Data::VAT_MODE_PAY) {
            $this->_setVatData($receipt, $order, $isRefund);
        }

        return $receipt;
    }

    /**
     *
     *
     * @param EET_Receipt $receipt
     * @param integer $storeId
     * @throws Exception
     * @return array|null
     */
    protected function _sendReceipt($receipt, $storeId)
    {
        $helper = Mage::helper('eet');

        $key  = $helper->getCertificateKey($storeId);
        $pem  = $helper->getCertificatePem($storeId);
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
        $helper     = Mage::helper('eet');
        $storeId    = $order->getStoreId();
        $taxClasses = $helper->getTaxClasses($storeId);

        foreach ($order->getAllItems() as $item) {
            $percent    = $item->getTaxPercent();
            $percentKey = intval($percent);

            if (!isset($taxClasses[$percentKey])) {
                continue;
            }

            $total    = $item->getBaseRowTotalInclTax();
            $discount = $item->getBaseDiscountAmount();

            $total -= $discount;
            $base = $total - $item->getBaseTaxAmount();

            $taxClasses[$percentKey]['base'] += $base;
            $taxClasses[$percentKey]['tax'] += $item->getBaseTaxAmount();
            $taxClasses[$percentKey]['total'] += $total;
        }

        if ($helper->getShippingTax($storeId) !== Maghos_Eet_Model_Record::SHIPPING_TAX_EXCLUDE) {
            $total    = $order->getBaseShippingInclTax();
            $discount = $order->getBaseShippingDiscountAmount();
            $total -= $discount;
            $base = $total - $order->getBaseShippingTaxAmount();

            $taxClasses[$helper->getShippingTax($storeId)]['base'] += $base;
            $taxClasses[$helper->getShippingTax($storeId)]['tax'] += $order->getBaseShippingTaxAmount();
            $taxClasses[$helper->getShippingTax($storeId)]['total'] += $total;
        }

        $sign = ($isRefund ? -1 : 1);
        foreach ($taxClasses as $taxKey => $taxClass) {
            $receipt->setTax($taxKey, $sign * $taxClass['base'], $sign * $taxClass['tax'], $sign * $taxClass['total']);
        }
    }
}
