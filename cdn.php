<?php

/**
 * CDN class.
 * 
 * Abstraction layer for integrating with multiple CDNs, primarily AWS S3 and Rackspace Cloudfiles, specifically formatted for CodeIgniter
 *
 * ===Requirements===
 * Rackspace and/or Amazon PHP API
 * It's configured to find the API's under 
 * ./resources/cloud_services/cloudfiles/ for Rackspace and 
 * ./resources/cloud_services/aws/sdk.class.php for Amazon.
 * If you need to change this look for it in the code. I'll maybe make it a config option at some point.
 * You'll also need the stuff that the API's rely on, mostly PHP Curl.
 *
 * ===Authentication=== 
 * You can set the following in a config file in CodeIgniter:
 * 
 	//Rackspace credentials
	$config["rackspace_api_key"]="Your API Key";
	$config["rackspace_username"]="Your Username";
	
	//Amazon credentials
	$config["aws_key"]="Your AWS key";
	$config["aws_secret_key"]="Your super-secret Amazon key";
	
	//Choose between Rackspace and Amazon
	//You can use Amazon, AWS or S3 for Amazon. For Rackspace, set it to rackspace.
	$config["cdn_service"]="aws";
 *	
 * Alternatively you can pass the credentials on init(), and set the cdn_service on object creation.
 *
 * ===Bugs===
 * There are still some bugs and the Amazon interface is particularly slow. Both Rackspace's and Amazon's PHP libraries are terribly buggy, 
 * which doesn't help matters.
 *
 *	Bug fix for cloudfiles_http.php +- line 230
 *       $url_path = $this->_make_path("CDN")."/?enabled_only=true"; //Change this line
 * 
 * ===Roadmap===
 * I'm using this library in 10Layer, so it'll get whatever features I need. If there's something you want, or you just
 * want to let me know you're using it, or you've got a fix or something, just mail me.
 * 
 * @author Jason Norwood-Young jason@10layer.com
 * @company 10Layer http://www.10layer.com
 * @licence MIT License
 * @version 0.1
 * @date 15 November 2010
 *
 */	
 
 /*
The MIT License
 
Copyright (c) 2010 Jason Norwood-Young

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*/
 
 class CDN {
 	private $_cdnobj;
 
 	public function __construct($args=array()) {
 		if (sizeof($args)==0) {
 			$ci=&get_instance();
 			$service=$ci->config->item("cdn_service");
 		} else {
 			if ((!isset($args["service"])) || (empty($args["service"]))) {
 				trigger_error("Set 'service' argument");
	 			return false;
 			}
 			$service=$args["service"];
 		}
 		if (empty($service)) {
 			trigger_error("No 'cdn_service' config item found and 'service' not passed as parameter");
	 		return false;
 		}
 		switch(strtolower($service))  {
 			case "rackspace": 
 				$this->_cdnobj=new RackspaceCDN();
 				break;
 			case "aws":
 			case "amazon":
 			case "s3":
 				$this->_cdnobj=new AmazonCDN();
 				break;
 			default:
 				trigger_error("CDN type not found");
 		}
 	}
 	
 	public function __call($method,$args) {
 		if (method_exists($this->_cdnobj,$method)) {
 			return call_user_func_array(array($this->_cdnobj,$method),$args);
 		}
 		trigger_error("Method $method not found");
 	}
 }

abstract class CDNAbstract {
	
	private $_conn;
	private $_bucket;
	private $_bucket_connected;
	private $_errormsg="";
	private $_error=false;
	
	/**
	 * Constructor
	 * 
	 * @access public
	 * @return void
	 */
	public function __contsruct() {
		$this->_conn=false;
		$this->_errormsg="";
		$this->_bucket=false;
		$this->_bucket_connected=false;
	}
	
	/**
	 * init function.
	 * 
	 * Creates a connection to the CDN. Returns true if successful.
	 *
	 * @access public
	 * @abstract
	 * @param bool $credentials. (default: false)
	 * @return boolean
	 */
	abstract public function init($credentials=false);
	
