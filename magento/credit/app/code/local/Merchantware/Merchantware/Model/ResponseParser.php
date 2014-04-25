<?php	
class Merchantware_Merchantware_Model_ResponseParser extends Varien_Simplexml_Element {
	
	public function isSuccessful() {
		$this->registerXPathNamespace('transport','http://transport.merchantware.net/v4/');
		$_result = $this->xpath('//transport:TransportKey');
		if($_result[0]) {
			return true;
		}
		return false;
	}
	
	public function getError() {
		$_ret = new Varien_Object();		
		$this->registerXPathNamespace('transport','http://transport.merchantware.net/v4/');
		$_result = $this->xpath('//transport:Messages');
		$error_message = '';
		foreach ($this->xpath('//transport:Messages') as $index => $item){			
			$message = (array)$item;
			foreach($message['Message'] as $tag => $value) {		
				$error_message .= $tag." : ".$value."\n";
				$_ret->setData('Reason', $error_message);
			}
		}
		return $_ret;
	}
	
	public function getInitializePaymentResult() {
		$_res = new Varien_Object();
		$this->registerXPathNamespace('transport','http://transport.merchantware.net/v4/');
		$_result = $this->xpath('//transport:CreateTransactionResult');
		if(count($_result)) {
			$_result = $_result[0]->asArray();			
			$_res->setData($_result);
		}
		return $_res;
	}
	
	public function getVerifyPaymentResult() {
		$_res = new Varien_Object();
		$this->registerXPathNamespace('response', 'http://www.merchantware.net/');
		$_result = $this->xpath('//response:VerifyPaymentResult');
		if(count($_result)) {
			$_result = $_result[0]->asArray();
			$_res->setData($_result);
		}
		return $_res;
	}
	
	public function getAcknowledgePaymentResult() {
		$_res = new Varien_Object();
		$this->registerXPathNamespace('response', 'http://www.merchantware.net/');
		$_result = $this->xpath('//response:AcknowledgePaymentResult');
		if(count($_result)) {
			$_result = $_result[0]->asArray();
			$_res->setData($_result);
		}
		return $_res;
	}
}
