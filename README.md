DigitalOcean PHP SDK + Command-line Interface
=============================================

A DigitalOcean PHP SDK that also comes with a feature-complete command-line interface that uses the SDK.  Full support for all DigitalOcean APIs.

[![Donate](https://cubiclesoft.com/res/donate-shield.png)](https://cubiclesoft.com/donate/)

Features
--------

* A single, simple PHP class for interfacing with the DigitalOcean API.  An SDK the way mother nature intended.
* Also comes with a complete, question/answer enabled command-line interface.  Nothing to compile.  Cross platform.
* Has a liberal open source license.  MIT or LGPL, your choice.
* Designed for relatively painless integration into your project.
* Sits on GitHub for all of that pull request and issue tracker goodness to easily submit changes and ideas respectively.

Getting Started
---------------

The easiest way to get started is to play with the command-line interface.  The command-line interface is question/answer enabled, which means all you have to do is run:

````
php do.php
````

That will enter interactive mode and guide you through the entire process.  The first time you run the tool, you can only 'setup' or use the Metadata API (i.e. this tool can be used on a Droplet).  During setup, you will see a screen like this:

````
The configuration file 'config.dat' does not exist.  Entering interactive configuration mode.


Available access token types:

  application:  Standard DigitalOcation OAuth2 application login.
  personal:     A permanent access token you manually set up in your DigitalOcean account.

Access token type [application]:
````

If you choose 'application' (the default), you will be asked to copy a specific URL into your web browser to sign in and authorize and then paste the destination URL back into the tool.  Configuration is a one-time process.

After setup, the world of DigitalOcean APIs opens up to you:

````
php do.php
````

Once you grow tired of manually entering information, you can pass in some or all of the answers to the questions on the command-line:

````
php do.php droplets list

php do.php droplets create

php do.php -s droplets create -name mydroplet -size s-1vcpu-1gb -backups N -ipv6 Y -private_network N -storage N -metadata '' -region nyc1 -image ubuntu-18-04-x64 -sshkey 123456 -sshkey 123457 -sshkey '' -wait Y
````

The -s option suppresses normal output (except for fatal error conditions), which allows for the processed JSON result to be the only thing that is output.

Related Software
----------------

When you set up/configure your Droplets, you might also find these related PHP-based tools useful:

* [CubicleSoft PHP SSH command-line tool](https://github.com/cubiclesoft/php-ssh) - SSH key generation, connection profile management, and SSH client
* [CubicleSoft PHP SSL certificate manager](https://github.com/cubiclesoft/php-ssl-certs) - SSL Certificate Signing Request (CSR) and certificate chain management tool
* [CubicleSoft Cloud Backup](https://github.com/cubiclesoft/cloud-backup) - Incremental off-site backup tool for sending encrypted, compressed data to cloud storage providers

Also, be sure to check out [the full CubicleSoft product line](http://cubiclesoft.com/).  Who knows?  You might find something interesting to use.  I'm a fairly prolific software developer.

Using the SDK
-------------

While the command-line tool can do everything the SDK can do, a few people will probably want/need additional flexibility.  For that, the SDK is available to program against.  The SDK itself is located in 'support\sdk_digitalocean.php'.  To get started, you will need to have a valid bearer token:

````
	require_once "support/sdk_digitalocean.php";

	$do = new DigitalOcean();
	// $do->SetDebug(true);  // Allows you to see raw debug output of various API calls.

	// Command-line interactive login:
	//   $result = $do->InteractiveLogin();
	//   if (!$result["success"])  CLI::DisplayError("An error occurred...", $result);
	//   $access_tokens = $do->GetAccessTokens();
	//   ...
	//   $do->AddAccessTokensUpdatedNotify("SaveAccessTokens");  // 'SaveAccessTokens' is the name of a callback function.
	// OR a personal access token:
	//   $access_tokens = $do->GetAccessTokens();
	//   $access_tokens["bearertoken"] = YOUR_PERSONAL_ACCESS_TOKEN
	$do->SetAccessTokens($access_tokens);
````

After you have set up your access token, you can start using the SDK with the various APIs:

````
	var_dump($do->AccountGetInfo());

	$options = array(
		"ssh_keys" => array("123456", "123457"),
		"backups" => false,
		"ipv6" => true,
		"private_networking" => false,
		"volumes" => array(),
		"user_data" => null
	);

	var_dump($do->DropletsCreate("awesome", "nyc1", "512mb", "ubuntu-16-04-x64", $options));
````

The command-line tool source code is an excellent source of example usage of the SDK.

SDK Variants
------------

Not everyone uses flat class SDKs.  That's okay.  There's an automated flavor of this SDK for everyone:

* [All CubicleSoft libraries](https://github.com/cubiclesoft/php-libs)
* [The above but inside a 'CubicleSoft' namespace](https://github.com/cubiclesoft/php-libs-namespaced)
* [The above, Composer-enabled](https://github.com/cubiclesoft/php-libs-to-composer)

You will, of course, have to adjust code accordingly.

DigitalOcean API Changelog
--------------------------

The API changes regularly.  You can safely assume that both this SDK and command-line tool mirrors the stable API (i.e. not in a beta program) as per the most recent commit date in this repository when compared to the DigitalOcean API changelog:

https://developers.digitalocean.com/documentation/changelog/
