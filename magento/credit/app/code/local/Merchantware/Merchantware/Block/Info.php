<?php
class Merchantware_Merchantware_Block_Info extends Mage_Payment_Block_Info {
	
	protected function _construct() {
		parent::_construct();
		$this->setTemplate('merchantware/merchantware/info.phtml');
	}
	
	public function toPdf() {
		$this->setTemplate('merchantware/info/pdf/default.phtml');
		return $this->toHtml();
	}
}
