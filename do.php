<?php
	// DigitalOcean command-line shell for managing droplets.
	// (C) 2017 CubicleSoft.  All Rights Reserved.

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
		"userinput" => "="
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
		echo "\tphp " . $args["file"] . " droplets create name=test\n";
		echo "\tphp " . $args["file"] . " -c=altconfig.dat -s account get-info\n";

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
			"domains" => "Domains (DNS)",
			"domain-records" => "Domain records (DNS)",
			"droplets" => "Droplets",
			"droplet-actions" => "Droplet actions",
			"images" => "Images",
			"image-actions" => "Image actions",
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

	$apigroup = CLI::GetLimitedUserInputWithArgs($args, "apigroup", "API group", $default, "Available API groups:", $apigroups, true, $suppressoutput);

	// Get the API.
	switch ($apigroup)
	{
		case "account":  $apis = array("get-info" => "Get account information");  break;
		case "actions":  $apis = array("list" => "List actions", "get-info" => "Get information about an action", "wait" => "Wait for an action to complete");  break;
		case "volumes":  $apis = array("list" => "List Block Storage volumes", "create" => "Create a Block Storage volume", "get-info" => "Get information about a Block Storage volume", "snapshots" => "List all Block Storage volume snapshots", "snapshot" => "Create a Block Storage volume snapshot", "delete" => "Delete a Block Storage volume");  break;
		case "volume-actions":  $apis = array("attach" => "Attach a Block Storage volume to a Droplet", "detach" => "Detach a Block Storage volume from a Droplet", "resize" => "Enlarge a Block Storage volume");  break;
		case "domains":  $apis = array("list" => "List registered domains (DNS)", "create" => "Create a domain (TLD)", "get-info" => "Get information about a domain", "delete" => "Delete a domain and all domain records");  break;
		case "domain-records":  $apis = array("list" => "List DNS records for a domain", "create" => "Create a domain record (A, AAAA, etc.)", "update" => "Update a domain record", "get-info" => "Get information about a domain record", "delete" => "Delete a domain record");  break;
		case "droplets":  $apis = array("list" => "List Droplets", "create" => "Create a new Droplet", "get-info" => "Get information about a Droplet", "kernels" => "List all available kernels for a Droplet", "snapshots" => "List all Droplet snapshots", "backups" => "List all Droplet backups", "actions" => "List Droplet actions", "delete" => "Delete a single Droplet", "delete-by-tag" => "Delete all Droplets with a specific tag", "neighbors" => "List neighbors for a Droplet", "all-neighbors" => "List all neighbors for all Droplets");  break;
		case "droplet-actions":  $apis = array("enable-backups" => "Enable backups", "disable-backups" => "Disable backups", "reboot" => "Reboot (gentle)", "power-cycle" => "Power cycle (hard reset)", "shutdown" => "Shutdown (gentle)", "power-off" => "Power off (forced shutdown)", "power-on" => "Power on", "restore" => "Restore from a backup image", "password-reset" => "Password reset", "resize" => "Resize Droplet", "rebuild" => "Recreate/Rebuild Droplet with a specific image", "rename" => "Rename", "change-kernel" => "Change Droplet kernel", "enable-ipv6" => "Enable IPv6 support", "enable-private-networking" => "Enable Shared Private Networking", "snapshot" => "Create a snapshot image");  break;
		case "images":  $apis = array("list" => "List images (snapshots, backups, etc.)", "actions" => "List image actions", "rename" => "Rename image", "delete" => "Delete image", "get-info" => "Get information about an image");  break;
		case "image-actions":  $apis = array("transfer" => "Transfer/Copy an image to another region", "convert" => "Convert a backup to a snapshot");  break;
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

	if ($apigroup !== "setup")  $api = CLI::GetLimitedUserInputWithArgs($args, "api", "API", false, "Available APIs:", $apis, true, $suppressoutput);

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

	function GetTagName()
	{
		global $suppressoutput, $args, $do;

		if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "tag_name"))
		{
			$tagname = CLI::GetUserInputWithArgs($args, "tag_name", "Tag name", false, "", $suppressoutput);

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
			$tagname = CLI::GetLimitedUserInputWithArgs($args, "tag_name", "Tag name", false, "Available tags:", $tags, true, $suppressoutput);
			unset($result["data"]);
			$result["tag"] = $tags2[$tagname];
		}

		return $result;
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
			$numpages = (int)CLI::GetUserInputWithArgs($args, "pages", "Number of pages to retrieve", "1", "", $suppressoutput);

			DisplayResult($do->ActionsList($numpages));
		}
		else if ($api === "get-info")
		{
			$id = CLI::GetUserInputWithArgs($args, "id", "Action ID", false, "", $suppressoutput);

			DisplayResult($do->ActionsGetInfo($id));
		}
		else if ($api === "wait")
		{
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
			$name = CLI::GetUserInputWithArgs($args, "name", "Block Storage volume name", false, "", $suppressoutput);
			$desc = CLI::GetUserInputWithArgs($args, "desc", "Description", "", "", $suppressoutput);
			$size = (int)CLI::GetUserInputWithArgs($args, "size", "Size (in GB)", "1", "DigitalOcean Block Storage is approximately \$0.10 USD/month per GB.", $suppressoutput);
			if ($size < 1)  $size = 1;

			// Get supported Droplet region.
			$region = GetDropletRegion("Block Storage region", false, false, false, false, true, "");

			DisplayResult($do->VolumesCreate($name, $desc, $size, $region));
		}
		else
		{
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
	else if ($apigroup === "domains")
	{
		// Domains.
		if ($api === "list")  DisplayResult($do->DomainsList());
		else if ($api === "create")
		{
			$name = CLI::GetUserInputWithArgs($args, "name", "Domain name (TLD, no subdomains)", false, "", $suppressoutput);
			$ipaddr = CLI::GetUserInputWithArgs($args, "ip", "IP address to point the domain to", false, "", $suppressoutput);

			DisplayResult($do->DomainsCreate($name, $ipaddr));
		}
		else
		{
			$name = GetDomainName("name");

			if ($api === "get-info")  DisplayResult($do->DomainsGetInfo($name));
			else if ($api === "delete")  DisplayResult($do->DomainsDelete($name));
		}
	}
	else if ($apigroup === "domain-records")
	{
		// Domain records.
		$domainname = GetDomainName("domain");

		if ($api === "list")  DisplayResult($do->DomainRecordsList($domainname));
		else
		{
			if ($api !== "create")
			{
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
			}

			if ($api === "create" || $api === "update")
			{
				$types = array(
					"A" => "IPv4",
					"AAAA" => "IPv6",
					"CNAME" => "Canonical Name/Alias",
					"MX" => "Mail eXchange",
					"TXT" => "Arbitrary text such as a SPF record",
					"SRV" => "Service",
					"NS" => "Nameserver"
				);
				$type = CLI::GetLimitedUserInputWithArgs($args, "type", "DNS record type", ($api === "update" ? $result["record"]["type"] : false), "Available DNS record types:", $types, true, $suppressoutput);

				if ($type === "A" || $type === "AAAA" || $type === "CNAME" || $type === "TXT" || $type === "SRV")  $name = CLI::GetUserInputWithArgs($args, "name", "Name", ($api === "update" ? $result["record"]["name"] : "@"), "", $suppressoutput);
				else  $name = "";

				$data = CLI::GetUserInputWithArgs($args, "data", "Value/data", ($api === "update" ? $result["record"]["data"] : false), "", $suppressoutput);

				if ($type === "MX" || $type === "SRV")  $priority = (int)CLI::GetUserInputWithArgs($args, "priority", "Priority", ($api === "update" && isset($result["record"]["priority"]) ? $result["record"]["priority"] : "0"), "", $suppressoutput);
				else  $priority = null;

				if ($type === "SRV")  $port = (int)CLI::GetUserInputWithArgs($args, "port", "Port", ($api === "update" && isset($result["record"]["port"]) ? $result["record"]["port"] : "0"), "", $suppressoutput);
				else  $port = null;

				if ($type === "SRV")  $weight = (int)CLI::GetUserInputWithArgs($args, "weight", "Weight", ($api === "update" && isset($result["record"]["weight"]) ? $result["record"]["weight"] : "1"), "", $suppressoutput);
				else  $weight = null;

				$ttl = CLI::GetUserInputWithArgs($args, "ttl", "TTL", ($api === "update" ? $result["record"]["ttl"] : "1800"), "", $suppressoutput);

				if ($api === "create")  DisplayResult($do->DomainRecordsCreate($domainname, $type, $name, $data, $priority, $port, $weight, $ttl));
				else  DisplayResult($do->DomainRecordsUpdate($domainname, $id, array("type" => $type, "name" => $name, "data" => $data, "priority" => $priority, "port" => $port, "weight" => $weight, "ttl" => $ttl)));
			}
			else if ($api === "get-info")  DisplayResult($result);
			else if ($api === "delete")  DisplayResult($do->DomainRecordsDelete($domainname, $id));
		}
	}
	else if ($apigroup === "droplets")
	{
		// Droplets.
		if ($api === "list")
		{
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
					$ids2 = CLI::GetLimitedUserInputWithArgs($args, "ssh_key", "Volume ID", "", "Available Block Storage volumes:", $ids, true, $suppressoutput, array("exit" => "", "nextquestion" => "Another Volume ID", "nextdefault" => ""));
					foreach ($ids2 as $id)  $volumes[] = $id;
				}
			}

			// Select registered SSH public keys to add to the Droplet.
			$done = false;
			$sshkeys = array();
			if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "ssh_key"))
			{
				do
				{
					$id = CLI::GetUserInputWithArgs($args, "ssh_key", (count($sshkeys) ? "Another SSH key ID" : "SSH key ID"), "", "", $suppressoutput);
					if ($id !== "")  $sshkeys[] = $id;
				} while ($id !== "" && ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "ssh_key")));

				if ($id === "")  $done = true;
			}

			if (!$done)
			{
				$result = $do->SSHKeysList();
				if (!$result["success"])  DisplayResult($result);

				$ids = array();
				foreach ($result["data"] as $sshkey)  $ids[$sshkey["id"]] = $sshkey["name"] . " | " . $sshkey["fingerprint"];
				$ids2 = CLI::GetLimitedUserInputWithArgs($args, "ssh_key", "SSH key ID", "", "Available SSH keys:", $ids, true, $suppressoutput, array("exit" => "", "nextquestion" => "Another SSH key ID", "nextdefault" => ""));
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
			$result = GetTagName();
			$tagname = $result["tag"]["name"];

			DisplayResult($do->DropletsDeleteByTag($tagname));
		}
		else
		{
			$result = GetDropletID();
			$id = $result["droplet"]["id"];

			if ($api === "get-info")  DisplayResult($result);
			else if ($api === "kernels")  DisplayResult($do->DropletsKernelsList($id));
			else if ($api === "snapshots")  DisplayResult($do->DropletsSnapshotsList($id));
			else if ($api === "backups")  DisplayResult($do->DropletsBackupsList($id));
			else if ($api === "actions")
			{
				$numpages = (int)CLI::GetUserInputWithArgs($args, "pages", "Number of pages to retrieve", "1", "", $suppressoutput);

				DisplayResult($do->DropletsActionsList($id, $numpages));
			}
			else if ($api === "delete")  DisplayResult($do->DropletsDelete($id));
			else if ($api === "neighbors")  DisplayResult($do->DropletsNeighborsList($id));
		}
	}
	else if ($apigroup === "droplet-actions")
	{
		// Droplet actions.
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
	else if ($apigroup === "images")
	{
		// Images.
		if ($api === "list")
		{
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
		else
		{
			// Retrieve an appropriate image.
			$result = GetDropletImage("Image", false, false, false, ($api === "rename" || $api === "delete"));
			$image = $result["id"];

			if ($api === "get-info")  DisplayResult($result);
			else if ($api === "actions")
			{
				$numpages = (int)CLI::GetUserInputWithArgs($args, "pages", "Number of pages to retrieve", "1", "", $suppressoutput);

				DisplayResult($do->ImagesActionsList($image, $numpages));
			}
			else if ($api === "rename")
			{
				$newname = CLI::GetUserInputWithArgs($args, "name", "New name", false, "", $suppressoutput);

				DisplayResult($do->ImagesRename($image, $newname));
			}
			else if ($api === "delete")
			{
				DisplayResult($do->ImagesDelete($image));
			}
		}
	}
	else if ($apigroup === "image-actions")
	{
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
	else if ($apigroup === "snapshots")
	{
		// Snapshots.
		if ($api === "list")
		{
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
			$name = CLI::GetUserInputWithArgs($args, "name", "SSH key name", false, "", $suppressoutput);
			$publickey = CLI::GetUserInputWithArgs($args, "public_key", "SSH public key", false, "For this next question, you need a valid SSH public key.  The public key looks like 'ssh-rsa {really long string} {generator-info}'.  You can specify either the filename where the public key is stored or copy and paste the public key.  If you don't have a SSH key pair, try 'ssh-keygen -t rsa -b 4096' (*NIX), puttygen (Windows), or the CubicleSoft PHP SSH key generator.\n", $suppressoutput);
			if (file_exists($publickey))  $publickey = file_get_contents($publickey);

			DisplayResult($do->SSHKeysCreate($name, $publickey));
		}
		else
		{
			if ($suppressoutput || CLI::CanGetUserInputWithArgs($args, "ssh_key"))
			{
				$id = CLI::GetUserInputWithArgs($args, "ssh_key", "SSH key ID", false, "", $suppressoutput);

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
				$id = CLI::GetLimitedUserInputWithArgs($args, "ssh_key", "SSH key ID", false, "Available SSH keys:", $ids, true, $suppressoutput);
				unset($result["data"]);
				$result["ssh_key"] = $ids2[$id];
			}

			if ($api === "get-info")  DisplayResult($result);
			else if ($api === "rename")
			{
				$newname = CLI::GetUserInputWithArgs($args, "name", "New name", false, "", $suppressoutput);

				DisplayResult($do->SSHKeysRename($id, $newname));
			}
			else if ($api === "delete")  DisplayResult($do->SSHKeysDelete($id));
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
		else
		{
			$result = GetFloatingIPAddr();
			$ipaddr = $result["ipaddr"]["ip"];

			if ($api === "get-info")  DisplayResult($result);
			else if ($api === "actions")
			{
				$numpages = (int)CLI::GetUserInputWithArgs($args, "pages", "Number of pages to retrieve", "1", "", $suppressoutput);

				DisplayResult($do->FloatingIPsActionsList($ipaddr, $numpages));
			}
			else if ($api === "delete")
			{
				DisplayResult($do->FloatingIPsDelete($ipaddr));
			}
		}
	}
	else if ($apigroup === "floating-ip-actions")
	{
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
			$tagname = CLI::GetUserInputWithArgs($args, "tag_name", "Tag name", false, "", $suppressoutput);

			DisplayResult($do->TagsCreate($tagname));
		}
		else
		{
			$result = GetTagName();
			$tagname = $result["tag"]["name"];

			if ($api === "get-info")  DisplayResult($result);
			else if ($api === "attach")
			{
				$resources = array();

				$num = (int)CLI::GetUserInputWithArgs($args, "num", "Number of resources", "1", "", $suppressoutput);

				while ($num)
				{
					$types = array(
						"droplet" => "Droplet"
					);
					$type = CLI::GetLimitedUserInputWithArgs($args, "type", "Resource type", false, "Available tagging resource types:", $types, true, $suppressoutput);

					if ($type === "droplet")
					{
						$result2 = GetDropletID();
						$id = $result2["droplet"]["id"];
					}

					$resources[] = array(
						"resource_id" => $id,
						"resource_type" => $type
					);

					$num--;
				}

				DisplayResult($do->TagsAttach($tagname, $resources));
			}
			else if ($api === "detach")
			{
				$resources = array();

				$num = (int)CLI::GetUserInputWithArgs($args, "num", "Number of resources", "1", "", $suppressoutput);

				while ($num)
				{
					$types = array(
						"droplet" => "Droplet"
					);
					$type = CLI::GetLimitedUserInputWithArgs($args, "type", "Resource type", false, "Available tagging resource types:", $types, true, $suppressoutput);

					if ($type === "droplet")
					{
						$result2 = GetDropletID($tagname);
						$id = $result2["droplet"]["id"];
					}

					$resources[] = array(
						"resource_id" => $id,
						"resource_type" => $type
					);

					$num--;
				}

				DisplayResult($do->TagsDetach($tagname, $resources));
			}
			else if ($api === "delete")
			{
				DisplayResult($do->TagsDelete($tagname));
			}
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