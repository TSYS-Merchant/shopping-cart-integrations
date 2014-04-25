<?php
$installer = $this;
$setup = new Mage_Eav_Model_Entity_Setup('core_setup');
$installer->startSetup();
$installer->run("
		ALTER TABLE `".$this->getTable('sales/order')."` ADD `giftcard_discount` float(10,2) NOT NULL DEFAULT 0;
		ALTER TABLE `".$this->getTable('sales/order')."` ADD `giftcard_code` varchar(255) NOT NULL DEFAULT 0;
");

$installer->endSetup();