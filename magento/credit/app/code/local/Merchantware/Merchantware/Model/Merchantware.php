<?php
class Merchantware_Merchantware_Model_Merchantware extends Mage_Payment_Model_Method_Abstract
{
	//See below, we have 2 sets of URLS for TEST and LIVE mode 
	//We also have desktop and mobile optimization	
	// For mobile submision
	const TEST_CHECKOUT_URL = 'https://staging.merchantware.net/transportweb/transportmobile.aspx';
	const LIVE_CHECKOUT_URL = 'https://transport.merchantware.net/v4/transportmobile.aspx';
	
	// For web submission
	const TEST_SERVER_URL = 'http://transport.merchantware.net/v4/CreateTransaction';
	const LIVE_SERVER_URL = 'http://transport.merchantware.net/v4/CreateTransaction';
	const MW_GETKEY_HOST = 'https://transport.merchantware.net/v4/transportservice.asmx';	
		
	const CANCEL_STATUS = 'User_Cancelled';
	const APPROVED_STATUS = 'APPROVED';
	const DECLINED_STATUS = 'DECLINED';
	protected $_code = 'merchantware_payment';
	const TEST_MOBILE_REDIRECT_URL = 'https://transport.merchantware.net/v4/transportmobile.aspx';
	const TEST_WEB_REDIRECT_URL = 'https://transport.merchantware.net/v4/transportweb.aspx';
	const LIVE_MOBILE_REDIRECT_URL = 'https://transport.merchantware.net/v4/transportmobile.aspx';
	const LIVE_WEB_REDIRECT_URL = 'https://transport.merchantware.net/v4/transportweb.aspx';	
	
	protected $_formBlockType = 'Merchantware_Merchantware/form';
	protected $_infoBlockType = 'Merchantware_Merchantware/info';
	
	protected $_isGateway                   = true;
	protected $_canOrder                    = false;
	protected $_canAuthorize                = false;
	protected $_canCapture                  = true;
	protected $_canCapturePartial           = false;
	protected $_canRefund                   = false;
	protected $_canRefundInvoicePartial     = false;
	protected $_canVoid                     = false;
	protected $_canUseInternal              = false;
	protected $_canUseCheckout              = true;
	protected $_canUseForMultishipping      = false;
	protected $_isInitializeNeeded          = true;
	protected $_canFetchTransactionInfo     = false;
	protected $_canReviewPayment            = false;
	protected $_canCreateBillingAgreement   = false;
	protected $_canManageRecurringProfiles  = false;
	protected $_canCancelInvoice            = false;
	protected $_debugReplacePrivateDataKeys = array();
	
	public function getSubConfigData($field)
	{
		$path = 'payment/'.$this->_code.'_'.$field;
		return Mage::getStoreConfig($path);	
	}
	
	protected function _debug($debugData)
    {
        if ($this->getDebugFlag()) {
            Mage::getModel('core/log_adapter', 'merchantwarehouse_payment.log')
               ->setFilterDataKeys($this->_debugReplacePrivateDataKeys)
               ->log($debugData);
        }
    }
    
    public function getDebugFlag()
    {
        return $this->getConfigData('debug');
    }
	
	public function submitPostRequest($host, $xml,$soapaction) 
	{
		$this->_debug(array('RequestedURL'=>$soapaction, 'RequestedXML'=>$xml));
		$ch = curl_init($host);
	
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
	
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	
		curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
	
		// Only add soap header if soap action is available
		$headers = array("Content-Type: text/xml; charset=utf-8",
				"SOAPAction: ".$soapaction);
	
		// Only add headers if they are available
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		try {
			$content = curl_exec($ch);
		}catch(Exception $e) {
				$this->_debug(array('Error' => $e->getMessage()));
				throw $e;
		}
	
		curl_close($ch);
		
		
		try {			
				$_xmlObject = Mage::getModel('Merchantware_Merchantware/ResponseParser', ($content));
			}catch(Exception $e) {
				$this->_debug(array('Error' => $e->getMessage()));
				throw $e;
			}
			
			if(!$_xmlObject->isSuccessful()) {
				$this->_debug(array('Error' => $_xmlObject->getError()->getData('Reason')));
				throw Mage::Exception('Mage_Payment_Model_Info', $_xmlObject->getError()->getData('Reason'));
			}
			
			return $_xmlObject;
	}
	
