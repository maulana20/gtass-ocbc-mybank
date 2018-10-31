<?php
require_once 'Zend/Http/Client.php';

abstract class HostToHostIOModel
{
	protected $url;
	protected $client;
	
	abstract protected function loginClient($username, $password);
	abstract protected function logoutClient();
	
	//========================================
	// PRIVATE-FUNCTION
	//========================================
	//==========================================
	// ADD/EDIT/DELETE FUNCTION
	//==========================================
	protected function logResponse($file, $response)
	{
		$f = fopen($file, 'w');
		fwrite($f, $response->getHeadersAsString() . "\n" . $response->getBody());
		fclose($f);
	}

	public function start($data)
	{
		$this->curloptions = array(
			CURLOPT_SSL_VERIFYPEER => false,
		);
		$this->url = $data['url'];
		$this->createClient();
		$this->loginClient($data['username'], $data['password']);
		$this->saveClient();
	}
	
	protected function createClient()
	{
		$this->client = new Zend_Http_Client($this->url);
		$config = array('timeout' => 60,
						'ssltransport' => 'sslv3',
						'keepalive' => true,
					    'adapter'      => 'Zend_Http_Client_Adapter_Curl',
    					'persistent' => true,
						'curloptions' => $this->curloptions,
//						'useragent' => 'Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2062.120 Safari/537.36',
		);
		$this->client->setConfig($config);
		$this->client->setCookieJar();
		$this->client->setHeaders("Connection", "keep-alive");
	}
	
	protected function setClientTimeout($timeout = 60)
	{
		$config = array('timeout' => $timeout);
		$this->client->setConfig($config);
	}
	
	public function stop()
	{
		$this->logoutClient();
		$this->deleteClient();
	}

	protected function deleteClient()
	{
		if (file_exists('Interface/gtass.dat')) {
			unlink('Interface/gtass.dat');
		}
	}
	
	protected function saveClient()
	{
		$s = serialize($this->client);
		file_put_contents('Interface/gtass.dat', $s);
	}
	
	protected function getClientFromDatabase($file_name, $h2h_id)
	{
		$file_is_exists = file_exists('Interface/gtass.dat');
		if ($file_is_exists) {
			try {
				$s = file_get_contents('Interface/gtass.dat');
				$this->client = unserialize($s);
			} catch (Exception $e) {
				$s = false;
			}
		} else {
			$s = false;
		}
		
		if ($s === false) return false; else return true;
		
	}
	
	//========================================
	// COMPARE DATA FUNCTION RETURN BOOLEAN
	//========================================

	//========================================
	// GET DATA FUNCTION RETURN DATA
	//========================================

}

class GTASSModel extends HostToHostIOModel
{	
	function loginClient($username, $password)
	{
		$client = &$this->client;
		$host = $this->url;
		
		$client->resetParameters();
		$data = array();
		$data['username'] = $username;
		$data['password'] = $password;
		$client->setUri($host . '/login');
		$client->setParameterPost($data);
		try {
			$response = $client->request(Zend_Http_Client::POST);
			$result = $response->getBody();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", $e->getMessage());
			exit();
		}
		$this->logResponse("log/GTASSlogin.html", $response);
		
		$body = stristr($result, 'alert alert-danger alert-block');
		$body = stristr($body, 'validation-summary-errors');
		$body = stristr($body, '<li');
		$body = stristr($body, '>');
		$matches = substr($body, 1, strpos($body, '</li', 1)-1);

		if (strtolower($matches) == 'this user is logged on.') {
			$client->resetParameters();
			$data = array();
			$data['username'] = $username . '/force';
			$data['password'] = $password;
			$client->setUri($host . '/login');
			$client->setParameterPost($data);
			try {
				$response = $client->request(Zend_Http_Client::POST);
				$result = $response->getBody();
			} catch (Exception $e) {
				echo $e->getMessage();
				logRes("log/gtass_error.txt", $e->getMessage());
				exit();
			}
			$this->logResponse("log/GTASSloginforce.html", $response);
		}
	}
	
	function logoutClient()
	{
		$client = &$this->client;
		$host = $this->url;
		
		$client->resetParameters();
		$client->setUri($host . '/logout');
		try {
			$response = $client->request();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", $e->getMessage());
			exit();
		}
		$this->logResponse("log/GTASSLogout.html", $response);
	}
	
	function isSessionTimeout()
	{
		return true;
	}
		