	/**
	 * connectBucket function.
	 * 
	 * This lets you skip putting the bucket name in each time for every method. Returns true if successful.
	 *
	 * @access public
	 * @abstract
	 * @param mixed $bucket
	 * @return boolean
	 */
	abstract public function connectBucket($bucket);
	
	/**
	 * listBuckets function.
	 *
	 * Returns an associative array as follows:
	 * $result[]=array(
	 *			"name"=>filename
	 *			"count"=>number of objects 
	 *			"size"=>total size of bucket in bytes 
	 *			"public"=>true if bucket is publicall available
	 *		);
	 * 
	 * @access public
	 * @abstract
	 * @return array Array of buckets
	 */
	abstract public function listBuckets();
	
	/**
	 * createBucket function.
	 * 
	 * Creates a bucket or container in the CDN - if it doesn't already exist. Returns true if successful.
	 *
	 * @access public
	 * @param string $bucket
	 * @return boolean
	 */
	abstract public function createBucket($bucketname,$public=true);
	
	/**
	 * deleteBucket function.
	 * 
	 * Deletes a bucket or container on the CDN. All your files will be lost! Returns true if successful.
	 *
	 * @access public
	 * @abstract
	 * @param string $bucket
	 * @return boolean
	 */
	abstract public function deleteBucket($bucket);
	
	/**
	 * deleteObject function.
	 * 
	 * @access public
	 * @abstract
	 * @param string $bucket
	 * @param string $filename
	 * @return boolean
	 */
	abstract public function deleteObject($filename,$bucket=false);
	
	/**
	 * listObjects function.
	 * 
	 * Returns an array of all the objects in a bucket or container. 
	 * $result[]=array(
	 *			"name"=>file name
	 *			"size"=>object size in bytes
	 *			"last_modified"=>date (unix time) of last modification
	 *			"url"=>the public url you can find this object at - for Rackspace, if it's not 
	 *				in a publically available container then it will be blank. For AWS, if it's not
	 *				publically available a temporary url will be created with a 5 minute expiration.
	 *			"content_type"=>content type, like "image/png" or whatever
	 *			"public"=>true if it's publically accessible (for Rackspace it's dependent on the container)
	 *			"is_folder"=>is this a folder (a 'path' in Rackspace nomenclature)
	 *		); 
	 *
	 * @access public
	 * @abstract
	 * @param string $bucket
	 * @return array
	 */
	abstract public function listObjects($bucket=false);
	
	/**
	 * uploadFile function.
	 *
	 * Upload a file to your CDN. Returns uri if successful, else returns false.
	 * 
	 * @access public
	 * @param string $filename
	 * @return string URI of new file
	 */
	abstract public function uploadFile($filename,$bucket=false);
	
	/**
	 * getFileContents function.
	 * 
	 * Gets the file contents from your CDN. Returns false if unsuccessful.
	 *
	 * @access public
	 * @abstract
	 * @param string $filename
	 * @return string
	 */
	abstract public function getFileContents($filename,$bucket=false);
	
	/**
	 * getContentType function.
	 * 
	 * Gets the content_type from your CDN for use in headers or wherever you need to know what you're dealing with. Returns false if unsuccessful.
	 *
	 * @access public
	 * @abstract
	 * @param string $filename
	 * @return string
	 */
	abstract public function getContentType($filename,$bucket=false);
	
	/**
	 * isPublic function.
	 * 
	 * Check if a bucket is public (true) or private (false)
	 *
	 * @access public
	 * @abstract
	 * @param string $bucket
	 * @return boolean
	 */
	abstract function isPublic($bucket=false);
		
	/**
	 * makePublic function.
	 * 
	 * Makes a bucket accessible to the world. Returns true if successful.
	 *
	 * @access public
	 * @abstract
	 * @param string $bucket
	 * @return boolean
	 */
	abstract public function makePublic($bucket=false);
	
