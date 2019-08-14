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
class Maghos_Eet_Model_Config_Payment
{

    protected $_methods;

    public function toOptionArray()
    {
        if (!$this->_methods) {
            $this->_methods = array();
            $methods = Mage::getModel('payment/config')->getAllMethods(null);
            foreach ($methods as $method) {
                $this->_methods[] = array(
                    'value' => $method->getCode(),
                    'label' => $method->getTitle()
                );
            }
        }
        return $this->_methods;
    }
}
