<?php 

class Merchantware_Giftcard_Model_Observer extends Varien_Object
{

    public function getConfigData($field)
	{
		$path = 'payment/merchantware_giftcard/'.$field;
		return Mage::getStoreConfig($path);	
	}

	public function salesOrderPaymentPlaceStart($observer){

		$quote = Mage::getSingleton('checkout/session')->getQuote();
		$address = $quote->getShippingAddress();
		$gcDiscount = $address->getGiftcardDiscount();
		
		$gcTotal = Mage::getSingleton('core/session')->getGiftcardAmount();
		$gcCode = Mage::getSingleton('core/session')->getGiftcardCode();

		if($gcDiscount > 0){

			$apiUrl = "https://ps1.merchantware.net/Merchantware/ws/ExtensionServices/v4/Giftcard.asmx?WSDL";
			$merchName = $this->getConfigData("name");
			$siteId = $this->getConfigData("site_id");
			$key = $this->getConfigData("key");
				
			// Create our soap client
			$client = new SoapClient($apiUrl,array("trace"=>1));
				
			// Here is the data required to make this API call
			$card = array("merchantName" => $merchName,
				"merchantSiteId" => $siteId,
				"merchantKey" => $key,
				"cardNumber" => $gcCode);
	
			// Make the API call
			$result = $client->BalanceInquiryKeyed($card);
			
			// was approved
			if(strtolower($result->BalanceInquiryKeyedResult->ApprovalStatus) == "approved"){ // approved
			
				// make sure they card has enough balance to cover the discount
				if($gcDiscount > $result->BalanceInquiryKeyedResult->CardBalance){
					Mage::getSingleton('core/session')->addError($this->__('There was an error with your gift card. The amount changed during checkout.'));

					$url = Mage::getUrl('checkout/cart');//eg to redirect to cart page
					$response = Mage::app()->getFrontController()->getResponse();
					$response->setRedirect($url);
			
					$controllerAction = $observer->getEvent()->getControllerAction();
					$result = array();
					$result['error'] = '-1';
					$result['message'] = 'Error with Gift Card';
					$controllerAction->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
					exit;
				}			
			}
			else if(strtolower($result->BalanceInquiryKeyedResult->ApprovalStatus) == "declined"){ // not approved
				Mage::getSingleton('core/session')->addError($this->__('The gift card you entered was declined.'));
				$url = Mage::getUrl('checkout/cart');//eg to redirect to cart page
				$response = Mage::app()->getFrontController()->getResponse();
				$response->setRedirect($url);
		
				$controllerAction = $observer->getEvent()->getControllerAction();
				$result = array();
				$result['error'] = '-1';
				$result['message'] = 'Error with Gift Card';
				$controllerAction->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
				exit;	
			}
			else{ // error
				Mage::getSingleton('core/session')->addError($this->__('There was an error verifying your gift card: ') . $result->BalanceInquiryKeyedResult->ErrorMessage);
				$url = Mage::getUrl('checkout/cart');//eg to redirect to cart page
				$response = Mage::app()->getFrontController()->getResponse();
				$response->setRedirect($url);
		
				$controllerAction = $observer->getEvent()->getControllerAction();
				$result = array();
				$result['error'] = '-1';
				$result['message'] = 'Error with Gift Card';
				$controllerAction->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
				exit;
			}
		}
				
		return $this;

	}

	// Now that payment has been made, we need to subtract the amount from
	// the gift card.   
	public function checkoutOnepageControllerSuccessAction($observer){ // sales_order_invoice_pay($observer){
				
		$orderId = $observer->getEvent()->getOrderIds();		
        $order = Mage::getSingleton('sales/order')->load($orderId[0]);    
	  
		$gcDiscount = $order->getGiftcardDiscount();
		$gcCode = $order->getGiftcardCode();

		if($gcDiscount > 0){
			
			$apiUrl = "https://ps1.merchantware.net/Merchantware/ws/ExtensionServices/v4/Giftcard.asmx?WSDL";
			$merchName = $this->getConfigData("name");
			$siteId = $this->getConfigData("site_id");
			$key = $this->getConfigData("key");

			// Create our soap client
			$client = new SoapClient($apiUrl,array("trace"=>1));
				
			// Here is the data required to make this API call
			$card = array("merchantName" => $merchName,
				"merchantSiteId" => $siteId,
				"merchantKey" => $key,
				"cardNumber" => $gcCode,
				"amount" => $gcDiscount);
	
			// Make the API call
			$result = $client->SaleKeyed($card);

		}
		
		Mage::getSingleton('core/session')->unsGiftcardAmount();
		Mage::getSingleton('core/session')->unsGiftcardCode();
						

		return $this;
	}
	
	public function updatePaypalTotal($observer){
		$cart = $observer->getPaypalCart();
		$salesEntity = $cart->getSalesEntity();
		$cart->updateTotal(Mage_Paypal_Model_Cart::TOTAL_DISCOUNT, $salesEntity->getGiftcardDiscount());
    }
}
?>