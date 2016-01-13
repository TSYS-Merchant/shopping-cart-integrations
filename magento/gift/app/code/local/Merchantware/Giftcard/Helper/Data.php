<?php
class Merchantware_Giftcard_Helper_Data extends Mage_Core_Helper_Abstract
{
	public function getConfigData($field)
	{
		$path = 'payment/merchantware_giftcard/'.$field;
		return Mage::getStoreConfig($path);	
	}
    
    public function getConfigDataDecrypt($field)
	{
		$path = 'payment/merchantware_giftcard/'.$field;
		return Mage::helper('core')->decrypt(Mage::getStoreConfig($path));	
	}
	
	
	
}