	function addGeneralCb($data)
	{
		$client = &$this->client;
		$host = $this->url;
		
		$client->resetParameters();
		$client->setUri($host . '/api/file/template/general-cb/html');
		try {
			$response = $client->request();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", $e->getMessage());
			exit();
		}
		$this->logResponse("log/GTASSGeneralCb.html", $response);
		
		
		/*$data = array();
		$data['act'] = 'add';
		$data['amount'] = 0;
		$data['coaCode'] = '111501'; // option
		$data['code'] = '<AUTO>';
		$data['currCode'] = 'IDR';
		$data['date'] = '2018-07-25'; // option
		$data['descr'] = 'test';
		$data['issueBy'] = 3; // option
		$data['locationId'] = 1; // mandatory : Pusat
		$data['mark'] = 'A';
		$data['rate'] = 1;
		$data['type'] = 'D'; // option
		$data['ChequeNo'] = '';
		$data['chequeDate'] = '';
		*/
		$client->resetParameters();		
		$json = json_encode($data);
		$client->setUri($host . '/api/general-cashbank/update?act=add');
		$client->setRawData($json, 'application/json');
		try {
			$response = $client->request('POST');
			$result = $response->getBody();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", $e->getMessage());
			exit();
		}
		$this->logResponse("log/GTASSAddGeneralCb.html", $response);
	}
	
	function addDepositAgentCb($res, $coa_code)
	{
		$client = &$this->client;
		$host = $this->url;
		
		$client->resetParameters();
		$client->setUri($host . '/api/file/template/deposit-subagent-cb/html');
		try {
			$response = $client->request();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", $e->getMessage());
			exit();
		}
		$this->logResponse("log/GTASSDepositAgentCb.html", $response);
		
		$data = array();
		$data['act'] = 'add';
		$data['amount'] = $res['amount'];
		$data['coaCode'] = $coa_code;
		$data['code'] = '<AUTO>';
		$data['currCode'] = 'IDR';
		$data['custCode'] = 'C0100051'; // SUB AGENT VERSA
		$data['date'] = date('Y-m-d', $res['date']);
		$data['descr'] = substr($res['descr'], 0, 100);
		$data['issueBy'] = 3; // PT (Putut)
		$data['locationId'] = 1; // Pusat
		$data['rate'] = 1;
		$data['type'] = 'D'; // Mandatory
		$client->resetParameters();
		$json = json_encode($data);
		$client->setUri($host . '/api/depo-sa-cb/update?act=add');
		$client->setRawData($json, 'application/json');
		try {
			$response = $client->request('POST');
			$result = $response->getBody();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", $e->getMessage());
			exit();
		}
		$res_json = json_decode($result, true);
		if ($res_json['success'] != true) {
			logRes("log/gtass_error.txt", $res_json['message']);
		} else {
			logRes("log/gtass_success.txt", $res_json['message']);
		}
		
		$this->logResponse("log/GTASSAddDepositAgentCb.html", $response);
	}
	
	function isAlreadyDepAgent($res)
	{
		$dsareport_list = array();
		$dsareport_list = $this->getDepositAgentCb($res['date'], 'C0100051');
		foreach ($dsareport_list['data'] as $k => $v) {
			if ( ($v['descr'] == $res['descr']) && ($v['amount'] == $res['amount']) ) return true;
		}
		return false;
	}
	
	function isCoa($coa_code)
	{
		$coa_list = array();
		$coa_list = $this->getCOA($coa_code);
		foreach ($coa_list['data'] as $k => $v) {
			if ($v['code'] == $coa_code) return true;
		}
		return false;
	}
	
// GET DATA
	function getUser()
	{
		// USER CODE
		/************************************
		 * kode	* nama			* initial	*
		 ************************************
		 * 10	* AQDA			* AQ		*
		 *		* GSO			*	 		*
		 * 		* GSU			*			*
		 * 7	* hafsyahsuki	* hki		*
		 * 8	* lia			* lia		*
		 * 9	* ODET			* OD		*
		 * 3	* putut			* PT		*
		 * 4	* YOGO BUDIONO	* yg		*
		 * 6	* Yanti Nurmala	* YN		*
		 ************************************
		 */
		
		$client = &$this->client;
		$host = $this->url;
		
		$client->resetParameters();
		$data = array();
		$client->setUri($host . '/api/user/list');
		$client->setParameterPost($data);
		$response = $client->request(Zend_Http_Client::POST);
		$result = $response->getBody();
		
		$this->logResponse("log/GTASSUserList.html", $response);
	}
	
	function getDepartement()
	{
		// DEPARTEMENT CODE
		/********************************************
		 * kode	* nama					* initial	*
		 ********************************************
		 * 2	* AKUNTING				* ACC		*
		 * 8	* BUSINESS DEVELOPMENT	* BD	 	*
		 * 9	* BUSINESS ANALYST		* BS		*
		 * 1	* CEO					* CEO		*
		 * 4	* FINANCE				* FN		*
		 * 6	* IT SUPPORT			* IT		*
		 * 7	* MEDIA					* MD		*
		 * 5	* MARKETING				* MR		*
		 * 10	* OPERATION				* OP		*
		 * 3	* TRAVEL CONSULTAN		* TC		*		
		 ********************************************
		 */
		 
		$client = &$this->client;
		$host = $this->url;
		
		$client->resetParameters();
		$data = array();
		$client->setUri($host . '/api/department/list');
		$client->setParameterPost($data);
		$response = $client->request(Zend_Http_Client::POST);
		$result = $response->getBody();
		
		$this->logResponse("log/GTASSDepartementList.html", $response);
	}
	