	/**
	 * makePrivate function.
	 * 
	 * Makes a bucket private. Returns true if successful.
	 *
	 * @access public
	 * @abstract
	 * @param string $bucket
	 * @return boolean
	 */
	abstract public function makePrivate($bucket=false);
	
	/**
	 * lastError function.
	 * 
	 * Returns the last error, if any
	 *
	 * @access public
	 * @return string
	 */
	public function lastError() {
		return $this->_errormsg;
	}
	
	public function hasError() {
		return $this->_error;
	}
	
	protected function _setError($message) {
		$this->_errormsg=$message;
		$this->_error=true;
	}
}

class RackspaceCDN extends CDNAbstract {
	/*
	Bug fix for cloudfiles_http.php +- line 230
	function list_cdn_containers()
    {
        $url_path = $this->_make_path("CDN")."/?enabled_only=true"; //Change this line
    */
        
	private $_publiclist=false;
	private $_bucket_connected=false;
	private $_bucket_connected_name="";
	
	public function init($credentials=false) {
		include_once("./resources/cloud_services/cloudfiles/cloudfiles.php");
		if (empty($credentials)) {
			$ci=&get_instance();
			$api_key=$ci->config->item("rackspace_api_key");
			$username=$ci->config->item("rackspace_username");
		} else {
			$api_key=$credentials["rackspace_api_key"];
			$username=$credentials["rackspace_username"];
		}
		if (empty($api_key) || empty($username)) {
			trigger_error("Need Rackspace API Key and Username to initialise CDN");
		}
		$auth=new CF_Authentication($username,$api_key);
		$auth->authenticate();
		$this->_conn=new CF_Connection($auth);
	}
	
	public function listBuckets() {
		$cfbuckets=$this->_conn->get_containers();
		$result=array();
		foreach($cfbuckets as $cfbucket) {
			$public=$this->isPublic($cfbucket->name);
			$result[]=array(
				"name"=>$cfbucket->name, 
				"count"=>$cfbucket->object_count, 
				"size"=>$cfbucket->bytes_used, 
				"public"=>$public
			);
			//print_r($cfbucket);
		}
		return $result;
	}
	
	public function connectBucket($bucket) {
		if (!empty($bucket)) {
			if (($this->_bucket_connected_name!=$bucket)) {
				//print "COnnecting to $bucket ";
				$this->_bucket=$this->_conn->get_container($bucket);
				$this->_bucket_connected=true;
				$this->_bucket_connected_name=$bucket;
			}
		} else {
			if (!$this->_bucket_connected) {
				trigger_error("Not connected to any bucket $bucket");
			}
		}
	}
	
	public function isPublic($bucket=false) {
		/*$this->connectBucket($bucket);
		return $this->_bucket->is_public();*/
		// Some bug causing above to crash. Workaround:
		if (!is_array($this->_publiclist)) {
			$this->_publiclist=$this->_conn->list_public_containers();
		}
		return in_array($bucket,$this->_publiclist);
	}
	
	public function makePublic($bucket=false) {
		$this->connectBucket($bucket);
		$this->_bucket->make_public(86400); //1 day
		return true;
	}
	
	public function makePrivate($bucket=false) {
		$this->connectBucket($bucket);
		$this->_bucket->make_private();
		return true;
	}
	
	public function listObjects($bucket=false) {
		$this->connectBucket($bucket);
		$objs=$this->_getObjectList();
		$result=array();
		foreach($objs as $object) {	
			$filename=(String) $object->name;
			$url=$this->_getObjectUrl($filename);
			$public=$this->isPublic($bucket);
			$is_folder=false;
			$result[]=array(
				"name"=>$filename,
				"size"=>(String) $object->content_length,
				"last_modified"=>strtotime($object->last_modified),
				"url"=>$url,
				"content_type"=>$object->content_type,
				"public"=>$public,
				"is_folder"=>$is_folder
			);

		}
		return $result;
	}
	
