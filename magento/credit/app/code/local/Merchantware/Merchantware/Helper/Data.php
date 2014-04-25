<?php
class Merchantware_Merchantware_Helper_Data extends Mage_Core_Helper_Data
{		
	public function getImageUrl()
	{
		return Mage::getDesign()->getSkinUrl('merchantware/images/');
	}
}
