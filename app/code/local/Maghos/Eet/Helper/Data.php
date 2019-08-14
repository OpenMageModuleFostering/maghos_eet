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
class Maghos_Eet_Helper_Data extends Mage_Core_Helper_Data
{

    const XML_GENERAL_ACTIVE = 'eet/general/active';
    const XML_GENERAL_VAT_ID = 'eet/general/vat';
    const XML_GENERAL_SHOP_ID = 'eet/general/shop';
    const XML_GENERAL_CHECKOUT_ID = 'eet/general/checkout';
    const XML_GENERAL_CERT_KEY = 'eet/general/cert_key_content';
    const XML_GENERAL_CERT_PEM = 'eet/general/cert_pem_content';
    const XML_GENERAL_TEST = 'eet/general/test';
    
    const XML_ADVANCED_MODE = 'eet/advanced/mode';
    const XML_ADVANCED_VAT_MODE = 'eet/advanced/vat';
    const XML_ADVANCED_TAX_BASE = 'eet/advanced/tax_base';
    const XML_ADVANCED_TAX_LOWER1 = 'eet/advanced/tax_base';
    const XML_ADVANCED_TAX_LOWER2 = 'eet/advanced/tax_base';
    
    const XML_ORDER_PAYMENT_METHODS = 'eet/order/payment_methods';
    const XML_ORDER_PAYMENT_CHECK = 'eet/order/payment_check';
    const XML_ORDER_SHIPPING_METHODS = 'eet/order/shipping_methods';
    const XML_ORDER_SHIPPING_CHECK = 'eet/order/shipping_check';
    
    const VAT_MODE_PAY = 1;
    const VAT_MODE_NO = 0;
    const EET_MODE_NORMAL = 0;
    const EET_MODE_SIMPLE = 1;

    /**
     * Is EET active
     *
     * @param integer|null $storeId
     * @return bool
     */
    public function isActive($storeId = null)
    {
        return (bool) Mage::getStoreConfig(self::XML_GENERAL_ACTIVE, $storeId);
    }

    /**
     * Is EET in test mode
     *
     * @param integer|null $storeId
     * @return bool
     */
    public function isTestMode($storeId = null)
    {
        return (bool) Mage::getStoreConfig(self::XML_GENERAL_TEST, $storeId);
    }

    /**
     * Get Certificate KEY content
     *
     * @param integer|null $storeId
     * @return string
     */
    public function getCertificateKey($storeId = null)
    {
        return Mage::getStoreConfig(self::XML_GENERAL_CERT_KEY, $storeId);
    }

    /**
     * Get Certificate PEM content
     *
     * @param integer|null $storeId
     * @return string
     */
    public function getCertificatePem($storeId = null)
    {
        return Mage::getStoreConfig(self::XML_GENERAL_CERT_PEM, $storeId);
    }

    /**
     * Get VAT id
     *
     * @param integer|null $storeId
     * @return string
     */
    public function getVatId($storeId = null)
    {
        return Mage::getStoreConfig(self::XML_GENERAL_VAT_ID, $storeId);
    }

    /**
     * Get Shop id
     *
     * @param integer|null $storeId
     * @return string
     */
    public function getShopId($storeId = null)
    {
        return Mage::getStoreConfig(self::XML_GENERAL_SHOP_ID, $storeId);
    }

    /**
     * Get Checkout id
     *
     * @param integer|null $storeId
     * @return string
     */
    public function getCheckoutId($storeId = null)
    {
        return Mage::getStoreConfig(self::XML_GENERAL_CHECKOUT_ID, $storeId);
    }

    /**
     * Get VAT mode
     *
     * @param integer|null $storeId
     * @return integer
     */
    public function getVatMode($storeId = null)
    {
        return (integer) Mage::getStoreConfig(self::XML_ADVANCED_VAT_MODE, $storeId);
    }

    /**
     * Get EET mode
     *
     * @param integer|null $storeId
     * @return integer
     */
    public function getMode($storeId = null)
    {
        return (integer) Mage::getStoreConfig(self::XML_ADVANCED_MODE, $storeId);
    }

    /**
     * Get payment method restrictions
     *
     * @param integer|null $storeId
     * @return array|null
     */
    public function getPaymentMethods($storeId = null)
    {
        if (Mage::getStoreConfig(self::XML_ORDER_PAYMENT_CHECK, $storeId)) {
            $methods = Mage::getStoreConfig(self::XML_ORDER_PAYMENT_METHODS, $storeId);
            return $methods ? explode(', ', $methods) : null;
        }

        return null;
    }

    /**
     * Get shipping method restrictions
     *
     * @param integer|null $storeId
     * @return array|null
     */
    public function getShippingMethods($storeId = null)
    {
        if (Mage::getStoreConfig(self::XML_ORDER_SHIPPING_CHECK, $storeId)) {
            $methods = Mage::getStoreConfig(self::XML_ORDER_SHIPPING_METHODS, $storeId);
            return $methods ? explode(', ', $methods) : null;
        }

        return null;
    }

    /**
     * Get EET mode
     *
     * @param integer|null $storeId
     * @return array
     */
    public function getTaxClasses($storeId = null)
    {
        $base = Mage::getStoreConfig(self::XML_ADVANCED_TAX_BASE, $storeId);
        $lower1 = Mage::getStoreConfig(self::XML_ADVANCED_TAX_LOWER1, $storeId);
        $lower2 = Mage::getStoreConfig(self::XML_ADVANCED_TAX_LOWER2, $storeId);

        return array('base' => $base, 'lower' => array($lower1, $lower2));
    }

    /**
     * Check if EET can be send for order
     *
     * @param Mage_Sales_Model_Order $order
     * @return bool
     */
    public function isValidOrder($order)
    {

        if (
            $order->getState() == Mage_Sales_Model_Order::STATE_CANCELED ||
            $order->getState() == Mage_Sales_Model_Order::STATE_CLOSED
        ) {
            return false;
        }

        if ($payment = $order->getPayment()) {
            $paymentMethod = $payment->getMethodInstance();
            $paymentMethods = $this->getPaymentMethods($order->getStoreId());
        } else {
            return false;
        }

        if ($paymentMethods && !in_array($paymentMethod, $paymentMethods)) {
            return false;
        }

        $shippingMethod = $order->getShippingMethod();
        $shippingMethods = $this->getShippingMethods($order->getStoreId());
        if ($shippingMethods && !in_array($shippingMethod, $shippingMethods)) {
            return false;
        }

        return true;
    }
}
