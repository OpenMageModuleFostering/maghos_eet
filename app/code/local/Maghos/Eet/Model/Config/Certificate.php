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
class Maghos_Eet_Model_Config_Certificate extends Mage_Core_Model_Config_Data
{

    /**
     * Save uploaded file before saving config value
     *
     * @return Mage_Adminhtml_Model_System_Config_Backend_File
     */
    protected function _beforeSave()
    {
        $value = $this->getValue();
        if ($_FILES['groups']['tmp_name'][$this->getGroupId()]['fields'][$this->getField()]['value']) {
            try {
                $maxsize = 4 * 1024;
                $tmpName = $_FILES['groups']['tmp_name'];
                $name = $_FILES['groups']['name'];
                $size = $_FILES['groups']['size'];
                $path = $tmpName[$this->getGroupId()]['fields'][$this->getField()]['value'];
                $fileSize = $size[$this->getGroupId()]['fields'][$this->getField()]['value'];
                $fileName = $name[$this->getGroupId()]['fields'][$this->getField()]['value'];

                if ($fileSize > $maxsize) {
                    throw Mage::exception('Mage_Core', Mage::helper('eet')->__('Uploaded certificate file is not valid'));
                }

                Mage::getConfig()->saveConfig(
                    $this->getPath() . '_content',
                    file_get_contents($path),
                    $this->getScope(),
                    $this->getScopeId()
                );

                $this->setValue($fileName);
            } catch (Exception $e) {
                Mage::throwException($e->getMessage());
                return $this;
            }
        } else {
            if (is_array($value) && !empty($value['delete'])) {
                $this->setValue('');
                Mage::getConfig()->saveConfig(
                    $this->getPath() . '_content',
                    '',
                    $this->getScope(), $this->getScopeId()
                );
            } else {
                $this->unsValue();
            }
        }

        return $this;
    }
}
