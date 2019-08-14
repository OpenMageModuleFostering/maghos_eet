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
class Maghos_Eet_Model_Config_Shipping
{

    protected $_methods;

    public function toOptionArray()
    {
        if (!$this->_methods) {
            $methods = Mage::getSingleton('shipping/config')->getActiveCarriers();

            $this->_methods = array();
            $matrixrate = null;

            foreach ($methods as $code => $carrier) {
                if (!$title = Mage::getStoreConfig("carriers/$code/title")) {
                    $title = $code;
                }
                if ($code == 'matrixrate') {
                    $matrixrate = $title;
                    continue;
                }
                if ($carrierMethods = $carrier->getAllowedMethods()) {
                    $single = count($carrierMethods) == 1;
                    foreach ($carrierMethods as $methodCode => $method) {
                        $this->_methods[] = array(
                            'label' => (!$single && $method) ? $title . ' - ' . $method : $title,
                            'value' => $code . '_' . $methodCode
                        );
                    }
                }
            }

            if ($matrixrate) {
                $collection = Mage::getResourceModel('matrixrate_shipping/carrier_matrixrate_collection');
                if ($collection) {
                    foreach ($collection as $rate) {
                        $this->_methods[] = array(
                            'label' => '' . $rate->getDeliveryType(),
                            'value' => 'matrixrate_matrixrate_' . $rate->getId()
                        );
                    }
                }
            }
        }
        return $this->_methods;
    }
}
