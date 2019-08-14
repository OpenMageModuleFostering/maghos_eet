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
class Maghos_Eet_Model_Pdf_Invoice extends Mage_Sales_Model_Order_Pdf_Invoice
{

    /**
     * Insert order to pdf page
     *
     * @param Zend_Pdf_Page $page
     * @param Mage_Sales_Model_Order|Mage_Sales_Model_Order_Shipment $obj
     * @param bool $putOrderId
     */
    protected function insertOrder(&$page, $obj, $putOrderId = true)
    {
        parent::insertOrder($page, $obj, $putOrderId);

        $record = $this->getEet($obj);
        if (!$record) {
            return;
        }

        $helper = Mage::helper('eet');

        $this->y += 10;
        $top = $this->y;
        $left = 35;
        $center = 285;
        $label = 85;

        $this->_setFontRegular($page, 9);
        $page->setFillColor(new Zend_Pdf_Color_GrayScale(0));

        $page->drawText($helper->__('Shop ID'), $left, $this->y-=12, 'UTF-8');
        $page->drawText($helper->__('FIK'), $center, $this->y, 'UTF-8');

        $page->drawText($record->getShopId(), $left + $label, $this->y, 'UTF-8');
        $page->drawText($record->getKey(), $center + $label, $this->y, 'UTF-8');

        $page->drawText($helper->__('Checkout ID'), $left, $this->y-=12, 'UTF-8');
        $page->drawText($helper->__('BKP'), $center, $this->y, 'UTF-8');

        $page->drawText($record->getCheckoutId(), $left + $label, $this->y, 'UTF-8');
        $page->drawText($record->getCode(), $center + $label, $this->y, 'UTF-8');

        $page->drawText($helper->__('VAT ID'), $left, $this->y-=12, 'UTF-8');
        $page->drawText($helper->__('Mode'), $center, $this->y, 'UTF-8');

        $mode = ($record->getMode() == Maghos_Eet_Helper_Data::EET_MODE_SIMPLE) ? $helper->__('Simple') : $helper->__('Normal');
        $page->drawText($record->getVatId(), $left + $label, $this->y, 'UTF-8');
        $page->drawText($mode, $center + $label, $this->y, 'UTF-8');

        $page->setLineColor(new Zend_Pdf_Color_GrayScale(0.5));
        $page->setLineWidth(0.5);
        $page->drawRectangle(25, $top, 570, $this->y - 7, Zend_Pdf_Page::SHAPE_DRAW_STROKE);

        $page->setFillColor(new Zend_Pdf_Color_RGB(0, 0, 0));

        $this->y -= 15;
    }

    /**
     * Get EET record for source
     *
     * @param Mage_Sales_Model_Order|Mage_Sales_Model_Order_Shipment $obj
     * @return Maghos_Eet_Model_Record|null
     */
    protected function getEet($obj)
    {
        if ($obj instanceof Mage_Sales_Model_Order) {
            $order = $obj;
        } elseif ($obj instanceof Mage_Sales_Model_Order_Shipment) {
            $order = $obj->getOrder();
        }

        $record = Mage::getModel('eet/record')->loadByOrderId($order->getId());
        if ($record->getId()) {
            return $record;
        }

        return null;
    }
}
