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
class Maghos_Eet_Model_Record extends Mage_Core_Model_Abstract
{

    const STATUS_NEW = 0;
    const STATUS_ERROR = 1;
    const STATUS_SEND = 2;

    const SHIPPING_TAX_EXCLUDE = -1;
    
    /** @var Mage_Sales_Model_Order */
    protected $_order;
    
    protected function _construct()
    {
        $this->_init('eet/record');
    }
    
    /**
     * Load record or refund record by order id
     *
     * @param integer $orderId
     * @param bool $isRefund
     * @return Maghos_Eet_Model_Record
     * @throws Exception
     */
    public function loadByOrderId($orderId, $isRefund = false)
    {
        $this->setData($this->getResource()->loadByOrderId($orderId, $isRefund));
        return $this;
    }

    /**
     * Get Magento Order model
     *
     * @return Mage_Sales_Model_Order|null
     * @throws Exception
     */
    public function getOrder()
    {
        if (!$this->getOrderId()) {
            return null;
        }
        
        if (!$this->_order) {
            $this->_order = Mage::getModel('sales/order');
            $this->_order->load($this->getOrderId());
        }

        return $this->_order;
    }
}
