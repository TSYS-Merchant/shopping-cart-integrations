<?php
class Merchantware_Merchantware_Model_Transactiontype extends Mage_Core_Model_Abstract
{
	const SALE = 'SALE';
	const LEVEL2SALE = 'LEVEL2SALE';
	const PREAUTH = 'PREAUTH';
 	public function toOptionArray()
    {		
		$optionArray = array();
		$optionArray[self::SALE] = self::SALE;
		$optionArray[self::LEVEL2SALE] = self::LEVEL2SALE;
		$optionArray[self::PREAUTH] = self::PREAUTH;
		return $optionArray;
    }
}