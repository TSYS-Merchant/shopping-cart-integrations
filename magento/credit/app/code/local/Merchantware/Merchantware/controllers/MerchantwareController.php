<?php
class Merchantware_Merchantware_MerchantwareController extends Mage_Core_Controller_Front_Action {
	
	protected function _expireAjax() {
		if(!$this->_getCheckout()->getQuote()->hasItems()) {
			$this->getResponse()->setHeader('HTTP/1.1','403 Session Expired');
			exit;
		}
	}
	
	protected function _getCheckout() {
		return Mage::getSingleton('checkout/session');
	}
	
	protected function _getOrderIdFromValidationKey($validationKey) {
		$_merchantwareSess = (array)$this->_getCheckout()->getMerchantwareSession();
		foreach($_merchantwareSess as $_k => $_v) {
			if($validationKey == $_v['ValidationKey']) {
				return $_k;
			}
		}
		return NULL;
	}
	
	public function redirectAction() {
		$_merchantwareSess = (array)$this->_getCheckout()->getMerchantwareSession();			
		if(($order_id = $this->getRequest()->getParam('order_id')) && isset($_merchantwareSess[$order_id])) {	
			$this->loadLayout();
			$this->getLayout()
				->getBlock('merchantware_redirect')					
				->setTransportkey($_merchantwareSess[$order_id]['TransportKey']);				
			$this->renderLayout();
		}else {
			$_url = Mage::helper('checkout/url')->getCartUrl();
			Mage::app()->getResponse()->setRedirect($_url)->sendResponse();
			exit();
		}
	}
	
	public function returnAction() {
		$_url = Mage::helper('checkout/url')->getCartUrl();
		$_req = $this->getRequest();
		$optimizedfor = Mage::getStoreConfig('payment/merchantware_payment/optimizedfor');		
		$validationKey = $_req->getParam('ValidationKey',0);
		if($validationKey || $optimizedfor == Merchantware_Merchantware_Model_Source_Optimizedfor::MOBILE) {			
			if($optimizedfor == Merchantware_Merchantware_Model_Source_Optimizedfor::MOBILE) {
					if(!$validationKey) {
						$order_id = 0;
						$response = explode(";&",key($_req->getParams()));
						$statusResponse = explode("=",$response[0]);
						$status = $statusResponse[1];
						$orderidResponse = explode("=",$response[1]);
						$order_id = $orderidResponse[1];
					}else{
						$order_id = $this->_getOrderIdFromValidationKey($validationKey);
					}
			}else{
				$order_id = $this->_getOrderIdFromValidationKey($validationKey);	
			}
			if($order_id) {
				$order = Mage::getModel('sales/order')->loadByIncrementId($order_id);
				if($order && $order->getId()) {
					$_reply = $order->getPayment()->getMethodInstance()->updateGatewayResponse($order_id, $_req->getParams());
					if($_reply['success']) {
						$_url = Mage::getUrl('checkout/onepage/success/');
					}else {
						if($order && $order->getId()) { 
							$order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true)->save();
						}
						$_url = Mage::getUrl('checkout/onepage/failure/');
						$this->_getCheckout()->setErrorMessage($_reply['message']);
					}
				}
			}
		}
		Mage::getSingleton('checkout/session')->unsMerchantwareSession();
		Mage::app()->getResponse()->setRedirect($_url)->sendResponse();
		exit();
	}
}