	public function initialize($paymentAction, $stateObject) {		
		$_xml = $this->_getInitPaymentXml($this->_prepareParams());
		$_url = $this->_getServerUrl();
		$_msg = $this->_getHelper()->__('The payment session can not be initialized');
		
		try {			
			$response = $this->submitPostRequest(self::MW_GETKEY_HOST,$_xml,$_url);
			$_result   = $response->getInitializePaymentResult();
			//echo '<pre>';print_r($_result);die;
			if($_result->getData('TransportKey') == '') {
				$this->_debug(array('Error' => $_result->getData('Messages')));
				throw Mage::Exception('Mage_Payment_Model_Info', $_msg);
			}
		}catch(Exception $e) {
			//$e->getMessage();
			throw Mage::Exception('Mage_Payment_Model_Info', $_msg);
		}
		
		$_merchantwareSess = (array)Mage::getSingleton('checkout/session')->getMerchantwareSession();
		$_merchantwareSess[$this->_getOrderId()]['TransportKey'] = $_result->getData('TransportKey');
		$_merchantwareSess[$this->_getOrderId()]['ValidationKey'] = $_result->getData('ValidationKey');
		Mage::getSingleton('checkout/session')->setMerchantwareSession($_merchantwareSess);
		
		return $stateObject;
	}
		
	public function getCheckoutUrl() {
		if($this->getConfigData('test_mode') == 1 && ($this->getConfigData('optimizedfor') == 'desktop' || $this->getConfigData('optimizedfor') == 'mobile') && $this->getConfigData('optimizedformode') == 'test') {
					return self::TEST_CHECKOUT_URL;
			}
			return self::LIVE_CHECKOUT_URL;		
	}
	
	public function getOrderPlaceRedirectUrl() {		
		return Mage::getUrl('merchantware/merchantware/redirect', array('order_id'=>$this->_getOrderId()));
	}
	
