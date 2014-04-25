<?php
class Merchantware_Merchantware_Model_Source_Optimizedformode
{    
	const TEST = 'test';
	const LIVE = 'live';
    public function toOptionArray()
    {
        return array(
            array('value' => self::TEST, 'label'=>Mage::helper('Merchantware_Merchantware')->__(strtoupper(self::TEST))),
            array('value' => self::LIVE, 'label'=>Mage::helper('Merchantware_Merchantware')->__(strtoupper(self::LIVE))),
        );
    }
	
    public function toArray()
    {
        return array(
            self::LIVE => Mage::helper('Merchantware_Merchantware')->__(strtoupper(self::LIVE)),
            self::TEST => Mage::helper('Merchantware_Merchantware')->__(strtoupper(self::TEST)),
        );
    }

}
