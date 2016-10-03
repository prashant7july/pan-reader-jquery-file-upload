<?php
/*
CREATE TABLE `files` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `size` int(11) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `pan_detail` text,
  `Created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
//http://www.w3schools.com/php/php_mysql_prepared_statements.asp
*/

set_time_limit(0);
error_reporting(E_ALL | E_STRICT);

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

	        $panDataArray 	= $this->read_data_pan_file($file);
	        $panDataSting 	= serialize($panDataArray);
	        $file->panData 	= $panDataArray;

			$sql = 'INSERT INTO `'.$this->options['db_table'] .'` (`name`, `size`, `type`, `pan_detail`)' .' VALUES (?, ?, ?, ?)';
            $query = $this->db->prepare($sql);
            $query->bind_param('siss',$file->name,$file->size,$file->type,$panDataSting); //siss means string, integer, string, string
            $query->execute();
            $file->id = $this->db->insert_id;
        }
        return $file;
    }

    protected function read_data_pan_file($file) {

    	$upload_dir = $this->get_upload_path();
		// Provide your user name and license code
		//$license_code   = 'D0FA3331-B69A-4E8D-A5B4-CFDABC07B3E7';
		//$username       =  'PRASHANTAHMD';

		$license_code   = 'ACBC0C10-5FCB-4258-A314-44813A98FA86';
		$username       = 'PRASHANTUCE';

		// Full path to uploaded document
		//$filePath = __DIR__ . '/imageedit_1_4587064404-rotate.jpg';
		$filePath = $upload_dir . $file->name;
		if (file_exists($filePath)) {
		  	$fp = @fopen($filePath, "r");
		} else {
			return $file->panData = array("status"=>0, "error"=>"File Not Exist");
		}

		// Build your OCR:
		$url = 'http://www.ocrwebservice.com/restservices/processDocument?gettext=true&newline=1&language=english&tobw=true';
		$session = curl_init();

		curl_setopt($session, CURLOPT_URL, $url);
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
			return $file->panData = array("status"=>0, "error"=>"Unauthorized request");
		}

		// Output response
		$data = json_decode($result);
		if($httpCode != 200){
		   	// OCR error
			return $file->panData = array("status"=>0, "error"=>$data->ErrorMessage);
			
		}
	    return $file->panData = array("status"=>1, "text"=>$data->OCRText[0][0]);
    } 

}

$options = array(
    'delete_type' => 'POST',
    'db_host' => 'localhost',
    'db_user' => 'root',
    'db_pass' => '',
    'db_name' => 'pan_module',
    'db_table' => 'files'
);
$upload_handler = new CustomUploadHandler($options);