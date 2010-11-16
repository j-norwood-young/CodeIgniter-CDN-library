CodeIgniter CDN class.

Version 0.1
15 November 2010

===Description=== 
Abstraction layer for integrating with multiple CDNs, primarily AWS S3 and Rackspace Cloudfiles, specifically formatted for CodeIgniter

===Requirements===
Rackspace and/or Amazon PHP API

The library is configured to find the API's under ./resources/cloud_services/cloudfiles/ for Rackspace and ./resources/cloud_services/aws/sdk.class.php for Amazon.

If you need to change this look for it in the code. I'll maybe make it a config option at some point.

You'll also need the stuff that the API's rely on, mostly PHP Curl.

You'll want PHP5 to run this as it uses OOP code.

===Authentication=== 
You can set the following in a config file in CodeIgniter:
 
 	//Rackspace credentials
	$config["rackspace_api_key"]="Your API Key";
	$config["rackspace_username"]="Your Username";
	
	//Amazon credentials
	$config["aws_key"]="Your AWS key";
	$config["aws_secret_key"]="Your super-secret Amazon key";
	
	//Choose between Rackspace and Amazon
	//You can use Amazon, AWS or S3 for Amazon. For Rackspace, set it to rackspace.
	$config["cdn_service"]="aws";
	
Alternatively you can pass the credentials on init(), and set the cdn_service on object creation.

===Bugs===

There are still some bugs and the Amazon interface is particularly slow. Both Rackspace's and Amazon's PHP libraries are terribly buggy, which doesn't help matters.

Bug fix for cloudfiles_http.php +- line 230
       $url_path = $this->_make_path("CDN")."/?enabled_only=true"; //Change this line
 
===Roadmap===
I'm using this library in 10Layer, so it'll get whatever features I need. If there's something you want, or you just want to let me know you're using it, or you've got a fix or something, just mail me.

Jason Norwood-Young 
jason@10layer.com
http://www.10layer.com
 
Copyright (c) 2010 Jason Norwood-Young

MIT License (see license.txt)