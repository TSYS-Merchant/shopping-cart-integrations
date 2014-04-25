<?php

class Merchantware_Giftcard_IndexController extends Mage_Core_Controller_Front_Action
{
	
	// this function simply returns us to the cart
    protected function _goBack()
    {
        $returnUrl = $this->getRequest()->getParam('return_url');
        if ($returnUrl) {

            if (!$this->_isUrlInternal($returnUrl)) {
                throw new Mage_Exception('External urls redirect to "' . $returnUrl . '" denied!');
            }

            $this->_getSession()->getMessages(true);
            $this->getResponse()->setRedirect($returnUrl);
        } elseif (!Mage::getStoreConfig('checkout/cart/redirect_to_cart')
            && !$this->getRequest()->getParam('in_cart')
            && $backUrl = $this->_getRefererUrl()
        ) {
            $this->getResponse()->setRedirect($backUrl);
        } else {
            if (($this->getRequest()->getActionName() == 'add') && !$this->getRequest()->getParam('in_cart')) {
                $this->_getSession()->setContinueShoppingUrl($this->_getRefererUrl());
            }
            $this->_redirect('checkout/cart');
        }
        return $this;
    }

    public function getConfigData($field)
	{
		$path = 'payment/merchantware_giftcard/'.$field;
		return Mage::getStoreConfig($path);	
	}
	
    /*  This action takes the gift card code from the form on the user's cart page 
	    It must hit the API and verify that the code is correct.   If so, it stores 
		the amount and code in session.   If not, then an error must be presented 
		to the user.   */
    public function indexAction()
    {		
		$session = Mage::getSingleton('core/session');
		
		// if we are removing this gift card from this order, unset them from session
        if ($this->getRequest()->getParam('remove') == "1") {
			$session->unsGiftcardCode();
			$session->unsGiftcardAmount();
			$session->unsGiftcardLimit();
        }
		else if($this->getRequest()->getParam('limit') == "1") {
		 	$gcLimit = (float) $this->getRequest()->getParam('giftcard_limit');
			$gcAmount = (float) $session->getGiftcardAmount();

			if($gcLimit <= $gcAmount){
				$session->setGiftcardLimit($gcLimit);	
				$session->addSuccess($this->__('Your new limit has been set.'));		
			}
			else{
				$error = $this->__('The amount you entered is more than your gift card amount.');
				$session->addError($error);
			}
		}
		else{
	    	try {		
				// get our gift code from the submitted form			
		        $gcCode = (string) $this->getRequest()->getParam('giftcard_code');

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
					if($result->BalanceInquiryKeyedResult->CardBalance > 0){						
						$session->setGiftcardCode($gcCode);
						$session->setGiftcardAmount($result->BalanceInquiryKeyedResult->CardBalance);
						$session->setGiftcardLimit($result->BalanceInquiryKeyedResult->CardBalance);
						$session->setUseGiftCard(1);
						$session->addSuccess($this->__('Your gift card balance of ' . Mage::helper('core')->currency($result->BalanceInquiryKeyedResult->CardBalance, true, false) . ' has been applied to this order, please see below.'));
					}
					else{
						$error = $this->__('The gift card you entered has a zero balance.');
						$session->addError($error);								
					}
				}
				else if(strtolower($result->BalanceInquiryKeyedResult->ApprovalStatus) == "declined"){ // not approved
					$error = $this->__('The gift card you entered was declined.');
					$session->addError($error);	
				}
				else{ // error
					$error = $this->__('There was an error verifying your gift card: ') . $result->BalanceInquiryKeyedResult->ErrorMessage;
					$session->addError($error);	
				}	
				
			}
			catch(Exception $e){
				$session->addError($this->__('There was an error connecting to the gift card gateway: ') . $e->getMessage());	
			}				
		}
		
		$session->setCartWasUpdated(true); 
		
        $this->_goBack();
    }
		
	public function balanceAction(){

		$session = Mage::getSingleton('core/session');

		if($this->getRequest()->getMethod() == "POST"){
			try {		
				// get our gift code from the submitted form			
		        $gcCode = (string) $this->getRequest()->getParam('giftcard_code');

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
					if($result->BalanceInquiryKeyedResult->CardBalance > 0){
						$session->addSuccess("Your gift card balance is: " . Mage::helper('core')->currency($result->BalanceInquiryKeyedResult->CardBalance, true, false));
					}
					else{
						$error = $this->__('The gift card you entered has a zero balance.');
						$session->addError($error);								
					}
				}
				else if(strtolower($result->BalanceInquiryKeyedResult->ApprovalStatus) == "declined"){ // not approved
					$error = $this->__('The gift card you entered was declined.');
					$session->addError($error);	
				}
				else{ // error
					$error = $this->__('There was an error verifying your gift card: ') . $result->BalanceInquiryKeyedResult->ErrorMessage;
					$session->addError($error);	
				}	
							}
			catch(Exception $e){
				$session->addError($this->__('There was an error connecting to the gift card gateway: ') . $e->getMessage());	
			}				
		}

		$this->loadLayout(array('default'));
    	$this->renderLayout();

	}
	
}