	public function uploadFile($filename,$bucket=false) {
		$this->connectBucket($bucket);
		$obj=$this->_bucket->create_object(basename($filename));
		$obj->load_from_filename($filename);
		return $obj->public_uri();
	}
	
	public function createBucket($bucketname,$public=true) {
		if ($this->_bucketExists($bucketname)) {
			return false;
		}
		$this->_conn->create_container($bucketname);
		$maxcount=30; //Timeout if we don't get a bucket in x number of tries
		$count=0;
		while (!$this->_bucketExists($bucketname)) {
			// Not yet? Sleep for 2 seconds, then check again. 1 sec doesn't work so well with Rackspace
			sleep(2);
			$count++;
			if ($count>$maxcount) {
				return false;
			}
		}
		$container=$this->_conn->get_container($bucketname);
		if ($public) {
			$container->make_public();
		} else {
			$container->make_private();
		}
		return true;
	}
	
	public function deleteBucket($bucket) {
		$this->_conn->delete_container($bucket);
		return true;
	}
	
	public function deleteObject($filename, $bucket=false) {
		$this->connectBucket($bucket);
		$this->_bucket->delete_object($filename);
		return true;
	}
	
	public function getFileContents($filename,$bucket=false) {
		$this->connectBucket($bucket);
		$obj=$this->_bucket->get_object($filename);
		return $obj->read();
	}
	
	public function getContentType($filename,$bucket=false) {
		$this->connectBucket($bucket);
		$obj=$this->_bucket->get_object($filename);
		return $obj->content_type;
	}
		
	protected function _getObjectList() {
		return $this->_bucket->get_objects();
	}
	
	protected function _getObjectUrl($filename) {
		$obj=new CF_Object($this->_bucket, $filename);
		return $obj->public_uri();
	}
	
	protected function _bucketExists($bucketname) {
		$list=$this->_conn->list_containers();
		return in_array($bucketname,$list);
	}
	
}

class AmazonCDN extends CDNAbstract {

	public function init($credentials=false) {
		include_once("./resources/cloud_services/aws/sdk.class.php");
		if (empty($credentials)) {
			$ci=&get_instance();
			$api_key=$ci->config->item("aws_key");
			$secret_key=$ci->config->item("aws_secret_key");
		} else {
			$api_key=$credentials["aws_key"];
			$secret_key=$credentials["aws_secret_key"];
		}
		if (empty($api_key) || empty($secret_key)) {
			trigger_error("Need AWS API Key and Username to initialise CDN");
		}
		$this->_conn=new AmazonS3($api_key, $secret_key);
	}
	
	public function connectBucket($bucket) {
	}
	
	public function listBuckets() {
		$cfbuckets=$this->_conn->list_buckets();
		$result=array();
		foreach($cfbuckets->body->Buckets->Bucket as $cfbucket) {
			$bucketname=(String) $cfbucket->Name;
			$size=$this->_bucketSize($bucketname);
			$count=$this->_countObjects($bucketname);
			$public=$this->isPublic($bucketname);
			$result[]=array(
				"name"=>$bucketname, 
				"count"=>$count, 
				"size"=>$size, 
				"public"=>$public
			);
		}
		return $result;
	}
	
	public function isPublic($bucket=false, $filename=false) {
		if (!empty($filename)) {
			//Object - check and return meta data ACL
			$meta=$this->_getObjecteMedatdata($bucket,$filename);
			foreach($meta["ACL"] as $tmp) {
				if (($tmp["id"]==AmazonS3::USERS_ALL) && ($tmp["permission"]=="READ")) {
					return true;
				}
			}
			return false;
		}
		$this->_conn->hostname=AmazonS3::DEFAULT_URL;
		$this->_conn->vhost=null;
		$this->_conn->resource_prefix="";
		$acl=$this->_conn->get_bucket_acl($bucket);
		//print_r($acl);
		if (isset($acl->body->AccessControlList->Grant)) {
			foreach($acl->body->AccessControlList->Grant as $tmp) {
				if (($tmp->Grantee->URI==AmazonS3::USERS_ALL) && ($tmp->Permission=="READ")) {
					return true;
				}
			}
		}
		return false;
	}
	
