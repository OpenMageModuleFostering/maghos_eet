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
class Maghos_Eet_Adminhtml_Eet_RecordController extends Mage_Adminhtml_Controller_Action
{

    /**
     * Create EET record action
     *
     * @return void
     */
    public function createAction()
    {
        $ids = $this->getRequest()->getParam('order_ids');
        if($ids == ''){
            $ids = $this->getRequest()->getParam('order_id');
        }
        
        $helper = Mage::helper('eet');
        $create = Mage::getModel('eet/record_create');
        
        if(!is_array($ids)){
            $ids = array($ids);
        }

        $errors = array();
        $success = array();

        foreach($ids as $id){
            $order = Mage::getModel('sales/order');
            try {
                $order->load($id);
                $create->fromOrder($order);
                $success[] = $order->getIncrementId();
            } catch (Exception $ex) {
                Mage::logException($ex);
                if($order->getId()){
                    $errors[] = $order->getIncrementId();
                }
            }
        }

        if(!empty($success)){
            Mage::getSingleton('adminhtml/session')->addSuccess(
                $helper->__(
                    count($success)>1 ? 'EET record created for orders #%s' : 'EET record created for order #%s',
                    implode(', #', $success)
                )
            );
        }
        if(!empty($errors)){
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__(
                    count($errors)>1 ? 'Cannot create EET record for orders #%s' : 'Cannot create EET record for order #%s',
                    implode(', #', $errors)
                )
            );
        }
        
        $this->_redirectReferer();
    }

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('system/eet');
    }
}
