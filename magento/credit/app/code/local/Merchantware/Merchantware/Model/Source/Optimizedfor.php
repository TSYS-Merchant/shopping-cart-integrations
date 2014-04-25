<?php
class Merchantware_Merchantware_Model_Source_Optimizedfor
{   
	const DESKTOP = 'desktop';
	const MOBILE = 'mobile';
    public function toOptionArray()
    {
        return array(
            array('value' => self::DESKTOP, 'label'=>Mage::helper('Merchantware_Merchantware')->__(ucfirst(self::DESKTOP))),
            array('value' => self::MOBILE, 'label'=>Mage::helper('Merchantware_Merchantware')->__(ucfirst(self::MOBILE))),
        );
    }
	
    public function toArray()
    {
        return array(
            self::MOBILE => Mage::helper('Merchantware_Merchantware')->__(ucfirst(self::MOBILE)),
            self::DESKTOP => Mage::helper('Merchantware_Merchantware')->__(ucfirst(self::DESKTOP)),
        );
    }

}