	public function makePublic($bucket=false) {
		$this->_conn->set_bucket_acl($bucket, AmazonS3::ACL_PUBLIC);
		return true;
	}
	
	public function makePrivate($bucket=false) {
		$this->_conn->set_bucket_acl($bucket, AmazonS3::ACL_PRIVATE);
		return true;
	}
	
	public function listObjects($bucket=false) {
		$objs=$this->_conn->list_objects($bucket);
		$result=array();
		foreach($objs->body->Contents as $object) {
			$filename=(String) $object->Key;
			$meta=$this->_getObjecteMedatdata($bucket,$filename);
			$public=$this->isPublic($bucket,$filename);
			$url=$this->_getObjectUrl($bucket,$filename);
			$isfolder=$this->_isFolder($filename);
			$result[]=array(
				"name"=>$filename,
				"size"=>(String) $object->Size,
				"last_modified"=>strtotime($object->LastModified),
				"url"=>$url,
				"content_type"=>$meta["ContentType"],
				"public"=>$public,
				"is_folder"=>$isfolder
			);
		}
		return $result;
	}
	
	public function uploadFile($filename,$bucket=false) {
		$result=$this->_conn->create_object($bucket,basename($filename),array("fileUpload"=>$filename));
		if ($result->status==200) {
			$url=$result->header["x-aws-request-url"];
			//$url=$this->_getObjectUrl($bucket,basename($filename));
			return $url;
		}
		return false;
	}
	
	public function createBucket($bucketname,$public=true) {
		if ($this->_conn->if_bucket_exists($bucketname)) {
			//Already exists
			return false;
		}
		if ($public) {
			$acl=AmazonS3::ACL_PUBLIC;
		} else {
			$acl=AmazonS3::ACL_PRIVATE;
		}
		$bucketresult=$this->_conn->create_bucket($bucketname,AmazonS3::REGION_US_E1,$acl);
		//print_r($bucketresult);
		if (!$bucketresult->isOK()) {
			return false;
		}
		$exists = $this->_conn->if_bucket_exists($bucketname);
		$maxcount=10; //Timeout if we don't get a bucket in x number of tries
		$count=0;
		while (!$exists) {
			// Not yet? Sleep for 1 second, then check again
			sleep(1);
			$exists = $this->_conn->if_bucket_exists($bucketname);
			$count++;
			if ($count>$maxcount) {
				return false;
			}
		}
		return true;
	}
	
	public function deleteBucket($bucket) {
		$this->_conn->delete_bucket($bucket);
		return true;
	}
	
	public function deleteObject($filename, $bucket=false) {
		$this->_conn->delete_object($bucket,$filename);
		return true;
	}
	
	public function getFileContents($filename,$bucket=false) {
		$obj=$this->_conn->get_object($bucket,$filename);
		return $obj->body;
	}
	
	public function getContentType($filename,$bucket=false) {
		$meta=$this->_getObjecteMedatdata($bucket,$filename);
		return $meta["ContentType"];
	}
	
	protected function _bucketSize($bucket) {
		return $this->_conn->get_bucket_filesize($bucket);
	}
	
	protected function _countObjects($bucket) {
		return $this->_conn->get_bucket_object_count($bucket);
	}
	
	protected function _getObjectUrl($bucket, $filename) {
		//For Amazon, if the object is private we make it available for 5 mins for preview here
		if ($this->isPublic($bucket,$filename)) {
			return $this->_conn->get_object_url($bucket, $filename);
		} else {
			return $this->_conn->get_object_url($bucket, $filename, "+5 minutes");
		}
	}
	
	protected function _getObjecteMedatdata($bucket, $filename) {
		return $this->_conn->get_object_metadata($bucket, $filename);
	}
	
	protected function _isFolder($filename) {
		if (substr($filename,-1)=="/") {
			return true;
		}
		return false;
	}
}

?>