	public function updateGatewayResponse($_orderId, $_post) {
		$_reply = array(
			'message'  => $this->_getHelper()->__('Unfortunately we can not varify the transaction details, please contact us.'),
			'success'  => false,
		);
		
		$_merchantwareSess = (array)Mage::getSingleton('checkout/session')->getMerchantwareSession();
		unset($_merchantwareSess[$_orderId]);
		Mage::getSingleton('checkout/session')->setMerchantwareSession($_merchantwareSess);
		$_req = Mage::app()->getRequest();
		$validationKey = $_req->getParam('ValidationKey',0);
		$optimizedfor = Mage::getStoreConfig('payment/merchantware_payment/optimizedfor');
		if($optimizedfor == Merchantware_Merchantware_Model_Source_Optimizedfor::MOBILE) {
			if(!$validationKey) {
				$_postData = key($_req->getParams());
				$response = explode(";&",$_postData);
				$statusResponse = explode("=",$response[0]);
				$_post['Status'] = $statusResponse[1];
				$status = $statusResponse[1];
				$orderidResponse = explode("=",$response[1]);
				$transactionID = $orderidResponse[1];
			}else{
				$transactionID = $_post['TransactionID'];
				$status = '';
				$responseStatus = $_post['Status'];
				if($responseStatus){
					$paymentStatus = explode(";",$responseStatus);
					$status = $paymentStatus[0];
				}
			}
		}
		if($optimizedfor != Merchantware_Merchantware_Model_Source_Optimizedfor::MOBILE) {
			$transactionID = $_post['TransactionID'];
			$status = '';
			$responseStatus = $_post['Status'];
			if($responseStatus){
				$paymentStatus = explode(";",$responseStatus);
				$status = $paymentStatus[0];
			}
		}
		$this->_debug(array('ResponsePost'=>$_post));
		
		
		try {
			
			
			if($transactionID && ($status && $status == Merchantware_Merchantware_Model_Merchantware::APPROVED_STATUS)) {
				$orderAmt = $this->_getAmount();
				$_tmpMsg  = $this->_getHelper()->__('Payment is successfully made.');
				if($_post['RefID']) {
					$_tmpMsg .= ' RefID: ' . $_post['RefID'];
				}
				if($_post['Token']) {
					$_tmpMsg .= ', Token: ' . $_post['Token'];
				}
				if($_post['AuthCode']) {
					$_tmpMsg .= ', AuthCode: ' . $_post['AuthCode'];
				}				
				$this->onSuccess($_post['RefID'], $orderAmt, $_tmpMsg);
				$_reply['success'] = true;
				$_reply['message'] = NULL;
			}elseif($transactionID && ($status && $status == Merchantware_Merchantware_Model_Merchantware::CANCEL_STATUS)) {				
				$_tmpErr  = $this->_getHelper()->__('Cancelled Payment.');
				if($_post['Status']) {
					$_tmpErr .= ' Status: ' . $_post['Status'];
				}
				$this->onFailure($_tmpErr);	
				$_reply['message'] = $this->_getHelper()->__('Cancelled Payment.');
			}elseif($transactionID && ($status && $status == Merchantware_Merchantware_Model_Merchantware::DECLINED_STATUS)){
				$_tmpErr  = $this->_getHelper()->__('Declined Payment.');
				
				
				$status = '';
				if($responseStatus){
					$paymentStatus = explode(";",$responseStatus);
					$status = $paymentStatus[0]." ".$paymentStatus[2];
				}
				if($status) {
					$_tmpErr .= ' Status: ' . $status;
				}
				
				$this->onFailure($_tmpErr);
				$_reply['message'] = $this->_getHelper()->__('Declined Payment.').'<br/>'.$status;
			}else {
				$_tmpErr = 'Failed Transaction.';
				if($_post['TransactionID'] != $_orderId) {
					$_tmpErr = 'Fraudulent Response. The order ID (' . $_orderId . ')'
						. ' is mismatching with the TransactionID (' . $_post['TransactionID'] . ')'
						. ' from the Merchantware Server'
					;
					$_reply['message'] = 'Invalid Transaction.';
				}
				//if($_tmpErr)
					$this->onFailure($_tmpErr);
			}
		}catch(Exception $e) {
			$this->onFailure($this->_getHelper()->__($e->getMessage()));
		}
		//$this->_postAcknowledge($_post['PaymentID']);
		return $_reply;
	}
	
	public function onFailure($orderComment = NULL) {
		$order   = $this->getInfoInstance()->getOrder();
		$payment = $order->getPayment();
		
		$payment->setStatus(self::STATUS_ERROR)
			->setStatusDescription($orderComment)
			->save()
		;
		
		$order->addStatusHistoryComment($orderComment)->save();
		return $this;
	}
	
	public function onSuccess($transId, $amount, $orderComment=NULL) {
		$order   = $this->getInfoInstance()->getOrder();
		$payment = $order->getPayment();
		
		$payment->setStatus(self::STATUS_SUCCESS)
			->setStatusDescription($orderComment)
			->setTransactionId($transId)
			->setIsTransactionClosed(0)
			->setSuTransactionId($transId)
		;
		
		$payment->setLastTransId($transId);
		$payment->save();


        /* TJG REMOVED: we are going to create it manually */
//		$payment->registerCaptureNotification($amount);
//      $invoice = $payment->getCreatedInvoice();

        /* TJG ADDED: Create the invoice manually */
        $invoice = $order->prepareInvoice();
        $invoice->register()->capture();

        Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder())
            ->save();
        $order->save();
        /* END TJG ADDED */

		if($invoice) $invoice->save();

        /* TJG REMOVED:  We created the transaction above */
//      $transaction = $payment->getCreatedTransaction();
//      if($transaction) $transaction->setIsClosed(1)->save();
		
		$order->addStatusHistoryComment($orderComment)->save();
		
