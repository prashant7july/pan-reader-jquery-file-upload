<?php
set_time_limit(0);
error_reporting(E_ALL | E_STRICT);

//www.ocrwebservice.com :: Use OCR RESTFul API for read Data on Indian PAN Image
defined('LICENSE_CODE') or define('LICENSE_CODE', 'D0FA3331-B69A-4E8D-A5B4-CFDABC07B3E7');
defined('USERNAME') or define('USERNAME', 'PRASHANTAHMD');

//defined('LICENSE_CODE') or define('LICENSE_CODE', 'ACBC0C10-5FCB-4258-A314-44813A98FA86');
//defined('USERNAME') or define('USERNAME', 'PRASHANTUCE');

defined('ENVIRONMENT') or define('ENVIRONMENT', '');
if (ENVIRONMENT == 'production') {
    // Production Database connection

} else if (ENVIRONMENT == 'pre-production') {
    // Pre-Production Database connection

} else if (ENVIRONMENT == 'testing' || ENVIRONMENT == 'uat') {
    // Testing or uat Database connection
	defined('HOST_NAME') or define('HOST_NAME', 'localhost');
	defined('USER_NAME') or define('USER_NAME', 'root');
	defined('USER_PASSWORD') or define('USER_PASSWORD', 'sqladmin');
	defined('DB_NAME') or define('DB_NAME', 'pan_module');
} else {
    // Development Database connection
	defined('HOST_NAME') or define('HOST_NAME', 'localhost');
	defined('USER_NAME') or define('USER_NAME', 'root');
	defined('USER_PASSWORD') or define('USER_PASSWORD', '');
	defined('DB_NAME') or define('DB_NAME', 'pan_module');
	defined('TABLE_NAME') or define('TABLE_NAME', 'files');
}
defined('TABLE_NAME') or define('TABLE_NAME', 'files');


require('UploadHandler.php');
class CustomUploadHandler extends UploadHandler {

    protected function initialize() {
        $this->db = new mysqli(
            $this->options['db_host'],
            $this->options['db_user'],
            $this->options['db_pass'],
            $this->options['db_name']
        );
        parent::initialize();
        $this->db->close();
    }

    protected function handle_file_upload($uploaded_file, $name, $size, $type, $error, $index = null, $content_range = null) {
        $file = parent::handle_file_upload(
            $uploaded_file, $name, $size, $type, $error, $index, $content_range
        );

        if (empty($file->error)) {

        	if (extension_loaded("curl")){
			    $this->read_data_pan_file_curl($file);
			    $panDataSting 	= serialize($file->panData);
			} else {
			    $panDataSting = "cURL extension is not available";
			}

			$sql = 'INSERT INTO `'.$this->options['db_table'] .'` (`name`, `size`, `type`, `pan_detail`)' .' VALUES (?, ?, ?, ?)';
            $query = $this->db->prepare($sql);
            $query->bind_param('siss',$file->name,$file->size,$file->type, $panDataSting); //siss means string, integer, string, string
            $query->execute();
            $file->id = $this->db->insert_id;
        }
        return $file;
    }

    protected function read_data_pan_file_curl($file) {

    	$upload_dir = $this->get_upload_path();
		$file->panData = new stdClass();
		// Full path to uploaded document
		$filePath = $upload_dir . $file->name;
		if (file_exists($filePath)) {
		  	$fp = @fopen($filePath, "r");
		} else {
			$file->panData->status  = 0;
			$file->panData->error 	= "File Not Exist";
			return ;
		}

		// Provide your user name and license code
		$license_code   = LICENSE_CODE;
		$username       = USERNAME;
		
		// Build your OCR:
		$url = 'http://www.ocrwebservice.com/restservices/processDocument?gettext=true&newline=1&language=english&tobw=true';
		$session = curl_init();

		curl_setopt($session, CURLOPT_URL, $url);
		// Provide user name and license code
		//curl_setopt($session, CURLOPT_USERPWD, "USERNAME:LICENSE_CODE");
		curl_setopt($session, CURLOPT_USERPWD, "$username:$license_code");

		curl_setopt($session, CURLOPT_UPLOAD, true);
		curl_setopt($session, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($session, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($session, CURLOPT_TIMEOUT, 200);
		curl_setopt($session, CURLOPT_HEADER, false);
		// Specify Response format to JSON or XML (application/json or application/xml)
		curl_setopt($session, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

		curl_setopt($session, CURLOPT_INFILE, $fp);
		curl_setopt($session, CURLOPT_INFILESIZE, filesize($filePath));

		$result = curl_exec($session);

		$httpCode = curl_getinfo($session, CURLINFO_HTTP_CODE);
		curl_close($session);
		fclose($fp);

		if($httpCode == 401) {
		   	// Please provide valid username and license code
			$file->panData->status  = 0;
			$file->panData->error 	= "Unauthorized request";
			return ;
		}

		// Output response
		$data = json_decode($result);
		if($httpCode != 200){
		   	// OCR error
			$file->panData->status  = 0;
			$file->panData->error 	= $data->ErrorMessage;
			return ;	
		}
		$file->panData->status  = 1;
		$file->panData->text 	= $data->OCRText[0][0];
    } 


	protected function read_data_pan_file_soap($file) {

    	$upload_dir = $this->get_upload_path();
		$file->panData = new stdClass();
		// Full path to uploaded document
		$filePath = $upload_dir . $file->name;
		if (file_exists($filePath)) {

			$client = new SoapClient("http://www.ocrwebservice.com/services/OCRWebService.asmx?WSDL", array("trace"=>1, "exceptions"=>1));

			$params = new StdClass();
			$params->user_name = 'PRASHANTAHMD';
			$params->license_code = 'D0FA3331-B69A-4E8D-A5B4-CFDABC07B3E7';

			$inimage = new StdClass();
		  	$fp = @fopen($filePath, "r");
			$card_image = fread($fp, filesize($filePath));
			fclose($fp);

			$inimage->fileName = $file->name;
			$inimage->fileData = $card_image;

			$params->OCRWSInputImage = $inimage;

			$settings = new StdClass();
			$settings->ocrLanguages = array("ENGLISH");
			$settings->outputDocumentFormat  = "TXT";
			$settings->convertToBW = TRUE;
			$settings->getOCRText = TRUE;
			$settings->createOutputDocument = FALSE;
			$settings->multiPageDoc = FALSE;
			$settings->ocrWords = FALSE;
			$settings->tobw = TRUE;

			$params->OCRWSSetting = $settings;

			try {
				$result = $client->OCRWebServiceRecognize($params);
			} catch (SoapFault $fault) {
				print($client->__getLastRequest());
				print($client->__getLastRequestHeaders());
			}
			var_dump($result->OCRWSResponse->ocrText->ArrayOfString->string);
			var_dump($result);
			print("Done");
			$file->panData->text  = $result->OCRWSResponse->ocrText->ArrayOfString->string ;

		} else {
			$file->panData->status  = 0;
			$file->panData->error 	= "File Not Exist";
		}
    } 

}

$options = array(
    'db_host' => HOST_NAME,
    'db_user' => USER_NAME,
    'db_pass' => USER_PASSWORD,
    'db_name' => DB_NAME,
    'db_table' => TABLE_NAME
);
$upload_handler = new CustomUploadHandler($options);