<?php 

class Merchantware_Giftcard_Model_Giftcard extends Mage_Sales_Model_Quote_Address_Total_Abstract
{
	
	protected function _aggregateItemDiscount($amount){
        if ($amount) {
            $this->_addAmount(-$amount);
            $this->_addBaseAmount(-$amount);
        }
        return $this;
    }
	
	public function collect(Mage_Sales_Model_Quote_Address $address) {
		
		// we only do this on the shipping address information
        parent::collect($address);
		if ($address->getData('address_type')=='billing') return $this;
		
		$gcTotal = Mage::getSingleton('core/session')->getGiftcardAmount();
		$gcCode = Mage::getSingleton('core/session')->getGiftcardCode();
		$gcLimit = Mage::getSingleton('core/session')->getGiftcardLimit();
		
        $amount = number_format($gcTotal,2);
		if(($gcTotal > 0) && ($gcLimit > 0)) {
		
			$quote = Mage::getModel('checkout/session')->getQuote();
			$quoteData= $quote->getData();
			
			$shipping = $address->getShipping_amount();
			$tax = $address->getTaxAmount();
			if(array_key_exists('subtotal', $quoteData)){
				$subtotal=$quoteData['subtotal'];
			}
			else $subtotal = 0;
			
			$grandTotal=$subtotal+$shipping+$tax;
			
			// If the total is more than our giftcard, then our card 
			// will discount the order by how much is on the card
			// Otherwise, it will discount the entire order total
			if($grandTotal > $gcLimit) $gcDiscount = $gcLimit;
			else $gcDiscount = $grandTotal;
						
			$this->_aggregateItemDiscount($gcDiscount);
		
			$address->setGiftcardDiscount($gcDiscount);
			$address->setGiftcardCode($gcCode);
		}
 	
		return $this;
	
		
	}
	
	public function fetch(Mage_Sales_Model_Quote_Address $address) {
	
		if ($address->getData('address_type')=='billing') return $this;
	
		$discount = $address->getGiftcardDiscount();
		$gcCode = Mage::getSingleton('core/session')->getGiftcardCode();

		if($discount > 0){
			 $address->addTotal(array(
				'code'  => $this->getCode(),
				'title' => Mage::helper('sales')->__('Gift Card (' . $gcCode . ')'),
				'value' => -$discount,
			));
		}
		
	
	}
}