		$order->sendNewOrderEmail();
		$order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true)->save();		
		return $this;
	}
	
	protected function _prepareParams() {
		$_billing = $this->_getBillingAddress();
		$info = $this->getInfoInstance();
		$_billingData = $_billing->getData();
		
		$_params  = array('merchant_detail'=>array(
			'merchantName'         		=> $this->getConfigData('name'),
			'merchantSiteId'           	=> $this->getConfigData('site_id'),
			'merchantKey'           	=> $this->getConfigData('key')),
			'request_param'=>array(
			'TransactionType'		   	=> $this->getConfigData('trantype'),
			'Amount'        	 		=> $this->_getAmount(),
			'ClerkId'            		=> $this->_getOrderId(),
			'OrderNumber'		 		=> $info->getOrder()->getId(), /*The OrderNumber field is limited to 8 characters.*/
			'Dba'            	 		=> $this->getConfigData('dba'),
			'SoftwareName'       		=> 'Magento',
			'SoftwareVersion'    		=> Mage::getVersion(),
			'AddressLine1'       		=> implode(', ', $_billing->getStreet()),
			'Zip'                		=> $_billing->getPostcode(),
			'Cardholder'         		=> implode(' ', array($_billing->getFirstname(), $_billing->getLastname())),
			'RedirectLocation'          => Mage::getUrl('merchantware/merchantware/return'),
			'LogoLocation'       		=> $this->getConfigData('logo_location'),
			'TransactionId'				=> $this->_getOrderId(),
			'ForceDuplicate'			=> 'false',			
			),
			'DisplayColors'  =>array(
			'ScreenBackgroundColor'		=> $this->getSubConfigData('display_color/screen_background'),
			'ContainerBackgroundColor'	=> $this->getSubConfigData('display_color/container_background'),
			'ContainerFontColor'		=> $this->getSubConfigData('display_color/container_font'),
			'ContainerHelpFontColor'	=> $this->getSubConfigData('display_color/container_helpfont'),
			'ContainerBorderColor'		=> $this->getSubConfigData('display_color/container_border'),
			'LogoBackgroundColor'		=> $this->getSubConfigData('display_color/logo_background'),
			'LogoBorderColor'			=> $this->getSubConfigData('display_color/logo_border'),
			'TooltipBackgroundColor'	=> $this->getSubConfigData('display_color/tooltip_background'),
			'TooltipBorderColor'		=> $this->getSubConfigData('display_color/tooltip_border'),
			'TextboxBackgroundColor'	=> $this->getSubConfigData('display_color/textbox_background'),
			'TextboxBorderColor'		=> $this->getSubConfigData('display_color/textbox_border'),
			'TextboxFocusBackgroundColor'=> $this->getSubConfigData('display_color/textbox_focusbackground'),
			'TextboxFocusBorderColor'	=> $this->getSubConfigData('display_color/textbox_focusborder'),
			'TextboxFontColor'			=> $this->getSubConfigData('display_color/textbox_font')),
			'DisplayOptions' =>array(
			'AlignLeft'					=> ($this->getSubConfigData('display_options/align_left')) ? 'true' : 'false',
			'NoCardNumberMask'			=> ($this->getSubConfigData('display_options/card_number_no_mask')) ? 'true' : 'false',
			'HideDetails'				=> ($this->getSubConfigData('display_options/details_hide')) ? 'true' : 'false',			
			'HideMessage'				=> ($this->getSubConfigData('display_options/message_hide')) ? 'true' : 'false',
			'HideTooltips'				=> ($this->getSubConfigData('display_options/tooltip_hide')) ? 'true' : 'false',
			'UseNativeButtons'			=> ($this->getSubConfigData('display_options/native_buttons')) ? 'true' : 'false'),
		);
		if($this->getConfigData('trantype') == Merchantware_Merchantware_Model_Transactiontype::LEVEL2SALE){
			$_params['request_param'] = array_merge($_params['request_param'],array('CustomerCode'=>$_billing->getCustomerId(),'PoNumber'=>$_billing->getPostcode(),'TaxAmount'=>$this->_getTaxAmount()));
		}
		return $_params;
	}
	
	protected function _getInitPaymentXml($elements) {
		$_xml = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
        <soap:Body>
        <CreateTransaction xmlns="http://transport.merchantware.net/v4/">'.$this->_toSimpleFlatXml($elements['merchant_detail']).'<request>' . $this->_toSimpleFlatXml($elements['request_param']) . '<DisplayColors>' . $this->_toSimpleFlatXml($elements['DisplayColors']) . '</DisplayColors><DisplayOptions>'. $this->_toSimpleFlatXml($elements['DisplayOptions']) .'</DisplayOptions><EntryMode>Keyed</EntryMode></request>
        </CreateTransaction>
        </soap:Body>
    </soap:Envelope>';		
		return $_xml;
	}
	
	protected function _getServerUrl() {
		if($this->getConfigData('test_mode') == 1 && ($this->getConfigData('optimizedfor') == 'desktop' || $this->getConfigData('optimizedfor') == 'mobile') && $this->getConfigData('optimizedformode') == 'test') {
				return self::TEST_SERVER_URL;
			}
			return self::LIVE_SERVER_URL;
	}	
	
	protected function _getOrderId(){
		$info = $this->getInfoInstance();
		if ($this->_isPlaceOrder()) {
			return $info->getOrder()->getIncrementId();
		} else {
			if (!$info->getQuote()->getReservedOrderId()) {
				$info->getQuote()->reserveOrderId();
			}
			return $info->getQuote()->getReservedOrderId();
		}
	}
	
	protected function _toSimpleFlatXml($elements){
		$_xml = NULL;
		foreach($elements as $k => $v) {
			$v = is_array($v) ? $this->_toSimpleFlatXml($v) : $this->_xmlspecialchars($v);
			$_xml .= '<' . $k . '>' . $v . '</' . $k . '>';
		}
		return $_xml;
	}
	
	protected function _xmlspecialchars($text) {
		return str_replace('&#039;', '&apos;', htmlspecialchars($text, ENT_QUOTES));
	}
	
	protected function _getTaxAmount() {
		$info = $this->getInfoInstance();
		$_tax = 0;
		if ($this->_isPlaceOrder()) {
			$_tax = (double)$info->getOrder()->getBaseTaxAmount();
			$_tax = $this->_applyHiddenTaxWorkaround($_tax, $info->getOrder());
		} else {
			$address = $info->getQuote()->getIsVirtual() ? $info->getQuote()->getBillingAddress() : $info->getQuote()->getShippingAddress();
			$_tax = (double)$address->getBaseTaxAmount();
			$_tax = $this->_applyHiddenTaxWorkaround($_tax, $address);
		}
		return $_tax;
	}
	
	protected function _applyHiddenTaxWorkaround($_tax, $salesEntity) {
		$_tax += (float)$salesEntity->getBaseHiddenTaxAmount();
		$_tax += (float)$salesEntity->getBaseShippingHiddenTaxAmount();
		return $_tax;
	}
	
	protected function _getAmount() {
		$info = $this->getInfoInstance();
		if ($this->_isPlaceOrder()) {
			if((double)$info->getOrder()->getQuoteBaseGrandTotal()) {
				return (double)$info->getOrder()->getQuoteBaseGrandTotal();
			}else {
				return (double)$info->getOrder()->getBaseGrandTotal();
			}
		} else {
			return (double)$info->getQuote()->getBaseGrandTotal();
		}
	}
	
	protected function _getCustomerEmail() {
		$info = $this->getInfoInstance();
		if ($this->_isPlaceOrder()) {
			return $info->getOrder()->getCustomerEmail();
		} else {
			return $info->getQuote()->getCustomerEmail();
		}
	}
	
	protected function _getBillingAddress() {
		$info = $this->getInfoInstance();
		if ($this->_isPlaceOrder()) {
			return $info->getOrder()->getBillingAddress();
		} else {
			return $info->getQuote()->getBillingAddress();
		}
	}
	
	protected function _getBillingCountryCode() {
		$info = $this->getInfoInstance();
		if ($this->_isPlaceOrder()) {
			return $info->getOrder()->getBillingAddress()->getCountryId();
		} else {
			return $info->getQuote()->getBillingAddress()->getCountryId();
		}
	}
	
	protected function _getCurrencyCode(){
		$info = $this->getInfoInstance();
		if ($this->_isPlaceOrder()) {
			return $info->getOrder()->getBaseCurrencyCode();
		} else {
			return $info->getQuote()->getBaseCurrencyCode();
		}
	}
	
	protected function _isPlaceOrder() {
		$info = $this->getInfoInstance();
		if ($info instanceof Mage_Sales_Model_Quote_Payment) {
			return false;
		} elseif ($info instanceof Mage_Sales_Model_Order_Payment) {
			return true;
		}
	}
}
