<?php

class Merchantware_Giftcard_Adminhtml_Block_Sales_Order_Invoice_Totals extends Mage_Adminhtml_Block_Sales_Order_Totals
{
    /**
     * Initialize order totals array
     *
     * @return Mage_Sales_Block_Order_Totals
     */
    protected function _initTotals()
    {
        parent::_initTotals();
        
		$amount = number_format($this->getOrder()->getGiftcardDiscount(),2);
		$gcCode = $this->getOrder()->getGiftcardCode();
		
        if ($amount > 0) {
            $this->addTotalBefore(new Varien_Object(array(
                'code'  => $this->getCode(),
                'value'     => -$amount,
                'base_value'=> -$amount,
                'label'     => 'Gift Card (' . $gcCode . ')',
            )), 'grand_total');
        }

        return $this;
    }
}