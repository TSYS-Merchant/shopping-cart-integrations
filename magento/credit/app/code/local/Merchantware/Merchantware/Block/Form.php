<?php
class Merchantware_Merchantware_Block_Form extends Mage_Payment_Block_Form {
	
	protected function _construct() {
		parent::_construct();
		$this->setTemplate('merchantware/merchantware/form.phtml');
	}
}
