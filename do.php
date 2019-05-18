<?php
	// DigitalOcean command-line shell for managing droplets.
	// (C) 2019 CubicleSoft.  All Rights Reserved.

	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	// Temporary root.
	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	require_once $rootpath . "/support/cli.php";
	require_once $rootpath . "/support/sdk_digitalocean.php";

	// Process the command-line options.
	$options = array(
		"shortmap" => array(
			"c" => "config",
			"d" => "debug",
			"s" => "suppressoutput",
			"?" => "help"
		),
		"rules" => array(
			"config" => array("arg" => true),
			"debug" => array("arg" => false),
			"suppressoutput" => array("arg" => false),
			"help" => array("arg" => false)
		),
		"allow_opts_after_param" => false
	);
	$args = CLI::ParseCommandLine($options);

	if (isset($args["opts"]["help"]))
	{
		echo "DigitalOcean management command-line tool\n";
		echo "Purpose:  Expose the DigitalOcean API to the command-line.  Also verifies correct SDK functionality.\n";
		echo "\n";
		echo "This tool is question/answer enabled.  Just running it will provide a guided interface.  It can also be run entirely from the command-line if you know all the answers.\n";
		echo "\n";
		echo "Syntax:  " . $args["file"] . " [options] [apigroup api [apioptions]]\n";
		echo "Options:\n";
		echo "\t-c   Specify a configuration file to use.  If it doesn't exist, it will be created.  Default is config.dat.\n";
		echo "\t-d   Enable raw API debug mode.  Dumps the raw data sent and received on the wire.\n";
		echo "\t-s   Suppress most output.  Useful for capturing JSON output.\n";
		echo "\n";
		echo "Examples:\n";
		echo "\tphp " . $args["file"] . "\n";
		echo "\tphp " . $args["file"] . " droplets create -name test\n";
		echo "\tphp " . $args["file"] . " -c altconfig.dat -s account get-info\n";

		exit();
	}

	function SaveConfig($configfile, $config)
	{
		file_put_contents($configfile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		chmod($configfile, 0660);
	}

	$suppressoutput = (isset($args["opts"]["suppressoutput"]) && $args["opts"]["suppressoutput"]);

	// Get the API group.
	$configfile = (isset($args["opts"]["config"]) ? $args["opts"]["config"] : $rootpath . "/config.dat");
	if (!file_exists($configfile))
	{
		$apigroups = array(
			"setup" => "Configure the tool",
			"metadata-droplet" => "Droplet metadata"
		);

		$default = "setup";
	}
	else
	{
		$apigroups = array(
			"account" => "Account",
			"actions" => "Actions",
			"volumes" => "Volumes (Block storage)",
			"volume-actions" => "Volume actions (Block storage)",
			"cdn-endpoints" => "Content Delivery Network (CDN) endpoints",
			"certificates" => "Certificates (SSL/TLS)",
			"database-clusters" => "Database clusters",
			"database-replicas" => "Database read-only replicas",
			"database-users" => "Database users",
			"databases" => "Databases in a database cluster",
			"database-pools" => "Database connection pools",
			"domains" => "Domains (DNS)",
			"domain-records" => "Domain records (DNS)",
			"droplets" => "Droplets",
			"droplet-actions" => "Droplet actions",
			"firewalls" => "Firewalls",
			"images" => "Images",
			"image-actions" => "Image actions",
			"load-balancers" => "Load balancers",
			"projects" => "Projects",
			"project-resources" => "Project resources",
			"snapshots" => "Snapshots",
			"ssh-keys" => "SSH keys",
			"regions" => "Regions",
			"sizes" => "Sizes",
			"floating-ips" => "Floating IPs",
			"floating-ip-actions" => "Floating IP actions",
			"tags" => "Tags",
			"oauth" => "OAuth",
			"metadata-droplet" => "Droplet metadata"
		);

		$default = false;
	}

	$apigroup = CLI::GetLimitedUserInputWithArgs($args, false, "API group", $default, "Available API groups:", $apigroups, true, $suppressoutput);

	// Get the API.
	switch ($apigroup)
	{
		case "account":  $apis = array("get-info" => "Get account information");  break;
		case "actions":  $apis = array("list" => "List actions", "get-info" => "Get information about an action", "wait" => "Wait for an action to complete");  break;
		case "volumes":  $apis = array("list" => "List Block Storage volumes", "create" => "Create a Block Storage volume", "get-info" => "Get information about a Block Storage volume", "snapshots" => "List all Block Storage volume snapshots", "snapshot" => "Create a Block Storage volume snapshot", "delete" => "Delete a Block Storage volume");  break;
		case "volume-actions":  $apis = array("attach" => "Attach a Block Storage volume to a Droplet", "detach" => "Detach a Block Storage volume from a Droplet", "resize" => "Enlarge a Block Storage volume");  break;
		case "cdn-endpoints":  $apis = array("list" => "List CDN endpoints", "create" => "Create a CDN endpoint", "update" => "Update a CDN endpoint", "get-info" => "Get information about a CDN endpoint", "purge" => "Purge CDN endpoint cache", "delete" => "Delete a CDN endpoint");  break;
		case "certificates":  $apis = array("list" => "List registered SSL certificates", "create" => "Create/Upload a SSL certificate", "get-info" => "Get information about a SSL certificate", "delete" => "Delete a SSL certificate");  break;
		case "database-clusters":  $apis = array("list" => "List database clusters", "create" => "Create a database cluster", "resize" => "Resize a database cluster", "migrate" => "Migrate a database cluster to another region", "maintenance" => "Configure the automatic maintenance window for a database cluster", "backups" => "List available backups for a database cluster", "restore" => "Restore from a backup into a new database cluster", "get-info" => "Get information about a database cluster", "delete" => "Delete a database cluster and all associated databases, tables, and users");  break;
		case "database-replicas":  $apis = array("list" => "List read-only database replicas", "create" => "Create a read-only database replica", "get-info" => "Get information about a read-only database replica", "delete" => "Delete a read-only database replica");  break;
		case "database-users":  $apis = array("list" => "List database users", "create" => "Create a database user", "get-info" => "Get information about a database user", "delete" => "Delete a database user");  break;
		case "databases":  $apis = array("list" => "List databases", "create" => "Create a database", "get-info" => "Get information about a database", "delete" => "Delete a database");  break;
		case "database-pools":  $apis = array("list" => "List database connection pools", "create" => "Create a database connection pool", "get-info" => "Get information about a database connection pool", "delete" => "Delete a database connection pool");  break;
		case "domains":  $apis = array("list" => "List registered domains (DNS)", "create" => "Create a domain (TLD)", "get-info" => "Get information about a domain", "delete" => "Delete a domain and all domain records");  break;
		case "domain-records":  $apis = array("list" => "List DNS records for a domain", "create" => "Create a domain record (A, AAAA, etc.)", "update" => "Update a domain record", "get-info" => "Get information about a domain record", "delete" => "Delete a domain record");  break;
		case "droplets":  $apis = array("list" => "List Droplets", "create" => "Create a new Droplet", "get-info" => "Get information about a Droplet", "kernels" => "List all available kernels for a Droplet", "snapshots" => "List all Droplet snapshots", "backups" => "List all Droplet backups", "actions" => "List Droplet actions", "delete" => "Delete a single Droplet", "delete-by-tag" => "Delete all Droplets with a specific tag", "neighbors" => "List neighbors for a Droplet", "all-neighbors" => "List all neighbors for all Droplets");  break;
		case "droplet-actions":  $apis = array("enable-backups" => "Enable backups", "disable-backups" => "Disable backups", "reboot" => "Reboot (gentle)", "power-cycle" => "Power cycle (hard reset)", "shutdown" => "Shutdown (gentle)", "power-off" => "Power off (forced shutdown)", "power-on" => "Power on", "restore" => "Restore from a backup image", "password-reset" => "Password reset", "resize" => "Resize Droplet", "rebuild" => "Recreate/Rebuild Droplet with a specific image", "rename" => "Rename", "change-kernel" => "Change Droplet kernel", "enable-ipv6" => "Enable IPv6 support", "enable-private-networking" => "Enable Shared Private Networking", "snapshot" => "Create a snapshot image");  break;
		case "firewalls":  $apis = array("list" => "List firewalls", "create" => "Create a firewall", "update" => "Update a firewall", "add-tag" => "Adds a tag to a firewall", "remove-tag" => "Removes a tag from a firewall", "add-droplet" => "Adds a Droplet to a firewall", "remove-droplet" => "Removes a Droplet from a firewall", "get-info" => "Get information about a firewall", "delete" => "Delete a firewall");  break;
		case "images":  $apis = array("list" => "List images (snapshots, backups, etc.)", "create" => "Create a custom image", "actions" => "List image actions", "rename" => "Rename image", "delete" => "Delete image", "get-info" => "Get information about an image");  break;
		case "image-actions":  $apis = array("transfer" => "Transfer/Copy an image to another region", "convert" => "Convert a backup to a snapshot");  break;
		case "load-balancers":  $apis = array("list" => "List load balancers", "create" => "Create a load balancer", "update" => "Update a load balancer", "add-forwarding-rule" => "Adds a forwarding rule to a load balancer", "remove-forwarding-rule" => "Removes a forwarding rule from a load balancer", "add-droplet" => "Adds a Droplet to a load balancer", "remove-droplet" => "Removes a Droplet from a load balancer", "get-info" => "Get information about a load balancer", "delete" => "Delete a load balancer");  break;
		case "projects":  $apis = array("list" => "List projects", "create" => "Create a project", "update" => "Update a project", "get-info" => "Get information about a project");  break;
		case "project-resources":  $apis = array("list" => "List resources", "assign" => "Assign a resource to a project");  break;
		case "snapshots":  $apis = array("list" => "List snapshots", "get-info" => "Get information about a snapshot", "delete" => "Delete a snapshot");  break;
		case "ssh-keys":  $apis = array("list" => "List SSH public keys in DigitalOcean account", "create" => "Add a SSH public key to your account", "get-info" => "Get information about a SSH public key", "rename" => "Rename a SSH public key", "delete" => "Remove a SSH public key from your account");  break;
		case "regions":  $apis = array("list" => "List all regions");  break;
		case "sizes":  $apis = array("list" => "List all Droplet sizes");  break;
		case "floating-ips":  $apis = array("list" => "List your Floating IPs", "create" => "Create a new Floating IP", "get-info" => "Get information about a Floating IP", "actions" => "List Floating IP actions", "delete" => "Delete a Floating IP");  break;
		case "floating-ip-actions":  $apis = array("assign" => "Assign a Floating IP to a Droplet", "unassign" => "Unassign a Floating IP");  break;
		case "tags":  $apis = array("list" => "List tags and associated resources", "create" => "Create a tag", "get-info" => "Get information about a tag", "attach" => "Attach a tag to one or more resources", "detach" => "Detach one or more resources from a tag", "delete" => "Delete a tag");  break;
		case "oauth":  $apis = array("revoke" => "Self-revoke API access");  break;
		case "metadata-droplet":  $apis = array("get-info" => "Get information about the Droplet (see Metadata API and Cloud Config tutorials)");  break;
	}

	if ($apigroup !== "setup")  $api = CLI::GetLimitedUserInputWithArgs($args, false, "API", false, "Available APIs:", $apis, true, $suppressoutput);

	function DisplayResult($result, $wait = true, $defaultwait = 5, $initwait = array())
	{
		global $do;

		if (is_array($result) && isset($result["success"]) && isset($result["actions"]) && $result["success"] && $wait)
		{
			foreach ($result["actions"] as $num => $action)
			{
				$result["actions"][$num]["result"] = $do->WaitForActionCompletion($action["id"], $defaultwait, $initwait, "ActionWaitHandler");
			}
		}

		echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

		exit();
	}

	$do = new DigitalOcean();

	if (isset($args["opts"]["debug"]) && $args["opts"]["debug"])  $do->SetDebug(true);

	if ($apigroup === "metadata-droplet")
	{
		if (!$suppressoutput)  echo "The metadata API is intended to be used from the droplet itself.\n";

		if ($api === "get-info")  DisplayResult($do->MetadataDropletGetInfo());
	}

	// Load the configuration file.
	if (!file_exists($configfile))
	{
		echo "\n\n---------------------\n\nThe configuration file '" . $configfile . "' does not exist.  Entering interactive configuration mode.\n\n";

		$tokentypes = array(
			"application" => "Standard DigitalOcation OAuth2 application login.",
			"personal" => "A permanent access token you manually set up in your DigitalOcean account."
		);
		$mode = CLI::GetLimitedUserInputWithArgs($args, false, "Access token type", "application", "Available access token types:", $tokentypes);

		$config = array();

		if ($mode === "application")
		{
			echo "\n";
			$result = $do->InteractiveLogin();
			if (!$result["success"])  CLI::DisplayError("An error occurred while performing the interactive login.", $result);

			$config["access_tokens"] = $do->GetAccessTokens();
		}
		else
		{
			$config["access_tokens"] = $do->GetAccessTokens();
			$config["access_tokens"]["bearertoken"] = CLI::GetUserInputWithArgs($args, false, "Personal Access Token (from API -> Tokens)", false);
			if ($config["access_tokens"]["bearertoken"] === "")  CLI::DisplayError("Personal Access Token was not supplied.");
		}

		SaveConfig($configfile, $config);

		echo "\n";
		echo "Configuration file written to '" . $configfile . "'.\n\n";
	}

	$config = json_decode(file_get_contents($configfile), true);
	if (!is_array($config))  CLI::DisplayError("The configuration file '" . $configfile . "' is corrupt.  Try deleting the configuration file and running the command again.");

	$do->SetAccessTokens($config["access_tokens"]);

	// Callback function for saving updated tokens.
	function SaveAccessTokens($do)
	{
		global $configfile, $config;

		$config["access_tokens"] = $do->GetAccessTokens();

		SaveConfig($configfile, $config);
	}

	$do->AddAccessTokensUpdatedNotify("SaveAccessTokens");

	function ActionWaitHandler($first, $result, $opts)
	{
		if ($first)  fwrite(STDERR, "Waiting for action '" . $result["action"]["id"] . "' to complete...");
		else if ($result["action"]["status"] === "completed")  fwrite(STDERR, "\n");
		else  fwrite(STDERR, ".");
	}

	function GetVolumeID()
	{
		global $suppressoutput, $args, $do;

		if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "id"))
		{
			$id = CLI::GetUserInputWithArgs($args, "id", "Volume ID", false, "", $suppressoutput);

			$result = $do->VolumesGetInfo($id);
			if (!$result["success"])  DisplayResult($result);
		}
		else
		{
			$result = $do->VolumesList();
			if (!$result["success"])  DisplayResult($result);

			$volumes = array();
			$volumes2 = array();
			foreach ($result["data"] as $volume)
			{
				$volumes[$volume["id"]] = $volume["region"]["slug"] . ", " . $volume["name"] . ", " . $volume["size_gigabytes"] . " GB";
				$volumes2[$volume["id"]] = $volume;
			}
			if (!count($volumes))  CLI::DisplayError("No Block Storage volumes have been created.  Try creating your first Block Storage volume with the API:  volumes create");
			$id = CLI::GetLimitedUserInputWithArgs($args, "id", "Volume ID", false, "Available Block Storage volumes:", $volumes, true, $suppressoutput);
			unset($result["data"]);
			$result["volume"] = $volumes2[$id];
		}

		return $result;
	}

	function GetVolumeSnapshotID($volumeid)
	{
		global $suppressoutput, $args, $do;

		if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "snapshot"))  $id = CLI::GetUserInputWithArgs($args, "snapshot", "Snapshot ID", false, "", $suppressoutput);
		else
		{
			$result = $do->VolumesSnapshotsList($volumeid);
			if (!$result["success"])  DisplayResult($result);

			$snapshots = array();
			foreach ($result["data"] as $snapshot)
			{
				$snapshots[$snapshot["id"]] = $snapshot["name"] . ", " . $snapshot["size_gigabytes"] . " GB (" . implode(", ", $snapshot["regions"]) . ")";
			}
			if (!count($snapshots))  CLI::DisplayError("No Block Storage volume snapshots have been created.  Try creating your first Block Storage volume snapshot with the API:  volumes snapshot");
			$id = CLI::GetLimitedUserInputWithArgs($args, "snapshot", "Snapshot ID", false, "Available Block Storage volume snapshots:", $snapshots, true, $suppressoutput);
		}

		return $id;
	}

	function GetCDNEndpointID()
	{
		global $suppressoutput, $args, $do;

		if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "id"))  $id = CLI::GetUserInputWithArgs($args, "id", "CDN endpoint ID", false, "", $suppressoutput);
		else
		{
			$result = $do->CDNEndpointsList();
			if (!$result["success"])  DisplayResult($result);

			$cdns = array();
			foreach ($result["data"] as $cdn)
			{
				$cdns[$cdn["id"]] = $cdn["origin"] . ($cdn["custom_domain"] !== "" ? " (" . $cdn["custom_domain"] . ")" : "");
			}
			if (!count($cdns))  CLI::DisplayError("No CDN endpoints have been created.  Try creating your first CDN endpoint with the API:  cdn-endpoints create");
			$id = CLI::GetLimitedUserInputWithArgs($args, "id", "CDN endpoint ID", false, "Available CDN endpoints:", $cdns, true, $suppressoutput);
		}

		return $id;
	}

	function GetCertificateID($default = false, $certificates = array())
	{
		global $suppressoutput, $args, $do;

		if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "id"))  $id = CLI::GetUserInputWithArgs($args, "id", "Certificate ID", $default, "", $suppressoutput);
		else
		{
			$result = $do->CertificatesList();
			if (!$result["success"])  DisplayResult($result);

			foreach ($result["data"] as $certificate)
			{
				$certificates[$certificate["id"]] = $certificate["name"] . " (" . implode(", ", $certificate["dns_names"]) . ")";
			}
			if (!count($certificates))  CLI::DisplayError("No SSL certificates have been created.  Try creating your first SSL certificate with the API:  certificates create");
			$id = CLI::GetLimitedUserInputWithArgs($args, "id", "Certificate ID", $default, "Available SSL certificates:", $certificates, true, $suppressoutput);
		}

		return $id;
	}

	function GetDatabaseClusterID()
	{
		global $suppressoutput, $args, $do;

		if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "id"))  $id = CLI::GetUserInputWithArgs($args, "id", "Database cluster ID", false, "", $suppressoutput);
		else
		{
			$result = $do->DatabaseClustersList();
			if (!$result["success"])  DisplayResult($result);

			$clusters = array();
			foreach ($result["data"] as $cluster)
			{
				$clusters[$cluster["id"]] = $cluster["name"];
			}
			if (!count($clusters))  CLI::DisplayError("No database clusters have been created.  Try creating your first database cluster with the API:  database-clusters create");
			$id = CLI::GetLimitedUserInputWithArgs($args, "id", "Database cluster ID", false, "Available database clusters:", $clusters, true, $suppressoutput);
		}

		return $id;
	}

	function GetDatabaseUsername($id)
	{
		global $suppressoutput, $args, $do;

		if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "name"))  $name = CLI::GetUserInputWithArgs($args, "name", "Database username", false, "", $suppressoutput);
		else
		{
			$result = $do->DatabaseUsersList($id);
			if (!$result["success"])  DisplayResult($result);

			$users = array();
			foreach ($result["data"] as $user)
			{
				$users[$user["name"]] = $user["name"] . " (" . $user["role"] . ")";
			}
			if (!count($users))  CLI::DisplayError("No database users have been created.  Try creating your first database user with the API:  database-users create");
			$name = CLI::GetLimitedUserInputWithArgs($args, "name", "Database username", false, "Available database users:", $users, true, $suppressoutput);
		}

		return $name;
	}

	function GetDatabaseName($id)
	{
		global $suppressoutput, $args, $do;

		if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "name"))  $name = CLI::GetUserInputWithArgs($args, "name", "Database name", false, "", $suppressoutput);
		else
		{
			$result = $do->DatabasesList($id);
			if (!$result["success"])  DisplayResult($result);

			$databases = array();
			foreach ($result["data"] as $db)
			{
				$databases[$db["name"]] = $db["name"];
			}
			if (!count($databases))  CLI::DisplayError("No databases have been created.  Try creating your first database with the API:  database create");
			$name = CLI::GetLimitedUserInputWithArgs($args, "name", "Database", false, "Available databases:", $databases, true, $suppressoutput);
		}

		return $name;
	}

	function GetDatabasePoolName($id)
	{
		global $suppressoutput, $args, $do;

		if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "name"))  $name = CLI::GetUserInputWithArgs($args, "name", "Database connection pool", false, "", $suppressoutput);
		else
		{
			$result = $do->DatabasePoolsList($id);
			if (!$result["success"])  DisplayResult($result);

			$pools = array();
			foreach ($result["data"] as $pool)
			{
				$pools[$pool["name"]] = $pool["mode"] . ", " . $pool["size"] . " max connections, " . $pool["db"] . ", " . $pool["user"];
			}
			if (!count($pools))  CLI::DisplayError("No database connection pools have been created.  Try creating your first database connection pool with the API:  database-pools create");
			$name = CLI::GetLimitedUserInputWithArgs($args, "name", "Database connection pool", false, "Available database connection pools:", $pools, true, $suppressoutput);
		}

		return $name;
	}

	function GetDomainName($arg)
	{
		global $suppressoutput, $args, $do;

		if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, $arg))  $domainname = CLI::GetUserInputWithArgs($args, $arg, "Domain name (TLD, no subdomains)", false, "", $suppressoutput);
		else
		{
			$result = $do->DomainsList();
			if (!$result["success"])  DisplayResult($result);

			$domainnames = array();
			foreach ($result["data"] as $domain)  $domainnames[] = $domain["name"];
			if (!count($domainnames))  CLI::DisplayError("No domains have been created.  Try creating your first domain with the API:  domains create");
			$domainname = CLI::GetLimitedUserInputWithArgs($args, $arg, "Domain name (TLD, no subdomains)", false, "Available domain names:", $domainnames, true, $suppressoutput);
			$domainname = $domainnames[$domainname];
		}

		return $domainname;
	}

	function GetDomainNameRecord($domainname)
	{
		global $suppressoutput, $args, $do;

		if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "id"))
		{
			$id = CLI::GetUserInputWithArgs($args, "id", "Domain record ID", false, "", $suppressoutput);

			$result = $do->DomainRecordsGetInfo($domainname, $id);
			if (!$result["success"])  DisplayResult($result);
		}
		else
		{
			$result = $do->DomainRecordsList($domainname);
			if (!$result["success"])  DisplayResult($result);

			$ids = array();
			$ids2 = array();
			foreach ($result["data"] as $record)
			{
				$ids[$record["id"]] = $record["type"] . " | " . $record["name"] . " | " . $record["data"] . (isset($record["priority"]) ? " | " . $record["priority"] : "") . (isset($record["port"]) ? " | " . $record["port"] : "") . (isset($record["weight"]) ? " | " . $record["weight"] : "");
				$ids2[$record["id"]] = $record;
			}
			if (!count($ids))  CLI::DisplayError("No domain records have been created.  Try creating your first domain record with the API:  domain-records create");
			$id = CLI::GetLimitedUserInputWithArgs($args, "id", "Domain record ID", false, "Available domain records:", $ids, true, $suppressoutput);
			unset($result["data"]);
			$result["record"] = $ids2[$id];
		}

		return $result;
	}

	function GetDropletSize($info = "")
	{
		global $suppressoutput, $args, $do;

		if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "size"))
		{
			$size = CLI::GetUserInputWithArgs($args, "size", "Droplet size", false, $info, $suppressoutput);
			$disksize = false;
		}
		else
		{
			$result = $do->SizesList();
			if (!$result["success"])  DisplayResult($result);

			$sizes = array();
			$sizes2 = array();
			foreach ($result["data"] as $size)
			{
				if ($size["available"])  $sizes[$size["slug"]] = $size["memory"] . "MB RAM, " . $size["vcpus"] . ($size["vcpus"] == 1 ? " vCore, " : " vCores, ") . $size["disk"] . "GB disk, " . $size["transfer"] . "TB transfer\n\t\$" . $size["price_monthly"] . " USD/month OR \$" . $size["price_hourly"] . " USD/hour\n";
				$sizes2[$size["slug"]] = $size;
			}
			if (!count($sizes))  CLI::DisplayError("No Droplet sizes are available.  Is your account in good standing and does your account have a valid credit card on file?");
			$size = CLI::GetLimitedUserInputWithArgs($args, "size", "Droplet size", false, ($info !== "" ? $info . "  " : "") . "Available Droplet sizes:", $sizes, true, $suppressoutput);
			$disksize = $sizes2[$size]["disk"];
		}

		return array("size" => $size, "disksize" => $disksize);
	}

	function GetDropletRegion($question, $size, $backups, $ipv6, $privatenetwork, $storage, $userdata)
	{
		global $suppressoutput, $args, $do;

		if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "region"))  $region = CLI::GetUserInputWithArgs($args, "region", $question, false, "", $suppressoutput);
		else
		{
			$result = $do->RegionsList();
			if (!$result["success"])  DisplayResult($result);

			$regions = array();
			foreach ($result["data"] as $region)
			{
				if ($region["available"] && ($size === false || in_array($size, $region["sizes"])) && (!$backups || in_array("backups", $region["features"])) && (!$ipv6 || in_array("ipv6", $region["features"])) && (!$privatenetwork || in_array("private_networking", $region["features"])) && (!$storage || in_array("storage", $region["features"])) && ($userdata === "" || in_array("metadata", $region["features"])))
				{
					$regions[$region["slug"]] = $region["name"];
				}
			}
			if (!count($regions))  CLI::DisplayError("No suitable regions are available.  Is your account in good standing and does your account have a valid credit card on file?");
			$region = CLI::GetLimitedUserInputWithArgs($args, "region", $question, false, "Available regions:", $regions, true, $suppressoutput);
		}

		return $region;
	}

	function GetDropletImage($question, $defaultval, $disksize, $region, $useronly = false, $matchtype = false)
	{
		global $suppressoutput, $args, $do;

		if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "image"))
		{
			$image = CLI::GetUserInputWithArgs($args, "image", $question, false, "", $suppressoutput);

			$result = $do->ImagesGetInfo($image);
			if (!$result["success"] || ($useronly && $image["public"]))  DisplayResult($result);
			$result["id"] = $image;
		}
		else
		{
			$images = array();
			$imagemap = array();
			if ($useronly)  $extras = array("?private=true" => "private");
			else  $extras = array("?type=distribution" => "distribution", "?type=application" => "application", "?private=true" => "private");
			foreach ($extras as $extra => $type)
			{
				$result = $do->ImagesList(true, $extra);
				if (!$result["success"])  DisplayResult($result);

				$images2 = array();
				foreach ($result["data"] as $image)
				{
					if (($disksize === false || $disksize <= $image["min_disk_size"]) && ($region === false || in_array($region, $image["regions"])) && ($matchtype === false || $image["type"] === $matchtype))
					{
						$images2[(isset($image["slug"]) ? $image["slug"] : $image["id"])] = ((int)$image["name"] > 0 ? $image["distribution"] . " " . $image["name"] : $image["name"] . " " . $image["distribution"]) . "\n\t" . (isset($image["size_gigabytes"]) ? $image["size_gigabytes"] : "Under " . $image["min_disk_size"]) . "GB, " . $image["type"] . ", " . ($image["public"] ? "public, " . $type : "private") . "\n";
						$imagemap[(isset($image["slug"]) ? $image["slug"] : $image["id"])] = $image;
					}
				}

				ksort($images2);

				foreach ($images2 as $id => $disp)  $images[$id] = $disp;
			}

			if (!count($images))  CLI::DisplayError($useronly ? ($matchtype === "backup" ? "No backups are available." : "No user-created backups or snapshots are available.") : "No images are available.  Is your account in good standing and does your account have a valid credit card on file?");
			$image = CLI::GetLimitedUserInputWithArgs($args, "image", $question, ($defaultval !== false && isset($images[$defaultval]) ? $defaultval : false), "Available images:", $images, true, $suppressoutput);
			unset($result["data"]);
			$result["image"] = $imagemap[$image];
			$result["id"] = $image;
		}

		return $result;
	}

	function GetDropletID($tagname = "", $region = false, $allowedids = false)
	{
		global $suppressoutput, $args, $do;

		if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "id"))
		{
			$id = CLI::GetUserInputWithArgs($args, "id", "Droplet ID", false, "", $suppressoutput);

			$result = $do->DropletsGetInfo($id);
			if (!$result["success"])  DisplayResult($result);
		}
		else
		{
			$result = $do->DropletsList(true, ($tagname !== "" ? "?tag_name=" . urlencode($tagname) : ""));
			if (!$result["success"])  DisplayResult($result);

			$ids = array();
			$ids2 = array();
			foreach ($result["data"] as $droplet)
			{
				if ($region !== false && $region !== $droplet["region"]["slug"])  continue;
				if ($allowedids !== false && !in_array($droplet["id"], $allowedids))  continue;

				$ids[$droplet["id"]] = $droplet["name"] . " - " . $droplet["status"] . ($droplet["locked"] ? " (locked)" : "") . "\n\t" . $droplet["memory"] . "MB RAM, " . $droplet["vcpus"] . ($droplet["vcpus"] == 1 ? " vCore, " : " vCores, ") . $droplet["disk"] . "GB disk\n";
				$ids2[$droplet["id"]] = $droplet;
			}
			if (!count($ids))  CLI::DisplayError("No droplets have been created" . ($region !== false ? " in the '" . $region . "' region.  Try creating a droplet with the API:  droplets create" : ".  Try creating your first droplet with the API:  droplets create"));
			$id = CLI::GetLimitedUserInputWithArgs($args, "id", "Droplet ID", false, "Available Droplets:", $ids, true, $suppressoutput);
			unset($result["data"]);
			$result["droplet"] = $ids2[$id];
		}

		return $result;
	}

	function GetFirewallID()
	{
		global $suppressoutput, $args, $do;

		if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "id"))
		{
			$id = CLI::GetUserInputWithArgs($args, "id", "Firewall ID", false, "", $suppressoutput);

			$result = $do->FirewallsGetInfo($id);
			if (!$result["success"])  DisplayResult($result);
		}
		else
		{
			$result = $do->FirewallsList();
			if (!$result["success"])  DisplayResult($result);

			$ids = array();
			$ids2 = array();
			foreach ($result["data"] as $firewall)
			{
				$opts = array();

				if (count($firewall["inbound_rules"]) == 1)  $opts[] = "1 inbound rule";
				else if (count($firewall["inbound_rules"]) == 0)  $opts[] = "No inbound rules";
				else  $opts[] = count($firewall["inbound_rules"]) . " inbound rules";

				if (count($firewall["outbound_rules"]) == 1)  $opts[] = "1 outbound rule";
				else if (count($firewall["outbound_rules"]) == 0)  $opts[] = "No outbound rules";
				else  $opts[] = count($firewall["outbound_rules"]) . " outbound rules";

				if (count($firewall["droplet_ids"]) == 1)  $opts[] = "1 Droplet";
				else if (count($firewall["droplet_ids"]) == 0)  $opts[] = "No Droplets";
				else  $opts[] = count($firewall["droplet_ids"]) . " Droplets";

				if (count($firewall["tags"]) == 1)  $opts[] = "1 tag";
				else if (count($firewall["tags"]) == 0)  $opts[] = "No tags";
				else  $opts[] = count($firewall["tags"]) . " tags";

				if (count($firewall["pending_changes"]) == 1)  $opts[] = "1 pending change";
				else if (count($firewall["pending_changes"]) != 0)  $opts[] = count($firewall["pending_changes"]) . " pending changes";

				$ids[$firewall["id"]] = $firewall["name"] . ", " . $firewall["status"] . " (" . implode(", ", $opts) . ")";
				$ids2[$firewall["id"]] = $firewall;
			}
			if (!count($ids))  CLI::DisplayError("No firewalls have been created.  Try creating your first firewall with the API:  firewalls create");
			$id = CLI::GetLimitedUserInputWithArgs($args, "id", "Firewall ID", false, "Available Firewalls:", $ids, true, $suppressoutput);
			unset($result["data"]);
			$result["firewall"] = $ids2[$id];
		}

		return $result;
	}

	function GetLoadBalancerID()
	{
		global $suppressoutput, $args, $do;

		if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "id"))
		{
			$id = CLI::GetUserInputWithArgs($args, "id", "Load balancer ID", false, "", $suppressoutput);

			$result = $do->LoadBalancersGetInfo($id);
			if (!$result["success"])  DisplayResult($result);
		}
		else
		{
			$result = $do->LoadBalancersList();
			if (!$result["success"])  DisplayResult($result);

			$ids = array();
			$ids2 = array();
			foreach ($result["data"] as $loadbalancer)
			{
				$ids[$loadbalancer["id"]] = $loadbalancer["name"] . ", " . $loadbalancer["ip"] . ", " . $loadbalancer["region"]["name"] . ", " . $loadbalancer["status"] . " (" . ($loadbalancer["tag"] !== "" ? $loadbalancer["tag"] : count($loadbalancer["droplet_ids"]) . " Droplets") . ")";
				$ids2[$loadbalancer["id"]] = $loadbalancer;
			}
			if (!count($ids))  CLI::DisplayError("No load balancers have been created.  Try creating your first load balancer with the API:  load-balancers create");
			$id = CLI::GetLimitedUserInputWithArgs($args, "id", "Load balancer ID", false, "Available load balancers:", $ids, true, $suppressoutput);
			unset($result["data"]);
			$result["load_balancer"] = $ids2[$id];
		}

		return $result;
	}

	function GetFloatingIPAddr()
	{
		global $suppressoutput, $args, $do;

		if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "ipaddr"))
		{
			$ipaddr = CLI::GetUserInputWithArgs($args, "ipaddr", "Floating IP address", false, "", $suppressoutput);

			$result = $do->FloatingIPsGetInfo($ipaddr);
			if (!$result["success"])  DisplayResult($result);
		}
		else
		{
			$result = $do->FloatingIPsList();
			if (!$result["success"])  DisplayResult($result);

			$ipaddrs = array();
			$ipaddrs2 = array();
			foreach ($result["data"] as $ipaddr)
			{
				$info = array();
				if (isset($ipaddr["region"]))  $info[] = $ipaddr["region"]["name"];
				if (isset($ipaddr["droplet"]))  $info[] = $ipaddr["droplet"]["name"] . " (" . $ipaddr["droplet"]["id"] . ")";

				$ipaddrs[$ipaddr["ip"]] = implode(", ", $info);
				$ipaddrs2[$ipaddr["ip"]] = $ipaddr;
			}
			if (!count($ipaddrs))  CLI::DisplayError("No Floating IPs have been created.  Try creating your first Floating IP with the API:  floating-ips create");
			$ipaddr = CLI::GetLimitedUserInputWithArgs($args, "ipaddr", "Floating IP address", false, "Available Floating IPs:", $ipaddrs, true, $suppressoutput);
			unset($result["data"]);
			$result["ipaddr"] = $ipaddrs2[$ipaddr];
		}

		return $result;
	}

	function GetProjectID($question = "Project ID")
	{
		global $suppressoutput, $args, $do;

		if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "id"))  $id = CLI::GetUserInputWithArgs($args, "id", $question, false, "", $suppressoutput);
		else
		{
			$result = $do->ProjectsList();
			if (!$result["success"])  DisplayResult($result);

			$projects = array();
			$default = false;
			foreach ($result["data"] as $project)
			{
				$projects[$project["id"]] = $project["name"] . ($project["is_default"] ? " (default)" : "");
				if ($project["is_default"])  $default = $project["id"];
			}
			if (!count($projects))  CLI::DisplayError("No Projects have been created.  Try creating your first Project with the API:  projects create");
			$id = CLI::GetLimitedUserInputWithArgs($args, "id", $question, $default, "Available Projects:", $projects, true, $suppressoutput);
		}

		return $id;
	}

	function GetSSHKey()
	{
		global $suppressoutput, $args, $do;

		if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "sshkey"))
		{
			$id = CLI::GetUserInputWithArgs($args, "sshkey", "SSH key ID", false, "", $suppressoutput);

			$result = $do->SSHKeysGetInfo($id);
			if (!$result["success"])  DisplayResult($result);
		}
		else
		{
			$result = $do->SSHKeysList();
			if (!$result["success"])  DisplayResult($result);

			$ids = array();
			$ids2 = array();
			foreach ($result["data"] as $sshkey)
			{
				$ids[$sshkey["id"]] = $sshkey["name"] . " | " . $sshkey["fingerprint"];
				$ids2[$sshkey["id"]] = $sshkey;
			}
			if (!count($ids))  CLI::DisplayError("No SSH keys have been created/registered.  Try creating/registering your first SSH key with the API:  ssh-keys create");
			$id = CLI::GetLimitedUserInputWithArgs($args, "sshkey", "SSH key ID", false, "Available SSH keys:", $ids, true, $suppressoutput);
			unset($result["data"]);
			$result["ssh_key"] = $ids2[$id];
		}

		return $result;
	}

	function GetTagName()
	{
		global $suppressoutput, $args, $do;

		if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "tag"))
		{
			$tagname = CLI::GetUserInputWithArgs($args, "tag", "Tag name", false, "", $suppressoutput);

			$result = $do->TagsGetInfo($tagname);
			if (!$result["success"])  DisplayResult($result);
		}
		else
		{
			$result = $do->TagsList();
			if (!$result["success"])  DisplayResult($result);

			$tags = array();
			$tags2 = array();
			foreach ($result["data"] as $tag)
			{
				$resources = array();
				foreach ($tag["resources"] as $type => $resourceinfo)
				{
					if ($resourceinfo["count"] > 0)  $resources[] = $resourceinfo["count"] . " " . $type;
				}
				if (!count($resources))  $resources[] = "No resources associated with this tag";

				$tags[$tag["name"]] = implode(", ", $resources);
				$tags2[$tag["name"]] = $tag;
			}
			if (!count($tags))  CLI::DisplayError("No tags have been created.  Try creating your first tag with the API:  tags create");
			$tagname = CLI::GetLimitedUserInputWithArgs($args, "tag", "Tag name", false, "Available tags:", $tags, true, $suppressoutput);
			unset($result["data"]);
			$result["tag"] = $tags2[$tagname];
		}

		return $result;
	}

	function ReinitArgs($newargs)
	{
		global $args;

		// Process the parameters.
		$options = array(
			"shortmap" => array(
				"?" => "help"
			),
			"rules" => array(
			)
		);

		foreach ($newargs as $arg)  $options["rules"][$arg] = array("arg" => true, "multiple" => true);
		$options["rules"]["help"] = array("arg" => false);

		$args = CLI::ParseCommandLine($options, array_merge(array(""), $args["params"]));

		if (isset($args["opts"]["help"]))  DisplayResult(array("success" => true, "options" => array_keys($options["rules"])));
	}

	if ($apigroup === "account")
	{
		// Account.
		if ($api === "get-info")  DisplayResult($do->AccountGetInfo());
	}
	else if ($apigroup === "actions")
	{
		// Actions.
		if ($api === "list")
		{
			ReinitArgs(array("pages"));

			$numpages = (int)CLI::GetUserInputWithArgs($args, "pages", "Number of pages to retrieve", "1", "", $suppressoutput);

			DisplayResult($do->ActionsList($numpages));
		}
		else if ($api === "get-info")
		{
			ReinitArgs(array("id"));

			$id = CLI::GetUserInputWithArgs($args, "id", "Action ID", false, "", $suppressoutput);

			DisplayResult($do->ActionsGetInfo($id));
		}
		else if ($api === "wait")
		{
			ReinitArgs(array("id", "wait"));

			$id = CLI::GetUserInputWithArgs($args, "id", "Action ID", false, "", $suppressoutput);
			$wait = (int)CLI::GetUserInputWithArgs($args, "wait", "Wait in seconds", false, "", $suppressoutput);
			if ($wait < 1)  $wait = 1;

			DisplayResult($do->WaitForActionCompletion($id, $wait, array(), "ActionWaitHandler"));
		}
	}
	else if ($apigroup === "volumes")
	{
		// Volumes.
		if ($api === "list")  DisplayResult($do->VolumesList());
		else if ($api === "create")
		{
			ReinitArgs(array("name", "desc", "size", "mode", "region", "id", "snapshot", "fstype", "fslabel"));

			$name = CLI::GetUserInputWithArgs($args, "name", "Block Storage volume name", false, "", $suppressoutput);
			$desc = CLI::GetUserInputWithArgs($args, "desc", "Description", "", "", $suppressoutput);
			$size = (int)CLI::GetUserInputWithArgs($args, "size", "Size (in GB)", "1", "DigitalOcean Block Storage is approximately \$0.10 USD/month per GB.", $suppressoutput);
			if ($size < 1)  $size = 1;

			$modes = array(
				"region" => "New Block Storage volume by region",
				"snapshot" => "Block Storage volume snapshot"
			);
			$mode = CLI::GetLimitedUserInputWithArgs($args, "mode", "Mode", "region", "Available volume creation modes:", $modes, true, $suppressoutput);

			$volumeopts = array();
			if ($mode === "region")
			{
				// Get supported Droplet region.
				$region = GetDropletRegion("Block Storage region", false, false, false, false, true, "");
				$volumeopts["region"] = $region;
			}
			else
			{
				$result = GetVolumeID();
				$id = $result["volume"]["id"];

				$snapshotid = GetVolumeSnapshotID($id);
				$volumeopts["snapshot_id"] = $snapshotid;
			}

			// Note:  Attaching pre-formatted volumes to Droplets created before April 26, 2018 is not recommended.
			$fstypes = array(
				"" => "Unformatted",
				"ext4" => "ext4",
				"xfs" => "xfs"
			);
			$fstype = CLI::GetLimitedUserInputWithArgs($args, "fstype", "Filesystem type", "", "Available filesystem types:", $fstypes, true, $suppressoutput);
			$volumeopts["filesystem_type"] = $fstype;

			if ($fstype !== "")
			{
				$fslabel = CLI::GetUserInputWithArgs($args, "fslabel", "Filesystem label", "", "Labels for ext4 may contain 16 characters while xfs may contain 12 characters.", $suppressoutput);
				$volumeopts["filesystem_label"] = $fslabel;
			}

			DisplayResult($do->VolumesCreate($name, $desc, $size, $volumeopts));
		}
		else
		{
			ReinitArgs(array("id", "name"));

			$result = GetVolumeID();
			$id = $result["volume"]["id"];

			if ($api === "get-info")  DisplayResult($result);
			else if ($api === "snapshots")  DisplayResult($do->VolumesSnapshotsList($id));
			else if ($api === "snapshot")
			{
				$name = CLI::GetUserInputWithArgs($args, "name", "Snapshot name", false, "", $suppressoutput);

				DisplayResult($do->VolumeSnapshotCreate($id, $name));
			}
			else if ($api === "delete")  DisplayResult($do->VolumesDelete($id));
		}
	}
	else if ($apigroup === "volume-actions")
	{
		ReinitArgs(array("id", "size", "wait"));

		$result = GetVolumeID();
		$id = $result["volume"]["id"];
		$region = $result["volume"]["region"]["slug"];

		$defaultwait = 5;
		$initwait = array();
		$actionvalues = array();

		$actionvalues["region"] = $region;

		if ($api === "attach" || $api === "detach")
		{
			$result = GetDropletID("", $region, ($api === "detach" ? $result["volume"]["droplet_ids"] : false));

			$actionvalues["droplet_id"] = $result["droplet"]["id"];
		}
		else if ($api === "resize")
		{
			$size = (int)CLI::GetUserInputWithArgs($args, "size", "New size (in GB)", (string)($result["volume"]["size_gigabytes"] + 1), "For the next question, Block Storage volumes can only increase in size.  DigitalOcean Block Storage is approximately \$0.10 USD/month per GB.", $suppressoutput);
			if ($size <= $result["volume"]["size_gigabytes"])  CLI::DisplayError("Size specified is smaller than " . ($result["volume"]["size_gigabytes"] + 1) . " GB.");

			$actionvalues["size_gigabytes"] = $size;
		}

		// Wait for action completion.
		$wait = CLI::GetYesNoUserInputWithArgs($args, "wait", "Wait for completion", "Y", "The next question involves whether or not to wait for the Block Storage volume action to complete.  If you don't want to wait, you can use the 'action' ID that is returned to wait later on for completion of the task.", $suppressoutput);

		DisplayResult($do->VolumeActionsByID($id, $api, $actionvalues), $wait, $defaultwait, $initwait);
	}
	else if ($apigroup === "cdn-endpoints")
	{
		// Content Delivery Network (CDN) endpoints.
		if ($api === "list")  DisplayResult($do->CDNEndpointsList());
		else if ($api === "create" || $api === "update")
		{
			ReinitArgs(array("origin", "ttl", "id", "customdomain"));

			if ($api === "create")  $origin = CLI::GetUserInputWithArgs($args, "origin", "Origin domain", false, "The next question asks for the fully qualified domain name (FQDN) for the origin server which the provides the content for the CDN.", $suppressoutput);
			else
			{
				$id = GetCDNEndpointID();

				$info = $do->CDNEndpointsGetInfo($id);
				if (!$info["success"])  DisplayResult($info);
			}

			$cdnopts = array();
			$cdnopts["ttl"] = (int)CLI::GetUserInputWithArgs($args, "ttl", "Cache TTL", ($api === "update" ? $info["endpoint"]["ttl"] : "3600"), "", $suppressoutput);
			$cdnopts["certificate_id"] = "";
			$cdnopts["custom_domain"] = "";

			$certid = GetCertificateID(($api === "update" ? ($info["endpoint"]["certificate_id"] !== "" ? $info["endpoint"]["certificate_id"] : "-") : false), array("-" => "None"));

			if ($certid !== "-")
			{
				$cdnopts["certificate_id"] = $certid;

				$result = $do->CertificatesGetInfo($certid);
				if (!$result["success"])  DisplayResult($result);

				$pos = CLI::GetLimitedUserInputWithArgs($args, "customdomain", "Custom domain", ($api === "update" ? $info["endpoint"]["custom_domain"] : false), "Available domains:", $result["certificate"]["dns_names"], true, $suppressoutput);
				$cdnopts["custom_domain"] = $result["certificate"]["dns_names"][$pos];
			}

			if ($api === "create")  DisplayResult($do->CDNEndpointsCreate($origin, $cdnopts));
			else  DisplayResult($do->CDNEndpointsUpdate($id, $cdnopts));
		}
		else if ($api === "purge")
		{
			ReinitArgs(array("id", "file"));

			$id = GetCDNEndpointID();

			$files = array();
			do
			{
				$file = CLI::GetUserInputWithArgs($args, "file", (count($files) ? "Another filename/pattern" : "Filename or pattern"), "", "", $suppressoutput);
				if ($file !== "")  $files[] = $file;
			} while ($file !== "" || !count($files));

			DisplayResult($do->CDNEndpointsPurge($id, $files));
		}
		else
		{
			ReinitArgs(array("id"));

			$id = GetCDNEndpointID();

			if ($api === "get-info")  DisplayResult($do->CDNEndpointsGetInfo($id));
			else if ($api === "delete")  DisplayResult($do->CDNEndpointsDelete($id));
		}
	}
	else if ($apigroup === "certificates")
	{
		// Certificates.
		if ($api === "list")  DisplayResult($do->CertificatesList());
		else if ($api === "create")
		{
			ReinitArgs(array("name", "type", "dnsname", "privatekey", "publickey", "certchain"));

			$name = CLI::GetUserInputWithArgs($args, "name", "Certificate name", false, "", $suppressoutput);

			$types = array(
				"custom" => "Custom",
				"lets_encrypt" => "Let's Encrypt"
			);
			$type = CLI::GetLimitedUserInputWithArgs($args, "type", "Certificate type", "custom", "Available certificate types:", $types, true, $suppressoutput);

			$typevalues = array();
			if ($type === "lets_encrypt")
			{
				$dnsnames = array();
				do
				{
					$name = CLI::GetUserInputWithArgs($args, "dnsname", (count($dnsnames) ? "Another DNS name" : "DNS name"), "", "", $suppressoutput);
					if ($name !== "")  $dnsnames[] = $name;
				} while ($name !== "");

				$typevalues["dns_names"] = $dnsnames;
			}
			else
			{
				do
				{
					$valid = false;
					$filename = CLI::GetUserInputWithArgs($args, "privatekey", "Private key filename", false, "", $suppressoutput);
					if (!file_exists($filename))  CLI::DisplayError("The file '" . $filename . "' does not exist.", false, false);
					else
					{
						$privatekey = file_get_contents($filename);

						$valid = true;
					}
				} while (!$valid);

				do
				{
					$valid = false;
					$filename = CLI::GetUserInputWithArgs($args, "publickey", "Public key filename", false, "", $suppressoutput);
					if (!file_exists($filename))  CLI::DisplayError("The file '" . $filename . "' does not exist.", false, false);
					else
					{
						$publickey = file_get_contents($filename);

						$valid = true;
					}
				} while (!$valid);

				do
				{
					$valid = false;
					$filename = CLI::GetUserInputWithArgs($args, "certchain", "Certificate chain filename", "", "", $suppressoutput);
					if ($filename === "")
					{
						$certchain = false;

						$valid = true;
					}
					else if (!file_exists($filename))
					{
						CLI::DisplayError("The file '" . $filename . "' does not exist.", false, false);
					}
					else
					{
						$certchain = file_get_contents($filename);

						$valid = true;
					}
				} while (!$valid);

				$typevalues["private_key"] = $privatekey;
				$typevalues["leaf_certificate"] = $publickey;
				$typevalues["certificate_chain"] = $certchain;
			}

			DisplayResult($do->CertificatesCreate($name, $type, $typevalues));
		}
		else
		{
			ReinitArgs(array("id"));

			$id = GetCertificateID();

			if ($api === "get-info")  DisplayResult($do->CertificatesGetInfo($id));
			else if ($api === "delete")  DisplayResult($do->CertificatesDelete($id));
		}
	}
	else if ($apigroup === "database-clusters")
	{
		// Database clusters.
		if ($api === "list")  DisplayResult($do->DatabaseClustersList());
		else if ($api === "create")
		{
			ReinitArgs(array("name", "engine", "size", "region", "nodes"));

			$name = CLI::GetUserInputWithArgs($args, "name", "Cluster name", false, "", $suppressoutput);

			$engines = array(
				"pg" => "PostgreSQL"
			);

			$engine = CLI::GetLimitedUserInputWithArgs($args, "engine", "Database engine", false, "Available database engines:", $engines, true, $suppressoutput);

			// NOTE:  There is insufficient API support for determining what versions of an engine are available for use.  Using the latest version is probably the best option anyway (i.e. the default).

			// NOTE:  There is insufficient API support for determining what regions and sizes support database clusters.
			$size = CLI::GetUserInputWithArgs($args, "size", "Size slug", false, "NOTE:  There is insufficient API support for determining what sizes support database clusters.  Please read the API documentation to determine what size slug to use for the next question.", $suppressoutput);

			$region = GetDropletRegion("Database cluster region", false, false, false, false, false, "");

			$nodes = (int)CLI::GetUserInputWithArgs($args, "nodes", "Number of nodes", false, "The next question asks for the number of nodes to use for the database cluster and must be between 1 and 3 inclusive.", $suppressoutput);
			if ($nodes < 1)  $nodes = 1;
			if ($nodes > 3)  $nodes = 3;

			DisplayResult($do->DatabaseClustersCreate($name, $engine, $size, $region, $nodes));
		}
		else if ($api === "resize")
		{
			ReinitArgs(array("id", "size", "nodes"));

			$id = GetDatabaseClusterID();

			// NOTE:  There is insufficient API support for determining what regions and sizes support database clusters.
			$size = CLI::GetUserInputWithArgs($args, "size", "New size slug", false, "The next question asks for a new size slug.  It must be equal to or larger than the current database cluster size.  NOTE:  There is insufficient API support for determining what sizes support database clusters.  Please read the API documentation to determine what size slug to use for the next question.", $suppressoutput);

			$nodes = (int)CLI::GetUserInputWithArgs($args, "nodes", "Number of nodes", false, "The next question asks for the number of nodes to use for the database cluster and must be between 1 and 3 inclusive.", $suppressoutput);
			if ($nodes < 1)  $nodes = 1;
			if ($nodes > 3)  $nodes = 3;

			DisplayResult($do->DatabaseClustersResize($id, $size, $nodes));
		}
		else if ($api === "migrate")
		{
			ReinitArgs(array("id", "region"));

			$id = GetDatabaseClusterID();

			// NOTE:  There is insufficient API support for determining what regions and sizes support database clusters.
			$region = GetDropletRegion("New database cluster region", false, false, false, false, false, "");

			DisplayResult($do->DatabaseClustersMigrate($id, $region));
		}
		else if ($api === "maintenance")
		{
			ReinitArgs(array("id", "day", "time"));

			$id = GetDatabaseClusterID();

			$result = $do->DatabaseClustersGetInfo($id);
			if (!$result["success"])  DisplayResult($result);

			$days = array(
				"Sunday",
				"Monday",
				"Tuesday",
				"Wednesday",
				"Thursday",
				"Friday",
				"Saturday",
			);

			$day = CLI::GetLimitedUserInputWithArgs($args, "day", "Weekday", $result["database"]["maintenance_window"]["day"], "Available weekdays:", $days, true, $suppressoutput);
			$day = strtolower($days[$day]);

			$time = CLI::GetUserInputWithArgs($args, "time", "Time", $result["database"]["maintenance_window"]["hour"], "The next question asks for the 24-hour clock time during which to perform cluster maintenance.", $suppressoutput);

			DisplayResult($do->DatabaseClustersMaintenance($id, $day, $time));
		}
		else if ($api === "restore")
		{
			ReinitArgs(array("id", "backup", "name"));

			$id = GetDatabaseClusterID();

			$result = $do->DatabaseClustersBackups($id);
			if (!$result["success"])  DisplayResult($result);

			$backups = array();
			foreach ($result["data"] as $backup)
			{
				$backups[] = trim(str_replace(array("T", "Z"), " ", $backup["created_at"])) . ", " . number_format($backup["size_gigabytes"], 2) . " GB";
			}
			if (!count($backups))  CLI::DisplayError("No backups exist for the specified database cluster.");
			$backup = CLI::GetLimitedUserInputWithArgs($args, "backup", "Backup", (count($backups) - 1), "Available backups:", $backups, true, $suppressoutput);

			$name = CLI::GetUserInputWithArgs($args, "name", "New cluster name", false, "The next question asks for a new cluster name to restore the backup into.", $suppressoutput);

			$result2 = $do->DatabaseClustersGetInfo($id);
			if (!$result2["success"])  DisplayResult($info);

			$createopts = array(
				"version" => $result2["database"]["version"],
				"backup_restore" => array(
					"database_name" => $result2["database"]["name"],
					"backup_created_at" => $backups["data"][$backup]["created_at"]
				)
			);

			DisplayResult($do->DatabaseClustersCreate($name, $result2["database"]["engine"], $result2["database"]["size"], $result2["database"]["region"], $result2["database"]["nodes"], $createopts));
		}
		else
		{
			ReinitArgs(array("id"));

			$id = GetDatabaseClusterID();

			if ($api === "get-info")  DisplayResult($do->DatabaseClustersGetInfo($id));
			else if ($api === "backups")  DisplayResult($do->DatabaseClustersBackups($id));
			else if ($api === "delete")  DisplayResult($do->DatabaseClustersDelete($id));
		}
	}
	else if ($apigroup === "database-replicas")
	{
		// Database cluster read-only replicas.
		if ($api === "list")
		{
			ReinitArgs(array("id"));

			$id = GetDatabaseClusterID();

			DisplayResult($do->DatabaseReplicasList($id));
		}
		else if ($api === "create")
		{
			ReinitArgs(array("id", "name", "size", "region"));

			$id = GetDatabaseClusterID();

			$result = $do->DatabaseClustersGetInfo($id);
			if (!$result["success"])  DisplayResult($info);

			$name = CLI::GetUserInputWithArgs($args, "name", "Replica name", false, "", $suppressoutput);

			// NOTE:  There is insufficient API support for determining what regions and sizes support database clusters.
			$size = CLI::GetUserInputWithArgs($args, "size", "Size slug", $result["database"]["size"], "The next question asks for a size slug for the replica.  It must be equal to or larger than the current database cluster size.  NOTE:  There is insufficient API support for determining what sizes support database clusters.  Please read the API documentation to determine what size slug to use for the next question.", $suppressoutput);

			$region = GetDropletRegion("Database cluster region", false, false, false, false, false, "");

			DisplayResult($do->DatabaseReplicasCreate($id, $name, $size, $region));
		}
		else
		{
			ReinitArgs(array("id", "name"));

			$id = GetDatabaseClusterID();

			$result = $do->DatabaseReplicasList($id);
			if (!$result["success"])  DisplayResult($result);

			$names = array();
			foreach ($result["data"] as $replica)
			{
				$names[$replica["name"]] = trim(str_replace(array("T", "Z"), " ", $replica["created_at"])) . ", " . $replica["region"] . ", " . $replica["status"];
			}

			$name = CLI::GetLimitedUserInputWithArgs($args, "name", "Replica name", false, "Available replica names:", $names, true, $suppressoutput);

			if ($api === "get-info")  DisplayResult($do->DatabaseReplicasGetInfo($id, $name));
			else if ($api === "delete")  DisplayResult($do->DatabaseReplicasDelete($id, $name));
		}
	}
	else if ($apigroup === "database-users")
	{
		// Database cluster users.
		if ($api === "list")
		{
			ReinitArgs(array("id"));

			$id = GetDatabaseClusterID();

			DisplayResult($do->DatabaseUsersList($id));
		}
		else if ($api === "create")
		{
			ReinitArgs(array("id", "name"));

			$id = GetDatabaseClusterID();

			$name = CLI::GetUserInputWithArgs($args, "name", "Username", false, "", $suppressoutput);

			DisplayResult($do->DatabaseUsersCreate($id, $name));
		}
		else
		{
			ReinitArgs(array("id", "name"));

			$id = GetDatabaseClusterID();

			$name = GetDatabaseUsername($id);

			if ($api === "get-info")  DisplayResult($do->DatabaseUsersGetInfo($id, $name));
			else if ($api === "delete")  DisplayResult($do->DatabaseUsersDelete($id, $name));
		}
	}
	else if ($apigroup === "databases")
	{
		// Databases in a database cluster.
		if ($api === "list")
		{
			ReinitArgs(array("id"));

			$id = GetDatabaseClusterID();

			DisplayResult($do->DatabasesList($id));
		}
		else if ($api === "create")
		{
			ReinitArgs(array("id", "name"));

			$id = GetDatabaseClusterID();

			$name = CLI::GetUserInputWithArgs($args, "name", "Database name", false, "", $suppressoutput);

			DisplayResult($do->DatabasesCreate($id, $name));
		}
		else
		{
			ReinitArgs(array("id", "name"));

			$id = GetDatabaseClusterID();

			$name = GetDatabaseName($id);

			if ($api === "get-info")  DisplayResult($do->DatabasesGetInfo($id, $name));
			else if ($api === "delete")  DisplayResult($do->DatabasesDelete($id, $name));
		}
	}
	else if ($apigroup === "database-pools")
	{
		// Databases pools for a database.
		if ($api === "list")
		{
			ReinitArgs(array("id"));

			$id = GetDatabaseClusterID();

			DisplayResult($do->DatabasePoolsList($id));
		}
		else if ($api === "create")
		{
			ReinitArgs(array("id", "name", "mode", "size"));

			$id = GetDatabaseClusterID();

			$name = CLI::GetUserInputWithArgs($args, "name", "Connection pool name", false, "", $suppressoutput);

			$modes = array(
				"transaction" => "One backend connection per transaction and clients can remain connected but idle",
				"session" => "A backend connection is assigned per client until the client disconnects",
				"statement" => "One backend connection per statement and refuses transactions with multiple statements"
			);

			$mode = CLI::GetLimitedUserInputWithArgs($args, "mode", "Mode", "transaction", "Available connection pool modes:", $modes, true, $suppressoutput);

			$size = (int)CLI::GetUserInputWithArgs($args, "size", "Connection pool size", false, "The next question asks how large the backend connection pool size should be.  The value is limited by the amount of RAM in the cluster (25 connections allowed per GB) minus the total number of connections in other connection pools minus 3 (reserved for database maintenance connections).", $suppressoutput);
			if ($size < 0)  $size = 1;

			$dbname = GetDatabaseName($id);
			$username = GetDatabaseUsername($id);

			DisplayResult($do->DatabasePoolsCreate($id, $name, $mode, $size, $dbname, $username));
		}
		else
		{
			ReinitArgs(array("id", "name"));

			$id = GetDatabaseClusterID();

			$name = GetDatabasePoolName($id);

			if ($api === "get-info")  DisplayResult($do->DatabasePoolsGetInfo($id, $name));
			else if ($api === "delete")  DisplayResult($do->DatabasePoolsDelete($id, $name));
		}
	}
	else if ($apigroup === "domains")
	{
		// Domains.
		if ($api === "list")  DisplayResult($do->DomainsList());
		else if ($api === "create")
		{
			ReinitArgs(array("name", "ip"));

			$name = CLI::GetUserInputWithArgs($args, "name", "Domain name (TLD, no subdomains)", false, "", $suppressoutput);
			$ipaddr = CLI::GetUserInputWithArgs($args, "ip", "IP address to point the domain to", "", "", $suppressoutput);

			DisplayResult($do->DomainsCreate($name, ($ipaddr !== "" ? $ipaddr : null)));
		}
		else
		{
			ReinitArgs(array("name"));

			$name = GetDomainName("name");

			if ($api === "get-info")  DisplayResult($do->DomainsGetInfo($name));
			else if ($api === "delete")  DisplayResult($do->DomainsDelete($name));
		}
	}
	else if ($apigroup === "domain-records")
	{
		// Domain records.
		if ($api === "list")
		{
			ReinitArgs(array("domain"));

			$domainname = GetDomainName("domain");

			DisplayResult($do->DomainRecordsList($domainname));
		}
		else if ($api === "create" || $api === "update")
		{
			if ($api === "create")  ReinitArgs(array("domain", "type", "data", "priority", "port", "weight", "flags", "tag", "ttl"));
			else  ReinitArgs(array("domain", "id", "type", "data", "priority", "port", "weight", "flags", "tag", "ttl"));

			$domainname = GetDomainName("domain");

			if ($api === "update")  $result = GetDomainNameRecord($domainname);

			$types = array(
				"A" => "IPv4",
				"AAAA" => "IPv6",
				"CAA" => "Certification Authority Authorization",
				"CNAME" => "Canonical Name/Alias",
				"MX" => "Mail eXchange",
				"TXT" => "Arbitrary text such as a SPF record",
				"SRV" => "Service",
				"NS" => "Nameserver"
			);
			$type = CLI::GetLimitedUserInputWithArgs($args, "type", "DNS record type", ($api === "update" ? $result["record"]["type"] : false), "Available DNS record types:", $types, true, $suppressoutput);

			if ($type === "A" || $type === "AAAA" || $type === "CAA" || $type === "CNAME" || $type === "TXT" || $type === "SRV")  $name = CLI::GetUserInputWithArgs($args, "name", "Name", ($api === "update" ? $result["record"]["name"] : "@"), "", $suppressoutput);
			else  $name = "";

			$data = CLI::GetUserInputWithArgs($args, "data", "Value/data", ($api === "update" ? $result["record"]["data"] : false), "", $suppressoutput);

			if ($type === "MX" || $type === "SRV")  $priority = (int)CLI::GetUserInputWithArgs($args, "priority", "Priority", ($api === "update" && isset($result["record"]["priority"]) ? $result["record"]["priority"] : "0"), "", $suppressoutput);
			else  $priority = null;

			if ($type === "SRV")  $port = (int)CLI::GetUserInputWithArgs($args, "port", "Port", ($api === "update" && isset($result["record"]["port"]) ? $result["record"]["port"] : "0"), "", $suppressoutput);
			else  $port = null;

			if ($type === "SRV")  $weight = (int)CLI::GetUserInputWithArgs($args, "weight", "Weight", ($api === "update" && isset($result["record"]["weight"]) ? $result["record"]["weight"] : "1"), "", $suppressoutput);
			else  $weight = null;

			if ($type === "CAA")  $flags = (int)CLI::GetUserInputWithArgs($args, "flags", "Flags", ($api === "update" && isset($result["record"]["flags"]) ? $result["record"]["flags"] : "0"), "", $suppressoutput);
			else  $flags = null;

			if ($type !== "CAA")  $tag = null;
			else
			{
				$tags = array(
					"issue" => "Grant authorization to a specific certificate issuer",
					"issuewild" => "Grant authorization to specific certificate issuers that only specify a wildcard domain",
					"iodef" => "Report a certificate issue request"
				);
				$tag = CLI::GetLimitedUserInputWithArgs($args, "tag", "CAA property tag", ($api === "update" ? $result["record"]["tag"] : false), "Available CAA property tags:", $tags, true, $suppressoutput);
			}

			$ttl = CLI::GetUserInputWithArgs($args, "ttl", "TTL", ($api === "update" ? $result["record"]["ttl"] : "1800"), "", $suppressoutput);

			if ($api === "create")  DisplayResult($do->DomainRecordsCreate($domainname, $type, $name, $data, $ttl, array("priority" => $priority, "port" => $port, "weight" => $weight, "flags" => $flags, "tag" => $tag)));
			else  DisplayResult($do->DomainRecordsUpdate($domainname, $result["record"]["id"], array("type" => $type, "name" => $name, "data" => $data, "priority" => $priority, "port" => $port, "weight" => $weight, "flags" => $flags, "tag" => $tag, "ttl" => $ttl)));
		}
		else
		{
			ReinitArgs(array("domain", "id"));

			$domainname = GetDomainName("domain");

			$result = GetDomainNameRecord($domainname);

			if ($api === "get-info")  DisplayResult($result);
			else if ($api === "delete")  DisplayResult($do->DomainRecordsDelete($domainname, $result["record"]["id"]));
		}
	}
	else if ($apigroup === "droplets")
	{
		// Droplets.
		if ($api === "list")
		{
			ReinitArgs(array("mode", "tag"));

			$modes = array(
				"all" => "All Droplets",
				"tag" => "Only Droplets with a tag"
			);
			$mode = CLI::GetLimitedUserInputWithArgs($args, "mode", "Mode", "all", "Available list modes:", $modes, true, $suppressoutput);

			if ($mode === "all")  $tagname = "";
			else
			{
				$result = GetTagName();
				$tagname = $result["tag"]["name"];
			}

			DisplayResult($do->DropletsList(true, ($tagname !== "" ? "?tag_name=" . urlencode($tagname) : "")));
		}
		else if ($api === "all-neighbors")
		{
			DisplayResult($do->DropletsNeighborsList("all"));
		}
		else if ($api === "create")
		{
			ReinitArgs(array("name", "size", "backups", "ipv6", "private_network", "storage", "metadata", "region", "image", "volume", "sshkey", "tag", "wait"));

			// Get Droplet name.
			$name = CLI::GetUserInputWithArgs($args, "name", "Droplet name", false, "When a Droplet name is set to a domain name managed in the DigitalOcean DNS management system, the name will be used to configure a PTR record for the Droplet.  Each name set during creation will also determine the hostname for the Droplet in its internal configuration.\n", $suppressoutput);

			// Get Droplet size.
			$result = GetDropletSize();
			$size = $result["size"];
			$disksize = $result["disksize"];

			// Get options.
			$backups = CLI::GetYesNoUserInputWithArgs($args, "backups", "Enable automated backups", "N", "Enabling automated backups adds 20% to the cost of the server.  For a \$5 per month server, automated backups for that server would be \$1 per month.", $suppressoutput);
			$ipv6 = CLI::GetYesNoUserInputWithArgs($args, "ipv6", "Enable IPv6", "Y", "", $suppressoutput);
			$privatenetwork = CLI::GetYesNoUserInputWithArgs($args, "private_network", "Enable Shared Private Networking", "N", "DigitalOcean shared private networking requires quite a bit of extra work to set up correctly and to avoid exposing the droplets to attack.  You should assume your droplets with this feature enabled are no more secure than those simply facing the Internet.", $suppressoutput);
			$storage = CLI::GetYesNoUserInputWithArgs($args, "storage", "Use Block Storage volumes", "N", "", $suppressoutput);

			$userdata = CLI::GetUserInputWithArgs($args, "metadata", "Droplet user data filename", "", "User data (aka metadata) is either a 'cloud-config' or shell script to run when initializing each Droplet specified earlier.  It can be used to automatically configure the Droplet.", $suppressoutput, function($line, &$opts) {
				$result = ($line === "" || (file_exists($line) && is_file($line)));
				if (!$result)  echo "Unable to find the filename '" . $line . "'.";

				return $result;
			});
			if ($userdata !== "")  $userdata = file_get_contents($userdata);

			// Get supported Droplet region.
			$region = GetDropletRegion("Droplet region", $size, $backups, $ipv6, $privatenetwork, $storage, $userdata);

			// Get supported Droplet image.
			$result = GetDropletImage("Droplet image", false, $disksize, $region);
			$image = $result["id"];

			// Attach Block Storage volumes to the Droplet.
			$volumes = array();
			if ($storage)
			{
				$done = false;
				if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "volume"))
				{
					do
					{
						$id = CLI::GetUserInputWithArgs($args, "volume", (count($volumes) ? "Another Volume ID" : "Volume ID"), "", "", $suppressoutput);
						if ($id !== "")  $volumes[] = $id;
					} while ($id !== "" && ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "volume")));

					if ($id === "")  $done = true;
				}

				if (!$done)
				{
					$result = $do->VolumesList();
					if (!$result["success"])  DisplayResult($result);

					$ids = array();
					foreach ($result["data"] as $volume)  $ids[$volume["id"]] = $volume["region"]["slug"] . ", " . $volume["name"] . ", " . $volume["size_gigabytes"] . " GB";
					$ids2 = CLI::GetLimitedUserInputWithArgs($args, "volume", "Volume ID", "", "Available Block Storage volumes:", $ids, true, $suppressoutput, array("exit" => "", "nextquestion" => "Another Volume ID", "nextdefault" => ""));
					foreach ($ids2 as $id)  $volumes[] = $id;
				}
			}

			// Select registered SSH public keys to add to the Droplet.
			$done = false;
			$sshkeys = array();
			if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "sshkey"))
			{
				do
				{
					$id = CLI::GetUserInputWithArgs($args, "sshkey", (count($sshkeys) ? "Another SSH key ID" : "SSH key ID"), "", "", $suppressoutput);
					if ($id !== "")  $sshkeys[] = $id;
				} while ($id !== "" && ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "sshkey")));

				if ($id === "")  $done = true;
			}

			if (!$done)
			{
				$result = $do->SSHKeysList();
				if (!$result["success"])  DisplayResult($result);

				$ids = array();
				foreach ($result["data"] as $sshkey)  $ids[$sshkey["id"]] = $sshkey["name"] . " | " . $sshkey["fingerprint"];
				$ids2 = CLI::GetLimitedUserInputWithArgs($args, "sshkey", "SSH key ID", "", "Available SSH keys:", $ids, true, $suppressoutput, array("exit" => "", "nextquestion" => "Another SSH key ID", "nextdefault" => ""));
				foreach ($ids2 as $id)  $sshkeys[] = $id;
			}

			$done = false;
			$tags = array();
			if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "tag"))
			{
				do
				{
					$id = CLI::GetUserInputWithArgs($args, "tag", (count($tags) ? "Another tag name" : "Tag name"), "", "", $suppressoutput);
					if ($id !== "")  $tags[] = $id;
				} while ($id !== "" && ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "tag")));

				if ($id === "")  $done = true;
			}

			if (!$done)
			{
				$result = $do->TagsList();
				if (!$result["success"])  DisplayResult($result);

				$ids = array();
				foreach ($result["data"] as $tag)  $ids[$tag["name"]] = $tag["name"];
				$ids2 = CLI::GetLimitedUserInputWithArgs($args, "tag", "Tag name", "", "Available tags:", $ids, true, $suppressoutput, array("exit" => "", "nextquestion" => "Another tag name", "nextdefault" => ""));
				foreach ($ids2 as $id)  $tags[] = $id;
			}

			// Wait for action completion.
			$wait = CLI::GetYesNoUserInputWithArgs($args, "wait", "Wait for completion", "Y", "The next question involves whether or not to wait for the new Droplet(s) to be created.  If you don't want to wait, you can use the 'action' ID that is returned to wait later on for completion of the task.", $suppressoutput);

			$result = $do->DropletsCreate($name, $region, $size, $image, array("ssh_keys" => $sshkeys, "backups" => $backups, "ipv6" => $ipv6, "private_networking" => $privatenetwork, "volumes" => $volumes, "user_data" => ($userdata !== "" ? $userdata : null), "tags" => $tags));

			DisplayResult($result, $wait, 10, array(5, 25, 15, 10, 5));
		}
		else if ($api === "delete-by-tag")
		{
			ReinitArgs(array("tag"));

			$result = GetTagName();
			$tagname = $result["tag"]["name"];

			DisplayResult($do->DropletsDeleteByTag($tagname));
		}
		else if ($api === "actions")
		{
			ReinitArgs(array("id", "pages"));

			$result = GetDropletID();
			$id = $result["droplet"]["id"];

			$numpages = (int)CLI::GetUserInputWithArgs($args, "pages", "Number of pages to retrieve", "1", "", $suppressoutput);

			DisplayResult($do->DropletsActionsList($id, $numpages));
		}
		else
		{
			ReinitArgs(array("id"));

			$result = GetDropletID();
			$id = $result["droplet"]["id"];

			if ($api === "get-info")  DisplayResult($result);
			else if ($api === "kernels")  DisplayResult($do->DropletsKernelsList($id));
			else if ($api === "snapshots")  DisplayResult($do->DropletsSnapshotsList($id));
			else if ($api === "backups")  DisplayResult($do->DropletsBackupsList($id));
			else if ($api === "delete")  DisplayResult($do->DropletsDelete($id));
			else if ($api === "neighbors")  DisplayResult($do->DropletsNeighborsList($id));
		}
	}
	else if ($apigroup === "droplet-actions")
	{
		// Droplet actions.
		if ($api === "restore")  ReinitArgs(array("mode", "id", "image", "wait"));
		else if ($api === "resize")  ReinitArgs(array("mode", "id", "size", "permanent", "wait"));
		else if ($api === "rebuild")  ReinitArgs(array("mode", "id", "image", "wait"));
		else if ($api === "rename")  ReinitArgs(array("mode", "id", "name", "wait"));
		else if ($api === "change-kernel")  ReinitArgs(array("mode", "id", "kernel", "wait"));
		else if ($api === "snapshot")  ReinitArgs(array("mode", "id", "tag", "name", "wait"));
		else  ReinitArgs(array("mode", "id", "tag", "wait"));

		$modes = array(
			"id" => "Droplet ID"
		);

		// Tags are only available for certain action types.
		if ($api === "enable-backups" || $api === "disable-backups" || $api === "power-cycle" || $api === "shutdown" || $api === "power-off" || $api === "power-on" || $api === "enable-ipv6" || $api === "enable-private-networking" || $api === "snapshot")  $modes["tag"] = "Tag name";

		$mode = CLI::GetLimitedUserInputWithArgs($args, "mode", "Mode", "id", "Available modes:", $modes, true, $suppressoutput);

		if ($mode === "id")
		{
			$result = GetDropletID();
			$id = $result["droplet"]["id"];
		}
		else if ($mode === "tag")
		{
			$result = GetTagName();
			$tagname = $result["tag"]["name"];
		}

		$defaultwait = 5;
		$initwait = array();
		$actionvalues = array();

		// Some actions take a little while to complete.
		if ($api === "reboot" || $api === "power-cycle")  $initwait = array(10);
		else if ($api === "restore")
		{
			// Select a backup image.
			if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "image"))  $image = CLI::GetUserInputWithArgs($args, "image", "Droplet backup image", false, "", $suppressoutput);
			else
			{
				$images = array();
				$result2 = $do->DropletsBackupsList($id);
				if (!$result2["success"])  DisplayResult($result2);

				$images2 = array();
				foreach ($result2["data"] as $image)
				{
					$images2[(isset($image["slug"]) ? $image["slug"] : $image["id"])] = ((int)$image["name"] > 0 ? $image["distribution"] . " " . $image["name"] : $image["name"] . " " . $image["distribution"]) . "\n\t" . (isset($image["size_gigabytes"]) ? $image["size_gigabytes"] : "Under " . $image["min_disk_size"]) . "GB, " . $image["type"] . ", " . ($image["public"] ? "public, " . $type : "private") . "\n";
				}

				ksort($images2);

				foreach ($images2 as $id => $disp)  $images[$id] = $disp;

				if (!count($images))  CLI::DisplayError("No Droplet backup images are available.  Have you fully enabled automated backups for this Droplet?");
				$image = CLI::GetLimitedUserInputWithArgs($args, "image", "Droplet backup image", false, "Available Droplet backup images:", $images, true, $suppressoutput);
			}

			$actionvalues["image"] = $image;
			$initwait = array(10);
		}
		else if ($api === "resize")
		{
			// Get Droplet size.
			$result = GetDropletSize("Current Droplet size is '" . $result["droplet"]["size"]["slug"] . "'.");
			$size = $result["size"];
			$disksize = $result["disksize"];

			$permanent = CLI::GetYesNoUserInputWithArgs($args, "permanent", "Permanent disk resize", "Y", "This next question affects whether or not you can select a smaller Droplet size in the future.  At the moment, DigitalOcean can enlarge disk size but not shrink it again.  If you say no, you can temporarily use the CPU and RAM of a larger instance but not the disk storage so that you can go back to the smaller instance later.", $suppressoutput);

			$actionvalues["disk"] = $permanent;
			$actionvalues["size"] = $size;
			$initwait = array(10);
		}
		else if ($api === "rebuild")
		{
			// Get supported Droplet image.  Select the current image as the default (if possible).
			$result = GetDropletImage("Droplet image", (isset($result["droplet"]["image"]["slug"]) ? $result["droplet"]["image"]["slug"] : $result["droplet"]["image"]["id"]), $result["droplet"]["size"]["disk"], $result["droplet"]["region"]["slug"]);
			$image = $result["id"];

			$actionvalues["image"] = $image;
			$defaultwait = 10;
			$initwait = array(5, 25, 15, 10, 5);
		}
		else if ($api === "rename")
		{
			$newname = CLI::GetUserInputWithArgs($args, "name", "New name", false, "", $suppressoutput);

			$actionvalues["name"] = $newname;
			$defaultwait = 2;
		}
		else if ($api === "change-kernel")
		{
			if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "kernel"))  $image = CLI::GetUserInputWithArgs($args, "kernel", "Kernel ID", false, "", $suppressoutput);
			else
			{
				$result2 = $do->DropletsKernelsList($id);
				if (!$result2["success"])  DisplayResult($result2);

				$kernels = array();
				foreach ($result2["data"] as $kernel)
				{
					$kernels[$kernel["id"]] = $kernel["name"];
				}

				natcasesort($kernels);

				if (!count($kernels))  CLI::DisplayError("No kernels are available for this Droplet.  The Droplet itself might need manual attention via the DigitalOcean web interface.");
				$kernel = CLI::GetLimitedUserInputWithArgs($args, "kernel", "Kernel ID", false, "Available kernels for this Droplet:", $kernels, true, $suppressoutput);
			}

			$actionvalues["kernel"] = $kernel;
			$initwait = array(10);
		}
		else if ($api === "snapshot")
		{
			$name = CLI::GetUserInputWithArgs($args, "name", "Snapshot name", false, "", $suppressoutput);

			$actionvalues["name"] = $name;
			$defaultwait = 15;
			$initwait = array(45);
		}

		// Wait for action completion.
		$wait = CLI::GetYesNoUserInputWithArgs($args, "wait", "Wait for completion", "Y", "The next question involves whether or not to wait for the Droplet action to complete.  If you don't want to wait, you can use the 'action' ID that is returned to wait later on for completion of the task.", $suppressoutput);

		if ($mode === "id")  DisplayResult($do->DropletActionsByID($id, $api, $actionvalues), $wait, $defaultwait, $initwait);
		else if ($mode === "tag")  DisplayResult($do->DropletActionsByTag($tagname, $api, $actionvalues), $wait, $defaultwait, $initwait);
	}
	else if ($apigroup === "firewalls")
	{
		// Firewalls.
		if ($api === "list")  DisplayResult($do->FirewallsList());
		else if ($api === "create")
		{
			ReinitArgs(array("name", "inboundrules", "outboundrules"));

			$name = CLI::GetUserInputWithArgs($args, "name", "Firewall name", false, "", $suppressoutput);

			do
			{
				do
				{
					$valid = false;
					$filename = CLI::GetUserInputWithArgs($args, "inboundrules", "Inbound rules filename", false, "", $suppressoutput);
					if (!file_exists($filename))  CLI::DisplayError("The file '" . $filename . "' does not exist.", false, false);
					else
					{
						$inboundrules = json_decode(file_get_contents($filename), true);

						if (!is_array($inboundrules))  CLI::DisplayError("The file '" . $filename . "' does not contain valid JSON.", false, false);
						else
						{
							$inboundrules = $do->NormalizeFirewallRules($inboundrules, "inbound");

							$valid = true;
						}
					}
				} while (!$valid);

				do
				{
					$valid = false;
					$filename = CLI::GetUserInputWithArgs($args, "outboundrules", "Outbound rules filename", false, "", $suppressoutput);
					if (!file_exists($filename))  CLI::DisplayError("The file '" . $filename . "' does not exist.", false, false);
					else
					{
						$outboundrules = json_decode(file_get_contents($filename), true);

						if (!is_array($outboundrules))  CLI::DisplayError("The file '" . $filename . "' does not contain valid JSON.", false, false);
						else
						{
							$outboundrules = $do->NormalizeFirewallRules($outboundrules, "outbound");

							$valid = true;
						}
					}
				} while (!$valid);

				if (!count($inboundrules) && !count($outboundrules))  CLI::DisplayError("At least one valid inbound or outbound rule must be specified.", false, false);

			} while (!count($inboundrules) && !count($outboundrules));

			DisplayResult($do->FirewallsCreate($name, $inboundrules, $outboundrules));
		}
		else if ($api === "update")
		{
			ReinitArgs(array("id", "name", "inboundrules", "outboundrules"));

			$result = GetFirewallID();
			$id = $result["firewall"]["id"];
			$info = $result["firewall"];

			$name = CLI::GetUserInputWithArgs($args, "name", "Firewall name", $info["name"], "", $suppressoutput);

			do
			{
				do
				{
					$valid = false;
					$filename = CLI::GetUserInputWithArgs($args, "inboundrules", "Inbound rules filename", "", "", $suppressoutput);
					if ($filename === "")
					{
						$inboundrules = $info["inbound_rules"];

						$valid = true;
					}
					else if (!file_exists($filename))
					{
						CLI::DisplayError("The file '" . $filename . "' does not exist.", false, false);
					}
					else
					{
						$inboundrules = json_decode(file_get_contents($filename), true);

						if (!is_array($inboundrules))  CLI::DisplayError("The file '" . $filename . "' does not contain valid JSON.", false, false);
						else
						{
							$inboundrules = $do->NormalizeFirewallRules($inboundrules, "inbound");

							$valid = true;
						}
					}
				} while (!$valid);

				do
				{
					$valid = false;
					$filename = CLI::GetUserInputWithArgs($args, "outboundrules", "Outbound rules filename", "", "", $suppressoutput);
					if ($filename === "")
					{
						$outboundrules = $info["outbound_rules"];

						$valid = true;
					}
					else if (!file_exists($filename))
					{
						CLI::DisplayError("The file '" . $filename . "' does not exist.", false, false);
					}
					else
					{
						$outboundrules = json_decode(file_get_contents($filename), true);

						if (!is_array($outboundrules))  CLI::DisplayError("The file '" . $filename . "' does not contain valid JSON.", false, false);
						else
						{
							$outboundrules = $do->NormalizeFirewallRules($outboundrules, "outbound");

							$valid = true;
						}
					}
				} while (!$valid);

				if (!count($inboundrules) && !count($outboundrules))  CLI::DisplayError("At least one valid inbound or outbound rule must be specified.", false, false);

			} while (!count($inboundrules) && !count($outboundrules));

			DisplayResult($do->FirewallsUpdate($id, $name, $inboundrules, $outboundrules, $info["droplet_ids"], $info["tags"]));
		}
		else if ($api === "add-tag" || $api === "remove-tag")
		{
			ReinitArgs(array("id", "tag"));

			$result = GetFirewallID();
			$id = $result["firewall"]["id"];
			$info = $result["firewall"];

			$result = GetTagName();
			$tagname = $result["tag"]["name"];

			if ($api === "add-tag")
			{
				if (!in_array($tagname, $info["tags"]))  $info["tags"][] = $tagname;
			}
			else if ($api === "remove-tag")
			{
				$pos = array_search($tagname, $info["tags"]);
				if ($pos !== false)  array_splice($info["tags"], $pos, 1);
			}

			DisplayResult($do->FirewallsUpdate($id, $info["name"], $info["inbound_rules"], $info["outbound_rules"], $info["droplet_ids"], $info["tags"]));
		}
		else
		{
			ReinitArgs(array("id"));

			$result = GetFirewallID();
			$id = $result["firewall"]["id"];
			$info = $result["firewall"];

			if ($api === "get-info")  DisplayResult($result);
			else if ($api === "delete")  DisplayResult($do->FirewallsDelete($id));
			else if ($api === "add-droplet")
			{
				$result = GetDropletID();
				$dropletid = $result["droplet"]["id"];

				if (!in_array($dropletid, $info["droplet_ids"]))  $info["droplet_ids"][] = $dropletid;

				DisplayResult($do->FirewallsUpdate($id, $info["name"], $info["inbound_rules"], $info["outbound_rules"], $info["droplet_ids"], $info["tags"]));
			}
			else if ($api === "remove-droplet")
			{
				$result = GetDropletID();
				$dropletid = $result["droplet"]["id"];

				$pos = array_search($dropletid, $info["droplet_ids"]);
				if ($pos !== false)  array_splice($info["droplet_ids"], $pos, 1);

				DisplayResult($do->FirewallsUpdate($id, $info["name"], $info["inbound_rules"], $info["outbound_rules"], $info["droplet_ids"], $info["tags"]));
			}
		}
	}
	else if ($apigroup === "images")
	{
		// Images.
		if ($api === "list")
		{
			ReinitArgs(array("type"));

			$types = array(
				"all" => "All images",
				"dist" => "Just distribution images",
				"app" => "Just application images",
				"user" => "Just your images"
			);
			$type = CLI::GetLimitedUserInputWithArgs($args, "type", "Image type", "all", "Available image types:", $types, true, $suppressoutput);

			switch ($type)
			{
				case "all":  $extra = "";  break;
				case "dist":  $extra = "?type=distribution";  break;
				case "app":  $extra = "?type=application";  break;
				case "user":  $extra = "?private=true";  break;
			}

			DisplayResult($do->ImagesList(true, $extra));
		}
		else if ($api === "create")
		{
			ReinitArgs(array("name", "url", "distro", "desc"));

			$name = CLI::GetUserInputWithArgs($args, "name", "Image name", false, "", $suppressoutput);
			$url = CLI::GetUserInputWithArgs($args, "url", "Image URL", false, "For this next question, specify a URL to an image in raw, qcow2, vhdx, vdi, or vmdk format.  It must be under 100GB when uncompressed.", $suppressoutput);

			// Get supported Droplet region.
			$region = GetDropletRegion("Region", false, false, false, false, true, "");

			$distributions = array(
				"Arch Linux",
				"CentOS",
				"CoreOS",
				"Debian",
				"Fedora",
				"Fedora Atomic",
				"FreeBSD",
				"Gentoo",
				"openSUSE",
				"RancherOS",
				"Ubuntu",
				"Unknown"
			);

			$imageopts = array();
			$pos = CLI::GetLimitedUserInputWithArgs($args, "distro", "Distro", "Unknown", "Available distributions:", $distributions, true, $suppressoutput);
			$imageopts["distribution"] = $distributions[$pos];
			$imageopts["desc"] = CLI::GetUserInputWithArgs($args, "desc", "Description", "", "", $suppressoutput);

			DisplayResult($do->ImagesCreate($name, $url, $region, $imageopts));
		}
		else if ($api === "actions")
		{
			ReinitArgs(array("image", "pages"));

			// Retrieve an appropriate image.
			$result = GetDropletImage("Image", false, false, false, true);
			$image = $result["id"];

			$numpages = (int)CLI::GetUserInputWithArgs($args, "pages", "Number of pages to retrieve", "1", "", $suppressoutput);

			DisplayResult($do->ImagesActionsList($image, $numpages));
		}
		else if ($api === "rename")
		{
			ReinitArgs(array("image", "name"));

			// Retrieve an appropriate image.
			$result = GetDropletImage("Image", false, false, false, true);
			$image = $result["id"];

			$newname = CLI::GetUserInputWithArgs($args, "name", "New name", false, "", $suppressoutput);

			DisplayResult($do->ImagesRename($image, $newname));
		}
		else
		{
			ReinitArgs(array("image"));

			// Retrieve an appropriate image.
			$result = GetDropletImage("Image", false, false, false);
			$image = $result["id"];

			if ($api === "get-info")  DisplayResult($result);
			else if ($api === "delete")  DisplayResult($do->ImagesDelete($image));
		}
	}
	else if ($apigroup === "image-actions")
	{
		if ($api === "transfer")  ReinitArgs(array("image", "region", "wait"));
		else  ReinitArgs(array("image", "wait"));

		// Retrieve an appropriate image.
		$result = GetDropletImage("Image", false, false, false, true, ($api === "convert" ? "backup" : false));
		$image = $result["id"];

		$defaultwait = 60;
		$initwait = array(30);
		$actionvalues = array();

		if ($api === "transfer")
		{
			$region = GetDropletRegion("New region", false, false, false, false, false, "");

			$actionvalues["region"] = $region;
		}

		// Wait for action completion.
		$wait = CLI::GetYesNoUserInputWithArgs($args, "wait", "Wait for completion", "Y", "The next question involves whether or not to wait for the Image action to complete.  If you don't want to wait, you can use the 'action' ID that is returned to wait later on for completion of the task.", $suppressoutput);

		DisplayResult($do->ImageActionsByID($image, $api, $actionvalues), $wait, $defaultwait, $initwait);
	}
	else if ($apigroup === "load-balancers")
	{
		// Load balancers.
		if ($api === "list")  DisplayResult($do->LoadBalancersList());
		else if ($api === "create")
		{
			ReinitArgs(array("name", "region", "entryproto", "entryport", "targetproto", "targetport", "id", "algorithm", "healthproto", "healthport", "healthpath", "healthinterval", "healthtimeout", "healthfail", "healthpass", "stickytype", "stickycookie", "stickycookiettl", "mode", "tag"));

			$name = CLI::GetUserInputWithArgs($args, "name", "Load balancer name", false, "", $suppressoutput);

			$region = GetDropletRegion("Load balancer region", false, false, false, false, false, "");

			// Forwarding rule.
			$protocols = array(
				"http" => "HTTP",
				"https" => "HTTPS",
				"http2" => "HTTP/2",
				"tcp" => "TCP"
			);

			$entryproto = CLI::GetLimitedUserInputWithArgs($args, "entryproto", "Entry protocol", false, "The next few questions setup the first forwarding rule for the load balancer.  Available entry protocols:", $protocols, true, $suppressoutput);

			if ($entryproto === "http")  $entryport = 80;
			else if ($entryproto === "https" || $entryproto === "http2")  $entryport = 443;
			else  $entryport = false;

			do
			{
				$entryport = (int)CLI::GetUserInputWithArgs($args, "entryport", "Entry port", $entryport, "", $suppressoutput);
			} while ($entryport < 0 || $entryport > 65535);

			$targetproto = CLI::GetLimitedUserInputWithArgs($args, "targetproto", "Target protocol", $entryproto, "Available target protocols:", $protocols, true, $suppressoutput);

			do
			{
				$targetport = (int)CLI::GetUserInputWithArgs($args, "targetport", "Target port", $entryport, "", $suppressoutput);
			} while ($targetport < 0 || $targetport > 65535);

			if ($entryproto === "https" || $entryproto === "http2")
			{
				$certid = GetCertificateID("-", array("-" => "None"));
				$tlspassthrough = ($certid === "");
			}
			else
			{
				$certid = "";
				$tlspassthrough = false;
			}

			$forwardingrules = array(
				array(
					"entry_protocol" => $entryproto,
					"entry_port" => $entryport,
					"target_protocol" => $targetproto,
					"target_port" => $targetport,
					"certificate_id" => $certid,
					"tls_passthrough" => $tlspassthrough
				)
			);

			$algorithms = array(
				"round_robin" => "Round-robin",
				"least_connections" => "Least connections"
			);

			$algorithm = CLI::GetLimitedUserInputWithArgs($args, "algorithm", "Balancing algorithm", "round_robin", "Available balancing algorithms:", $algorithms, true, $suppressoutput);

			// Health check setup.
			$healthcheck = array();
			$protocols = array(
				"http" => "HTTP",
				"tcp" => "TCP"
			);

			$healthcheck["protocol"] = CLI::GetLimitedUserInputWithArgs($args, "healthproto", "Health check protocol", ($targetproto === "http" ? "http" : "tcp"), "The next few questions setup the health check for the load balancer.  Available health check protocols:", $protocols, true, $suppressoutput);

			if ($healthcheck["protocol"] === "http")  $healthport = 80;
			else  $healthport = false;

			$healthcheck["port"] = (int)CLI::GetUserInputWithArgs($args, "healthport", "Health check port", $healthport, "", $suppressoutput);
			$healthcheck["path"] = CLI::GetUserInputWithArgs($args, "healthpath", "Health check path", "/", "", $suppressoutput);
			$healthcheck["check_interval_seconds"] = (int)CLI::GetUserInputWithArgs($args, "healthinterval", "Health check interval", "10", "", $suppressoutput);
			$healthcheck["response_timeout_seconds"] = (int)CLI::GetUserInputWithArgs($args, "healthtimeout", "Health check timeout", "5", "The number of seconds the Load Balancer instance will wait for a response until marking a health check as failed.", $suppressoutput);
			$healthcheck["unhealthy_threshold"] = (int)CLI::GetUserInputWithArgs($args, "healthfail", "Health check failure threshold", "3", "The number of times a health check must fail for a backend Droplet to be marked 'unhealthy' and be removed from the pool.", $suppressoutput);
			$healthcheck["healthy_threshold"] = (int)CLI::GetUserInputWithArgs($args, "healthpass", "Health check passing threshold", "5", "The number of times a health check must pass for a backend Droplet to be marked 'healthy' and be re-added to the pool.", $suppressoutput);

			// Sticky sessions setup.
			$stickysessions = array();
			$stickytypes = array(
				"none" => "None",
				"cookies" => "HTTP cookies"
			);

			$stickysessions["type"] = CLI::GetLimitedUserInputWithArgs($args, "stickytype", "Sticky session type", "none", "The next few questions setup the sticky session options for the load balancer, which allow the load balancer to pass traffic onto the same backend for each request by a client.  Note that for sticky cookie sessions to work, the load balancer has to decrypt all traffic.  Available sticky session types:", $stickytypes, true, $suppressoutput);

			if ($stickytype === "cookies")
			{
				$stickysessions["cookie_name"] = CLI::GetUserInputWithArgs($args, "stickycookie", "Sticky cookie name", "LB_DO", "", $suppressoutput);
				$stickysessions["cookie_ttl_seconds"] = CLI::GetUserInputWithArgs($args, "stickycookiettl", "Sticky cookie TTL", "600", "The TTL is the amount of time, in seconds, that sticky cookies will be valid for.", $suppressoutput);
			}

			// Balancer options.
			$balanceropts = array(
				"algorithm" => $algorithm,
				"health_check" => $healthcheck,
				"sticky_sessions" => $stickysessions
			);

			// Load balnacer mode.
			$modes = array(
				"tag" => "Tag",
				"droplets" => "Droplets"
			);

			$mode = CLI::GetLimitedUserInputWithArgs($args, "mode", "Load balancer mode", "tag", "The load balancer can operate in either Droplet or tag mode.  Using tag mode is highly recommended for simplified management of Droplets.  Available load balancer modes:", $modes, true, $suppressoutput);

			if ($mode === "tag")
			{
				$result = GetTagName();
				$balanceropts["tag"] = $result["tag"]["name"];
			}
			else if ($mode === "droplets")
			{
				$result = GetDropletID("", $region);
				$balanceropts["droplet_ids"] = array($result["droplet"]["id"]);
			}

			DisplayResult($do->LoadBalancersCreate($name, $region, $forwardingrules, $balanceropts));
		}
		else if ($api === "update")
		{
			ReinitArgs(array("id", "name", "algorithm", "healthproto", "healthport", "healthpath", "healthinterval", "healthtimeout", "healthfail", "healthpass", "stickytype", "stickycookie", "stickycookiettl"));

			$result = GetLoadBalancerID();
			$id = $result["load_balancer"]["id"];
			$info = $result["load_balancer"];

			$name = CLI::GetUserInputWithArgs($args, "name", "Load balancer name", $info["name"], "", $suppressoutput);

			$algorithms = array(
				"round_robin" => "Round-robin",
				"least_connections" => "Least connections"
			);

			$algorithm = CLI::GetLimitedUserInputWithArgs($args, "algorithm", "Balancing algorithm", $info["algorithm"], "Available balancing algorithms:", $algorithms, true, $suppressoutput);

			// Health check setup.
			$healthcheck = array();
			$protocols = array(
				"http" => "HTTP",
				"tcp" => "TCP"
			);

			$healthcheck["protocol"] = CLI::GetLimitedUserInputWithArgs($args, "healthproto", "Health check protocol", $info["health_check"]["protocol"], "The next few questions setup the health check for the load balancer.  Available health check protocols:", $protocols, true, $suppressoutput);
			$healthcheck["port"] = (int)CLI::GetUserInputWithArgs($args, "healthport", "Health check port", $info["health_check"]["port"], "", $suppressoutput);
			$healthcheck["path"] = CLI::GetUserInputWithArgs($args, "healthpath", "Health check path", $info["health_check"]["path"], "", $suppressoutput);
			$healthcheck["check_interval_seconds"] = (int)CLI::GetUserInputWithArgs($args, "healthinterval", "Health check interval", $info["health_check"]["check_interval_seconds"], "", $suppressoutput);
			$healthcheck["response_timeout_seconds"] = (int)CLI::GetUserInputWithArgs($args, "healthtimeout", "Health check timeout", $info["health_check"]["response_timeout_seconds"], "The number of seconds the Load Balancer instance will wait for a response until marking a health check as failed.", $suppressoutput);
			$healthcheck["unhealthy_threshold"] = (int)CLI::GetUserInputWithArgs($args, "healthfail", "Health check failure threshold", $info["health_check"]["unhealthy_threshold"], "The number of times a health check must fail for a backend Droplet to be marked 'unhealthy' and be removed from the pool.", $suppressoutput);
			$healthcheck["healthy_threshold"] = (int)CLI::GetUserInputWithArgs($args, "healthpass", "Health check passing threshold", $info["health_check"]["healthy_threshold"], "The number of times a health check must pass for a backend Droplet to be marked 'healthy' and be re-added to the pool.", $suppressoutput);

			// Sticky sessions setup.
			$stickysessions = array();
			$stickytypes = array(
				"none" => "None",
				"cookies" => "HTTP cookies"
			);

			$stickysessions["type"] = CLI::GetLimitedUserInputWithArgs($args, "stickytype", "Sticky session type", $info["sticky_sessions"]["type"], "The next few questions setup the sticky session options for the load balancer, which allow the load balancer to pass traffic onto the same backend for each request by a client.  Note that for sticky cookie sessions to work, the load balancer has to decrypt all traffic.  Available sticky session types:", $stickytypes, true, $suppressoutput);

			if ($stickysessions["type"] === "cookies")
			{
				$stickysessions["cookie_name"] = CLI::GetUserInputWithArgs($args, "stickycookie", "Sticky cookie name", (isset($info["sticky_sessions"]["cookie_name"]) ? $info["sticky_sessions"]["cookie_name"] : "LB_DO"), "", $suppressoutput);
				$stickysessions["cookie_ttl_seconds"] = CLI::GetUserInputWithArgs($args, "stickycookiettl", "Sticky cookie TTL", (isset($info["sticky_sessions"]["cookie_ttl_seconds"]) ? $info["sticky_sessions"]["cookie_ttl_seconds"] : "600"), "The TTL is the amount of time, in seconds, that sticky cookies will be valid for.", $suppressoutput);
			}

			// Balancer options.
			$balanceropts = array(
				"algorithm" => $algorithm,
				"health_check" => $healthcheck,
				"sticky_sessions" => $stickysessions,
				"redirect_http_to_https" => $info["redirect_http_to_https"],
				"enable_proxy_protocol" => $info["enable_proxy_protocol"]
			);

			if ($info["tag"] !== "")  $balanceropts["tag"] = $info["tag"];
			else  $balanceropts["droplet_ids"] = $info["droplet_ids"];

			DisplayResult($do->LoadBalancersUpdate($id, $name, $info["region"]["slug"], $info["forwarding_rules"], $balanceropts));
		}
		else if ($api === "add-forwarding-rule")
		{
			ReinitArgs(array("id", "entryproto", "entryport", "targetproto", "targetport"));

			$result = GetLoadBalancerID();
			$id = $result["load_balancer"]["id"];
			$info = $result["load_balancer"];

			$protocols = array(
				"http" => "HTTP",
				"https" => "HTTPS",
				"http2" => "HTTP/2",
				"tcp" => "TCP"
			);

			$entryproto = CLI::GetLimitedUserInputWithArgs($args, "entryproto", "Entry protocol", false, "Available entry protocols:", $protocols, true, $suppressoutput);

			if ($entryproto === "http")  $entryport = 80;
			else if ($entryproto === "https" || $entryproto === "http2")  $entryport = 443;
			else  $entryport = false;

			do
			{
				$entryport = (int)CLI::GetUserInputWithArgs($args, "entryport", "Entry port", $entryport, "", $suppressoutput);
			} while ($entryport < 0 || $entryport > 65535);

			$targetproto = CLI::GetLimitedUserInputWithArgs($args, "targetproto", "Target protocol", $entryproto, "Available target protocols:", $protocols, true, $suppressoutput);

			do
			{
				$targetport = (int)CLI::GetUserInputWithArgs($args, "targetport", "Target port", $entryport, "", $suppressoutput);
			} while ($targetport < 0 || $targetport > 65535);

			if ($entryproto === "https" || $entryproto === "http2")
			{
				$certid = GetCertificateID("-", array("-" => "None"));
				$tlspassthrough = ($certid === "");
			}
			else
			{
				$certid = "";
				$tlspassthrough = false;
			}

			$info["forwarding_rules"][] = array(
				"entry_protocol" => $entryproto,
				"entry_port" => $entryport,
				"target_protocol" => $targetproto,
				"target_port" => $targetport,
				"certificate_id" => $certid,
				"tls_passthrough" => $tlspassthrough
			);

			// Balancer options.
			$balanceropts = array(
				"algorithm" => $info["algorithm"],
				"health_check" => $info["health_check"],
				"sticky_sessions" => $info["sticky_sessions"],
				"redirect_http_to_https" => $info["redirect_http_to_https"],
				"enable_proxy_protocol" => $info["enable_proxy_protocol"]
			);

			if ($info["tag"] !== "")  $balanceropts["tag"] = $info["tag"];
			else  $balanceropts["droplet_ids"] = $info["droplet_ids"];

			DisplayResult($do->LoadBalancersUpdate($id, $info["name"], $info["region"]["slug"], $info["forwarding_rules"], $balanceropts));
		}
		else if ($api === "remove-forwarding-rule")
		{
			ReinitArgs(array("id", "rule"));

			$result = GetLoadBalancerID();
			$id = $result["load_balancer"]["id"];
			$info = $result["load_balancer"];

			$rules = array();
			foreach ($info["forwarding_rules"] as $num => $rule)
			{
				$rules[$num + 1] = $rule["entry_protocol"] . ":" . $rule["entry_port"] . " => " . $rule["target_protocol"] . ":" . $rule["target_port"];
			}

			$num = CLI::GetLimitedUserInputWithArgs($args, "rule", "Forwarding rule", false, "Available load balancer forwarding rules:", $rules, true, $suppressoutput);
			$num--;

			if (isset($info["forwarding_rules"][$num]))  array_splice($info["forwarding_rules"], $num, 1);

			// Balancer options.
			$balanceropts = array(
				"algorithm" => $info["algorithm"],
				"health_check" => $info["health_check"],
				"sticky_sessions" => $info["sticky_sessions"],
				"redirect_http_to_https" => $info["redirect_http_to_https"],
				"enable_proxy_protocol" => $info["enable_proxy_protocol"]
			);

			if ($info["tag"] !== "")  $balanceropts["tag"] = $info["tag"];
			else  $balanceropts["droplet_ids"] = $info["droplet_ids"];

			DisplayResult($do->LoadBalancersUpdate($id, $info["name"], $info["region"]["slug"], $info["forwarding_rules"], $balanceropts));
		}
		else
		{
			ReinitArgs(array("id"));

			$result = GetLoadBalancerID();
			$id = $result["load_balancer"]["id"];
			$info = $result["load_balancer"];

			if ($api === "get-info")  DisplayResult($result);
			else if ($api === "delete")  DisplayResult($do->LoadBalancersDelete($id));
			else if ($api === "add-droplet")
			{
				$result = GetDropletID("", $info["region"]["slug"]);
				$dropletid = $result["droplet"]["id"];

				if (!in_array($dropletid, $info["droplet_ids"]))  $info["droplet_ids"][] = $dropletid;

				// Balancer options.
				$balanceropts = array(
					"algorithm" => $info["algorithm"],
					"health_check" => $info["health_check"],
					"sticky_sessions" => $info["sticky_sessions"],
					"redirect_http_to_https" => $info["redirect_http_to_https"],
					"enable_proxy_protocol" => $info["enable_proxy_protocol"]
				);

				if ($info["tag"] !== "")  $balanceropts["tag"] = $info["tag"];
				else  $balanceropts["droplet_ids"] = $info["droplet_ids"];

				DisplayResult($do->LoadBalancersUpdate($id, $info["name"], $info["region"]["slug"], $info["forwarding_rules"], $balanceropts));
			}
			else if ($api === "remove-droplet")
			{
				$result = GetDropletID("", $info["region"]["slug"]);
				$dropletid = $result["droplet"]["id"];

				$pos = array_search($dropletid, $info["droplet_ids"]);
				if ($pos !== false)  array_splice($info["droplet_ids"], $pos, 1);

				// Balancer options.
				$balanceropts = array(
					"algorithm" => $info["algorithm"],
					"health_check" => $info["health_check"],
					"sticky_sessions" => $info["sticky_sessions"],
					"redirect_http_to_https" => $info["redirect_http_to_https"],
					"enable_proxy_protocol" => $info["enable_proxy_protocol"]
				);

				if ($info["tag"] !== "")  $balanceropts["tag"] = $info["tag"];
				else  $balanceropts["droplet_ids"] = $info["droplet_ids"];

				DisplayResult($do->LoadBalancersUpdate($id, $info["name"], $info["region"]["slug"], $info["forwarding_rules"], $balanceropts));
			}
		}
	}
	else if ($apigroup === "projects")
	{
		// Projects.
		if ($api === "list")  DisplayResult($do->ProjectsList());
		else if ($api === "create" || $api === "update")
		{
			if ($api === "create")  ReinitArgs(array("name", "desc", "purpose", "env", "default"));
			else
			{
				ReinitArgs(array("id", "name", "desc", "purpose", "env", "default"));

				$id = GetProjectID();

				$info = $do->ProjectsGetInfo($id);
				if (!$info["success"])  DisplayResult($info);
			}

			$name = CLI::GetUserInputWithArgs($args, "name", "Name", ($api === "update" ? $info["project"]["name"] : false), "", $suppressoutput);
			$purpose = CLI::GetUserInputWithArgs($args, "purpose", "Purpose", ($api === "update" ? $info["project"]["purpose"] : false), "", $suppressoutput);

			$projectopts = array();
			$projectopts["description"] = CLI::GetUserInputWithArgs($args, "desc", "Description", ($api === "update" ? $info["project"]["description"] : ""), "", $suppressoutput);

			$environments = array(
				"dev" => "Development",
				"staging" => "Staging",
				"prod" => "Production"
			);

			$env = CLI::GetLimitedUserInputWithArgs($args, "env", "Environment", ($api === "update" ? $info["project"]["environment"] : false), "Available environments:", $environments, true, $suppressoutput);
			$projectopts["environment"] = $environments[$env];

			if ($api === "update")  $projectopts["is_default"] = CLI::GetYesNoUserInputWithArgs($args, "default", "Set as default project", ($info["project"]["is_default"] ? "Y" : "N"), "", $suppressoutput);

			if ($api === "create")  DisplayResult($do->ProjectsCreate($name, $purpose, $projectopts));
			else  DisplayResult($do->ProjectsUpdate($id, $name, $purpose, $projectopts));
		}
		else
		{
			ReinitArgs(array("id"));

			$id = GetProjectID();

			if ($api === "get-info")  DisplayResult($do->ProjectsGetInfo($id));
		}
	}
	else if ($apigroup === "project-resources")
	{
		// Project resources.
		if ($api === "list")
		{
			ReinitArgs(array("id"));

			$id = GetProjectID();

			DisplayResult($do->ProjectResourcesList($id));
		}
		else if ($api === "assign")
		{
			ReinitArgs(array("id", "urn"));

			$id = GetProjectID("Destination project ID");

			// Get resource URNs.
			$done = false;
			$resources = array();
			if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "urn"))
			{
				do
				{
					$urn = CLI::GetUserInputWithArgs($args, "urn", (count($tags) ? "Another resource URN" : "Resource URN"), "", "", $suppressoutput);
					if ($urn !== "")  $resources[] = $urn;
				} while ($urn !== "" && ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "urn")));

				if ($urn === "")  $done = true;
			}

			if (!$done)
			{
				// Get all resource URNs except for those assigned to the current project ID.
				$result = $do->ProjectsList();
				if (!$result["success"])  DisplayResult($result);

				$urns = array();
				foreach ($result["data"] as $project)
				{
					if ($project["id"] === $id)  continue;

					$result2 = $do->ProjectResourcesList($project["id"]);
					if (!$result2["success"])  DisplayResult($result2);

					foreach ($result2["data"] as $resource)
					{
						$urns[$resource["urn"]] = $project["name"] . ", " . substr($resource["assigned_at"], 0, 10);
					}
				}

				if (count($urns))
				{
					$urns2 = CLI::GetLimitedUserInputWithArgs($args, "urn", "Resource URN", "", "Available resource URNs:", $urns, true, $suppressoutput, array("exit" => "", "nextquestion" => "Another resource URN", "nextdefault" => ""));
					foreach ($urns2 as $urn)  $resources[] = $urn;
				}
			}

			if (!count($resources))  CLI::DisplayError("No resource URNs specified.");

			DisplayResult($do->ProjectResourcesAssign($id, $resources));
		}
	}
	else if ($apigroup === "snapshots")
	{
		// Snapshots.
		if ($api === "list")
		{
			ReinitArgs(array("type"));

			$types = array(
				"all" => "All snapshots",
				"droplet" => "Just droplet snapshots",
				"volume" => "Just volume snapshots"
			);
			$type = CLI::GetLimitedUserInputWithArgs($args, "type", "Snapshot type", "all", "Available snapshot types:", $types, true, $suppressoutput);

			switch ($type)
			{
				case "all":  $extra = "";  break;
				case "droplet":  $extra = "?resource_type=droplet";  break;
				case "volume":  $extra = "?resource_type=volume";  break;
			}

			DisplayResult($do->SnapshotsList(true, $extra));
		}
		else
		{
			ReinitArgs(array("snapshot"));

			// Retrieve snapshot.
			if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "snapshot"))
			{
				$id = CLI::GetUserInputWithArgs($args, "snapshot", "Snapshot", false, "", $suppressoutput);

				$result = $do->SnapshotsGetInfo($id);
				if (!$result["success"])  DisplayResult($result);
			}
			else
			{
				$result = $do->SnapshotsList();
				if (!$result["success"])  DisplayResult($result);

				$ids = array();
				$ids2 = array();
				foreach ($result["data"] as $snapshot)
				{
					$ids[$snapshot["id"]] = $snapshot["name"];
					$ids2[$snapshot["id"]] = $snapshot;
				}
				if (!count($ids))  CLI::DisplayError("No snapshots have been created.");
				$id = CLI::GetLimitedUserInputWithArgs($args, "snapshot", "Snapshot", false, "Available snapshots:", $ids, true, $suppressoutput);
				unset($result["data"]);
				$result["snapshot"] = $ids2[$id];
			}

			if ($api === "get-info")  DisplayResult($result);
			else if ($api === "delete")  DisplayResult($do->SnapshotsDelete($id));
		}
	}
	else if ($apigroup === "ssh-keys")
	{
		// SSH keys.
		if ($api === "list")  DisplayResult($do->SSHKeysList());
		else if ($api === "create")
		{
			ReinitArgs(array("name", "publickey"));

			$name = CLI::GetUserInputWithArgs($args, "name", "SSH key name", false, "", $suppressoutput);
			$publickey = CLI::GetUserInputWithArgs($args, "publickey", "SSH public key", false, "For this next question, you need a valid SSH public key.  The public key looks like 'ssh-rsa {really long string} {generator-info}'.  You can specify either the filename where the public key is stored or copy and paste the public key.  If you don't have a SSH key pair, try 'ssh-keygen -t rsa -b 4096' (*NIX), puttygen (Windows), or the CubicleSoft PHP SSH key generator.\n", $suppressoutput);
			if (file_exists($publickey))  $publickey = file_get_contents($publickey);

			DisplayResult($do->SSHKeysCreate($name, $publickey));
		}
		else if ($api === "rename")
		{
			ReinitArgs(array("sshkey", "name"));

			$result = GetSSHKey();

			$newname = CLI::GetUserInputWithArgs($args, "name", "New name", false, "", $suppressoutput);

			DisplayResult($do->SSHKeysRename($result["ssh_key"]["id"], $newname));
		}
		else
		{
			ReinitArgs(array("sshkey"));

			$result = GetSSHKey();

			if ($api === "get-info")  DisplayResult($result);
			else if ($api === "delete")  DisplayResult($do->SSHKeysDelete($result["ssh_key"]["id"]));
		}
	}
	else if ($apigroup === "regions")
	{
		// Regions.
		if ($api === "list")  DisplayResult($do->RegionsList());
	}
	else if ($apigroup === "sizes")
	{
		// Sizes.
		if ($api === "list")  DisplayResult($do->SizesList());
	}
	else if ($apigroup === "floating-ips")
	{
		// Floating IPs.
		if ($api === "list")  DisplayResult($do->FloatingIPsList());
		else if ($api === "create")
		{
			ReinitArgs(array("type", "id", "region", "wait"));

			$types = array(
				"droplet" => "Droplet",
				"region" => "Region"
			);
			$type = CLI::GetLimitedUserInputWithArgs($args, "type", "Create and assign to", false, "Available assignment types:", $types, true, $suppressoutput);

			if ($type === "droplet")
			{
				$result = GetDropletID();
				$id = $result["droplet"]["id"];
			}
			else if ($type === "region")
			{
				$id = GetDropletRegion("Region", false, false, false, false, false, "");
			}

			// Wait for action completion.
			$wait = CLI::GetYesNoUserInputWithArgs($args, "wait", "Wait for completion", "Y", "The next question involves whether or not to wait for the Floating IP action to complete.  If you don't want to wait, you can use the 'action' ID that is returned to wait later on for completion of the task.", $suppressoutput);

			DisplayResult($do->FloatingIPsCreate($type, $id), $wait);
		}
		else if ($api === "actions")
		{
			ReinitArgs(array("ipaddr", "pages"));

			$result = GetFloatingIPAddr();
			$ipaddr = $result["ipaddr"]["ip"];

			$numpages = (int)CLI::GetUserInputWithArgs($args, "pages", "Number of pages to retrieve", "1", "", $suppressoutput);

			DisplayResult($do->FloatingIPsActionsList($ipaddr, $numpages));
		}
		else
		{
			ReinitArgs(array("ipaddr"));

			$result = GetFloatingIPAddr();
			$ipaddr = $result["ipaddr"]["ip"];

			if ($api === "get-info")  DisplayResult($result);
			else if ($api === "delete")  DisplayResult($do->FloatingIPsDelete($ipaddr));
		}
	}
	else if ($apigroup === "floating-ip-actions")
	{
		if ($api === "assign")  ReinitArgs(array("ipaddr", "id", "wait"));
		else  ReinitArgs(array("ipaddr", "wait"));

		$result = GetFloatingIPAddr();
		$ipaddr = $result["ipaddr"]["ip"];

		$defaultwait = 5;
		$initwait = array();
		$actionvalues = array();

		if ($api === "assign")
		{
			$result2 = GetDropletID();
			$id = $result2["droplet"]["id"];

			$actionvalues["droplet_id"] = $id;
		}

		// Wait for action completion.
		$wait = CLI::GetYesNoUserInputWithArgs($args, "wait", "Wait for completion", "Y", "The next question involves whether or not to wait for the Floating IP action to complete.  If you don't want to wait, you can use the 'action' ID that is returned to wait later on for completion of the task.", $suppressoutput);

		DisplayResult($do->FloatingIPActionsByIP($ipaddr, $api, $actionvalues), $wait, $defaultwait, $initwait);
	}
	else if ($apigroup === "tags")
	{
		// Tags.
		if ($api === "list")  DisplayResult($do->TagsList());
		else if ($api === "create")
		{
			ReinitArgs(array("name"));

			$tagname = CLI::GetUserInputWithArgs($args, "name", "Tag name", false, "", $suppressoutput);

			DisplayResult($do->TagsCreate($tagname));
		}
		else if ($api === "attach" || $api === "detach")
		{
			ReinitArgs(array("tag", "num", "type", "id", "image"));

			$result = GetTagName();
			$tagname = $result["tag"]["name"];

			$resources = array();

			$num = (int)CLI::GetUserInputWithArgs($args, "num", "Number of resources", "1", "", $suppressoutput);

			while ($num)
			{
				$types = array(
					"droplet" => "Droplet",
					"image" => "Image",
					"volume" => "Volume"
				);
				$type = CLI::GetLimitedUserInputWithArgs($args, "type", "Resource type", false, "Available tagging resource types:", $types, true, $suppressoutput);

				if ($type === "droplet")
				{
					$result2 = GetDropletID();
					$id = $result2["droplet"]["id"];
				}
				else if ($type === "image")
				{
					$result2 = GetDropletImage("Image", false, false, false, true);
					$id = $result2["id"];
				}
				else if ($type === "volume")
				{
					$result2 = GetVolumeID();
					$id = $result2["volume"]["id"];
				}

				$resources[] = array(
					"resource_id" => $id,
					"resource_type" => $type
				);

				$num--;
			}

			if ($api === "attach")  DisplayResult($do->TagsAttach($tagname, $resources));
			else  DisplayResult($do->TagsDetach($tagname, $resources));
		}
		else
		{
			ReinitArgs(array("tag"));

			$result = GetTagName();
			$tagname = $result["tag"]["name"];

			if ($api === "get-info")  DisplayResult($result);
			else if ($api === "delete")  DisplayResult($do->TagsDelete($tagname));
		}
	}
	else if ($apigroup === "oauth")
	{
		if ($api === "revoke")
		{
			$sure = CLI::GetYesNoUserInputWithArgs($args, false, "Revoke API access", "N", "Are you really sure you want to revoke API access?  This action cannot be undone.  To use the API again, you'll go through the configuration setup process again during the next run.");

			if (!$sure)  CLI::LogMessage("[Notice] Nothing was done.  Your API access is still okay.  Whew!  That was a close one!");
			else
			{
				$result = $do->OAuthRevokeSelf();
				if ($result["success"])  @unlink($configfile);

				DisplayResult($result);
			}
		}
	}
?>