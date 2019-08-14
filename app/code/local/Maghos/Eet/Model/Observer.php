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
class Maghos_Eet_Model_Observer
{

    /**
     * Handler for core_block_abstract_prepare_layout_before event
     *
     * @param Varien_Event_Observer $observer
     * @return void
     * @throws Exception
     */
    public function addMassAction($observer)
    {
        /** @var Mage_Core_Block_Abstract */
        $block = $observer->getEvent()->getBlock();

        $helper = Mage::helper('eet');

        if (
            $block instanceof Mage_Adminhtml_Block_Widget_Grid_Massaction &&
            $block->getRequest()->getControllerName() == 'adminhtml_sales_order'
        ) {
            $block->addItem(
                'eet_create',
                array(
                    'label' => $helper->__('Send to EET'),
                    'url' => Mage::app()->getStore()->getUrl('adminhtml/eet_record/create'),
                )
            );
        }
    }

    /**
     * Handler for adminhtml_widget_container_html_before event
     *
     * @param Varien_Event_Observer $observer
     * @return void
     * @throws Exception
     */
    public function addButton($observer)
    {
        /** @var Mage_Core_Block_Abstract */
        $block = $observer->getEvent()->getBlock();

        $helper = Mage::helper('eet');

        if ($block instanceof Mage_Adminhtml_Block_Sales_Order_View) {
            $record = Mage::getModel('eet/record')->loadByOrderId($block->getOrderId());
            if (!$record->getId() && $helper->isValidOrder($block->getOrder())) {
                $block->addButton(
                    'eet_record',
                    array(
                        'label' => $helper->__('Send to EET'),
                        'onclick' => "setLocation('{$block->getUrl('adminhtml/eet_record/create')}')",
                        'class' => 'save'
                    )
                );
            }
        }
    }

    /**
     * Handler for core_block_abstract_to_html_after event
     *
     * @param Varien_Event_Observer $observer
     * @return void
     * @throws Exception
     */
    public function addInfo($observer)
    {
        /** @var Mage_Core_Block_Abstract */
        $block = $observer->getEvent()->getBlock();

        /** @var Varien_Object */
        $transport = $observer->getEvent()->getTransport();

        if (
            $block instanceof Mage_Adminhtml_Block_Sales_Order_View_Info &&
            $block->getRequest()->getControllerName() != 'sales_order_creditmemo'
           ) {
            if ($parent = $block->getParentBlock()) {
                $order = $parent->getOrder();
                if ($order) {
                    $record = Mage::getModel('eet/record')->loadByOrderId($order->getId());
                    if ($record->getId()) {
                        $template = $block->getLayout()->createBlock('adminhtml/template')
                            ->setTemplate('maghos/eet/order.phtml')
                            ->setRecord($record);
                        $transport->setHtml($transport->getHtml() . $template->toHtml());
                    }
                }
            }
        }
    }
    
    /**
     * Handler for sales_order_payment_cancel event
     *
     * @param Varien_Event_Observer $observer
     * @return void
     * @throws Exception
     */
    public function cancelOrder($observer)
    {
        /** @var Mage_Sales_Model_Order_Payment */
        $payment = $observer->getEvent()->getPayment();

        /** @var Mage_Sales_Model_Order */
        $order = $payment->getOrder();
       
        $helper = Mage::helper('eet');
        $create = Mage::getModel('eet/record_create');
        
        if (!$helper->isActive($order->getStoreId())) {
            return;
        }

        $success = true;
        try {
            $record = Mage::getModel('eet/record')->loadByOrderId($order->getId());
            if ($record->getId()) {
                $create->fromOrder($order, true);
            }
        } catch (Exception $ex) {
            $success = false;
            Mage::logException($ex);
        }

        if (Mage::getSingleton('admin/session')->isLoggedIn()) {
            if($success) {
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    $helper->__(
                        'EET record created for order #%s',
                        $order->getIncrementId()
                    )
                );
            }else{
                Mage::getSingleton('adminhtml/session')->addError(
                    $helper->__(
                        'Cannot create EET record for order #%s',
                        $order->getIncrementId()
                    )
                );
            }
        }
    }

    /**
     * Process unsended receipts
     *
     * @return void
     * @throws Exception
     */
    public function cronSend()
    {
        $collection = Mage::getModel('eet/record')->getCollection();
        $collection->addFieldToFilter('status', Maghos_Eet_Model_Record::STATUS_ERROR)
            ->setPageSize(50)
            ->setCurPage(1)
            ->setOrder('created_at', 'asc');

        $create = Mage::getModel('eet/record_create');
        
        foreach ($collection as $record) {  
            $create->retry($record);
        }
    }
}