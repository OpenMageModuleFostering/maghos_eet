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

$installer = $this;

$installer->startSetup();

$eet = $installer->getConnection()
        ->newTable($installer->getTable('eet/record'))
        ->addColumn(
            'id',
            Varien_Db_Ddl_Table::TYPE_INTEGER,
            null,
            array(
                'unsigned' => true,
                'nullable' => false,
                'identity' => true,
                'primary' => true
            ),
            'Record internal id'
        )
        ->addColumn(
            'order_id',
            Varien_Db_Ddl_Table::TYPE_INTEGER,
            null,
            array(
                'nullable' => false,
            ),
            'Magento order id'
        )
        ->addColumn(
            'vat_id',
            Varien_Db_Ddl_Table::TYPE_VARCHAR,
            20,
            array(
                'nullable' => false,
            ),
            'VAT id'
        )
        ->addColumn(
            'shop_id',
            Varien_Db_Ddl_Table::TYPE_VARCHAR,
            40,
            array(
                'nullable' => false,
            ),
            'Shop id'
        )
        ->addColumn(
            'checkout_id',
            Varien_Db_Ddl_Table::TYPE_VARCHAR,
            40,
            array(
                'nullable' => false,
            ),
            'Checkout id'
        )
        ->addColumn(
            'mode',
            Varien_Db_Ddl_Table::TYPE_TINYINT,
            1,
            array(
                'nullable' => false,
            ),
            'EET mode'
        )
        ->addColumn(
            'key',
            Varien_Db_Ddl_Table::TYPE_TEXT,
            null,
            array(
                'nullable' => true,
            ),
            'Key from EET'
        )
        ->addColumn(
            'code',
            Varien_Db_Ddl_Table::TYPE_TEXT,
            null,
            array(
                'nullable' => true,
            ),
            'EET Security code'
        )
        ->addColumn(
            'status',
            Varien_Db_Ddl_Table::TYPE_BOOLEAN,
            null,
            array(
                'nullable' => false,
                'default' => 0
            ),
            'EET response status'
        )
        ->addColumn(
            'is_refund',
            Varien_Db_Ddl_Table::TYPE_BOOLEAN,
            null,
            array(
                'nullable' => false,
                'default' => 0,
            ),
            'This record is refund'
        )
        ->addColumn(
            'created_at',
            Varien_Db_Ddl_Table::TYPE_TIMESTAMP,
            null,
            array(
                'nullable' => false,
                'default' => Varien_Db_Ddl_Table::TIMESTAMP_INIT
            ),
            'Record creation date'
        )
        ->addIndex(
            $installer->getIdxName(
              'eet/record',
              array('status'),
              Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX
            ),
            array('status'),
            array('type' => Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX)
        )
        ->addForeignKey(
              $this->getFkName('eet/record', 'order_id', 'sales/order', 'entity_id'),
              'order_id',
              $this->getTable('sales/order'),
              'entity_id',
              Varien_Db_Ddl_Table::ACTION_CASCADE,
              Varien_Db_Ddl_Table::ACTION_CASCADE
        )
        ->setComment('EET Record');

$installer->getConnection()->createTable($eet);

$installer->endSetup();