	function getCOA($coa_code)
	{
		// COA CODE
		/********************************************
		 * kode		* description					*
		 ********************************************
		 * 111101	* Kas IDR						*
		 * 111110	* Kas USD						*
		 * 111501	* MANDIRI - 1210068900079  IDR	*
		 * 111502	* BCA - 270-0193881 IDR			*
		 * 111503	* BRI - 033801000680306  IDR	*
		 * 111504	* MAY BANK - 2427001160 IDR		*
		 * 111505	* OCBC - 133800000878 IDR		*
		 * 111506	* BCA - 5440307037  IDR			*
		 * 111507	* MANDIRI - 1210060608886 IDR	*
		 * 111508	* MANDIRI - 1210006595999 IDR	*
		 * 111509 	* MANDIRI - 1220004313048 IDR	*
		 * 111510	* BCA -: 544-0305450  IDR		*
		 * 111511	* MAY BANK - 2427003336  IDR	*
		 * 111512	* BCA - 545-0634567 IDR			*
		 * 111513	* BCA - 545-0240909  IDR		*
		 * 111514	* BCA -  544-0144561  IDR		*
		 * 111515	* BNI 46  - 5506677889  IDR		*
		 * 111516	* PERMATA BANK - 702040140  IDR	*
		 * 111517	* BANK MANDIRI - 1210008819991	*
		 * 111518	* BCA - 2700232088  IDR			*
		 * 111519	* OCBC - 133811232379  IDR		*
		 * 111520	* BNI 46  - 2342122017  IDR		*
		 * 111600	* Kas Perantara - IDR			*
		 ********************************************
		 */
		
		$client = &$this->client;
		$host = $this->url;
		
		$client->resetParameters();
		$data = array();
		$data['search'] = $coa_code;
		$data['take'] = 50;
		$data['skip'] = 0;
		$data['page'] = 1;
		$data['pageSize'] = 50;
		$client->setUri($host . '/api/coa/list');
		$client->setParameterPost($data);
		try {
			$response = $client->request(Zend_Http_Client::POST);
			$result = $response->getBody();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", $e->getMessage());
			exit();
		}
		$res_json = json_decode($result, true);
		
		$this->logResponse("log/GTASSCOAList.html", $response);
		
		return $res_json;
	}
	
	function getCustomer()
	{
		$client = &$this->client;
		$host = $this->url;
		
		$client->resetParameters();
		$data = array();
		$data['search'] = '';
		$data['take'] = 50;
		$data['skip'] = 0;
		$data['page'] = 1;
		$data['pageSize'] = 50;
		$client->setUri($host . '/api/customer/list');
		$client->setParameterPost($data);
		$response = $client->request(Zend_Http_Client::POST);
		$result = $response->getBody();
		
		$this->logResponse("log/GTASSCustomerList.html", $response);
	}
	
	function getDepositAgentCb($date, $cust_code)
	{
		$client = &$this->client;
		$host = $this->url;
		
		$client->resetParameters();
		$data = array();
		$data['search'] = date('d M Y', $date);
		$data['take'] = 10;
		$data['skip'] = 0;
		$data['page'] = 1;
		$data['pageSize'] = 1000;
		$data['custCode'] = $cust_code; //C0100051 : Sub Agent Versa
		$client->setUri($host . '/api/depo-sa-cb/list');
		$client->setParameterPost($data);
		try {
			$response = $client->request(Zend_Http_Client::POST);
			$result = $response->getBody();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", $e->getMessage());
			exit();
		}
		$res_json = json_decode($result, true);
		
		$this->logResponse("log/GTASSDepositAgentCbList.html", $response);
		
		return $res_json;
	}
	
	function getDsaReport($date, $cust_code)
	{
		$client = &$this->client;
		$host = $this->url;
		
		$client->resetParameters();
		$data = array();;
		$data['dateFrom'] = date('Y-m-d', $date);
		$data['dateTo'] = date('Y-m-d', $date);
		$data['loc'] = 1;
		$data['custCode'] = $cust_code; // C0100051 : Sub Agent Versa
		$client->setUri($host . '/api/dsa-report/lists');
		$client->setParameterPost($data);
		try {
			$response = $client->request(Zend_Http_Client::POST);
			$result = $response->getBody();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", $e->getMessage());
			exit();
		}
		$res_json = json_decode($result, true);
		
		$this->logResponse("log/GTASSDSAReportList.html", $response);
		
		return $res_json;
	}
}
