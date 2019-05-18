<?php
	// CubicleSoft DigitalOcean PHP SDK.
	// (C) 2017 CubicleSoft.  All Rights Reserved.

	// Load dependencies.
	if (!class_exists("WebBrowser", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/web_browser.php";

	// Implements the full suite of DigitalOcean v2 APIs.
	class DigitalOcean
	{
		private $web, $fp, $debug, $accesstokens, $callbacks;

		public function __construct()
		{
			$this->web = new WebBrowser();
			$this->fp = false;
			$this->debug = false;

			$this->accesstokens = array(
				"clientid" => false,
				"clientsecret" => false,
				"clientscope" => false,
				"returnurl" => false,
				"refreshtoken" => false,
				"bearertoken" => false,
				"bearerexpirets" => -1,
			);

			$this->callbacks = array();
		}

		public function SetAccessTokens($info)
		{
			$this->web = new WebBrowser();
			if (is_resource($this->fp))  @fclose($this->fp);
			$this->fp = false;

			$this->accesstokens = array(
				"clientid" => false,
				"clientsecret" => false,
				"clientscope" => false,
				"returnurl" => false,
				"refreshtoken" => false,
				"bearertoken" => false,
				"bearerexpirets" => -1,
			);

			$this->accesstokens = array_merge($this->accesstokens, $info);

			if ($this->accesstokens["clientid"] === false && $this->accesstokens["clientsecret"] === false && $this->accesstokens["refreshtoken"] === false && $this->accesstokens["bearertoken"] === false)
			{
				$this->accesstokens["clientid"] = "a8a18c6f991462c8b964c2cf226e4aa577c736757cd7d98694c4d0a92839157b";
				$this->accesstokens["clientsecret"] = "e5f9424c1f4fb4b6a9de984df7cbdf7b2bd14802b589cbf19939bdfecd8e193f";
				$this->accesstokens["clientscope"] = array("read", "write");
				$this->accesstokens["returnurl"] = "https://localhost:23121";
				$this->accesstokens["bearerexpirets"] = -1;
			}
		}

		public function GetAccessTokens()
		{
			return $this->accesstokens;
		}

		public function AddAccessTokensUpdatedNotify($callback)
		{
			if (is_callable($callback))  $this->callbacks[] = $callback;
		}

		public function GetLoginURL()
		{
			if ($this->accesstokens["clientid"] === false && $this->accesstokens["clientsecret"] === false && $this->accesstokens["refreshtoken"] === false && $this->accesstokens["bearertoken"] === false)  $this->SetAccessTokens(array());

			$url = "https://cloud.digitalocean.com/v1/oauth/authorize?client_id=" . urlencode($this->accesstokens["clientid"]) . "&scope=" . urlencode(is_array($this->accesstokens["clientscope"]) ? implode(" ", $this->accesstokens["clientscope"]) : $this->accesstokens["clientscope"]) . "&response_type=code&redirect_uri=" . urlencode($this->accesstokens["returnurl"]);

			return $url;
		}

		public function InteractiveLogin()
		{
			if (!class_exists("CLI", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/cli.php";
			if (!class_exists("HTTP", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/http.php";

			// Reset refresh and bearer tokens.
			$this->accesstokens["refreshtoken"] = false;
			$this->accesstokens["bearertoken"] = false;
			$this->accesstokens["bearerexpirets"] = -1;

			echo self::DO_Translate("********************************************\n");
			echo self::DO_Translate("Starting interactive login for DigitalOcean.\n\n");
			echo self::DO_Translate("Please copy and paste the following OAuth2 URL into a web browser and sign in:\n\n");
			echo $this->GetLoginURL() . "\n";
			echo self::DO_Translate("********************************************\n");

			do
			{
				$valid = false;

				$tokens = $this->GetAccessTokens();
				$args = array("params" => array());
				$url = CLI::GetUserInputWithArgs($args, false, self::DO_Translate("Authorization URL"), false, self::DO_Translate("Once you sign in, your web browser will be redirected to an invalid URL that starts with '%s'.  That will be the Authorization URL to enter for the next question.", $tokens["returnurl"]));
				if (substr($url, 0, strlen($tokens["returnurl"])) !== $tokens["returnurl"])  CLI::DisplayError("The URL supplied is not valid.", false, false);
				else
				{
					$url = HTTP::ExtractURL($url);

					if (isset($url["queryvars"]["error"]))  CLI::DisplayError(self::DO_Translate("Unfortunately, an error occurred:  %s (%s).  Did you deny/cancel the consent?", $url["queryvars"]["error_description"][0], $url["queryvars"]["error"][0]), false, false);
					else
					{
						$result = $this->UpdateRefreshToken($url["queryvars"]["code"][0]);
						if (!$result["success"])  CLI::DisplayError(self::DO_Translate("Unfortunately, an error occurred while attempting to retrieve the refresh/bearer tokens:  %s (%s).  Try the process again.", $result["error"], $result["errorcode"]), false, false);
						else  $valid = true;
					}
				}
			} while (!$valid);

			echo "\n";
			echo self::DO_Translate("Interactive login completed successfully.\n");

			return array("success" => true);
		}

		public function UpdateRefreshToken($code)
		{
			if ($this->accesstokens["clientid"] === false && $this->accesstokens["clientsecret"] === false && $this->accesstokens["refreshtoken"] === false && $this->accesstokens["bearertoken"] === false)  $this->SetAccessTokens(array());

			$this->accesstokens["bearertoken"] = false;

			$options = array(
				"postvars" => array(
					"grant_type" => "authorization_code",
					"code" => $code,
					"client_id" => $this->accesstokens["clientid"],
					"client_secret" => $this->accesstokens["clientsecret"],
					"redirect_uri" => $this->accesstokens["returnurl"]
				),
				"sslopts" => self::InitSSLOpts(array())
			);

			$result = $this->RunAPI("POST", "https://cloud.digitalocean.com/v1/oauth/token", false, $options);
			if (!$result["success"])  return $result;

			$data = $result["data"][0];
			$this->accesstokens["refreshtoken"] = $data["refresh_token"];
			$this->accesstokens["bearertoken"] = $data["access_token"];
			$this->accesstokens["bearerexpirets"] = time() + (int)$data["expires_in"] - 30;

			return array("success" => true);
		}

		public function UpdateBearerToken()
		{
			if ($this->accesstokens["refreshtoken"] === false)  return array("success" => true);

			if ($this->accesstokens["bearerexpirets"] <= time())
			{
				$this->accesstokens["bearertoken"] = false;

				$options = array(
					"postvars" => array(
						"grant_type" => "refresh_token",
						"refresh_token" => $this->accesstokens["refreshtoken"]
					),
					"sslopts" => self::InitSSLOpts(array())
				);

				$result = $this->RunAPI("POST", "https://cloud.digitalocean.com/v1/oauth/token", false, $options);
				if (!$result["success"])  return $result;

				$data = $result["data"][0];
				$this->accesstokens["refreshtoken"] = $data["refresh_token"];
				$this->accesstokens["bearertoken"] = $data["access_token"];
				$this->accesstokens["bearerexpirets"] = time() + (int)$data["expires_in"] - 30;

				foreach ($this->callbacks as $callback)
				{
					if (is_callable($callback))  call_user_func_array($callback, array($this));
				}
			}

			return array("success" => true);
		}

		public function OAuthRevokeSelf()
		{
			if ($this->accesstokens["bearertoken"] === false)  return array("success" => true);

			$options = array(
				"postvars" => array(
					"token" => $this->accesstokens["bearertoken"]
				),
				"sslopts" => self::InitSSLOpts(array())
			);

			$result = $this->RunAPI("POST", "https://cloud.digitalocean.com/v1/oauth/revoke", false, $options);
			if (!$result["success"])  return $result;

			$this->SetAccessTokens(array());

			return $result;
		}

		public function SetDebug($debug)
		{
			$this->debug = (bool)$debug;
		}

		// Account.
		public function AccountGetInfo($apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "account" . $apiextra, "account", $options);
		}

		// Actions.
		public function ActionsList($numpages = 1, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "actions" . $apiextra, "actions", $numpages, $options);
		}

		public function ActionsGetInfo($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "actions/" . self::MakeValidID($id) . $apiextra, "action", $options);
		}

		public function WaitForActionCompletion($id, $defaultwait, $initwait = array(), $callback = false, $callbackopts = false, $apiextra = "", $options = array())
		{
			$result = $this->ActionsGetInfo($id, $apiextra, $options);
			if (!$result["success"])  return $result;

			if ($result["action"]["status"] !== "completed")
			{
				if (is_callable($callback))  call_user_func_array($callback, array(true, $result, &$callbackopts));

				do
				{
					if (count($initwait))  $wait = array_shift($initwait);
					else  $wait = $defaultwait;

					sleep($wait);

					$result = $this->ActionsGetInfo($id, $apiextra, $options);
					if (!$result["success"])  return $result;

					if (is_callable($callback))  call_user_func_array($callback, array(false, $result, &$callbackopts));

					if ($result["action"]["status"] === "completed")  break;

				} while (1);
			}

			return array("success" => true);
		}

		// Volumes.
		public function VolumesList($numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "volumes" . $apiextra, "volumes", $numpages, $options);
		}

		public function VolumesCreate($name, $desc, $size, $volumeopts = array(), $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("POST", "volumes" . $apiextra, "volume", self::MakeJSONOptions(array_merge(array("name" => $name, "description" => $desc, "size_gigabytes" => $size), $volumeopts), $options), 201);
		}

		public function VolumesGetInfo($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "volumes/" . $id . $apiextra, "volume", $options);
		}

		public function VolumesGetInfoByName($region, $name, $apiextra = "", $options = array())
		{
			return $this->VolumesGetInfo("", "?region=" . urlencode($region) . "&name=" . urlencode($name) . $apiextra, $options);
		}

		public function VolumesSnapshotsList($id, $numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "volumes/" . $id . "/snapshots" . $apiextra, "snapshots", $numpages, $options);
		}

		public function VolumeSnapshotCreate($id, $name, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("POST", "volumes/" . $id . "/snapshots" . $apiextra, "snapshot", self::MakeJSONOptions(array("name" => $name), $options), 201);
		}

		public function VolumesDelete($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "volumes/" . $id . $apiextra, $options);
		}

		public function VolumesDeleteByName($region, $name, $apiextra = "", $options = array())
		{
			return $this->VolumesDelete("", "?region=" . urlencode($region) . "&name=" . urlencode($name) . $apiextra, $options);
		}

		// Volume actions.
		public function VolumeActionsByID($id, $action, $actionvalues = array(), $apiextra = "", $options = array())
		{
			$action = preg_replace('/[^a-z]/', "_", strtolower(trim($action)));

			$result = $this->RunAPIGetOne("POST", "volumes/" . $id . "/actions" . $apiextra, "action", self::MakeJSONOptions(array_merge(array("type" => $action), $actionvalues), $options), 201);
			if (!$result["success"])  return $result;

			$result["actions"][] = $result["action"];
			unset($result["action"]);

			return $result;
		}

		// Content Devliery Networks (CDNs).
		public function CDNEndpointsList($numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "cdn/endpoints" . $apiextra, "endpoints", $numpages, $options);
		}

		public function CDNEndpointsCreate($origin, $cdnopts = array(), $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("POST", "cdn/endpoints" . $apiextra, "endpoint", self::MakeJSONOptions(array_merge(array("origin" => $origin), $cdnopts), $options), 201);
		}

		public function CDNEndpointsUpdate($id, $cdnopts, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("PUT", "cdn/endpoints/" . $id . $apiextra, "endpoint", self::MakeJSONOptions($cdnopts, $options));
		}

		public function CDNEndpointsGetInfo($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "cdn/endpoints/" . $id . $apiextra, "endpoint", $options);
		}

		public function CDNEndpointsPurge($id, $files, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "cdn/endpoints/" . $id . "/cache" . $apiextra, self::MakeJSONOptions(array("files" => $files), $options));
		}

		public function CDNEndpointsDelete($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "cdn/endpoints/" . $id . $apiextra, $options);
		}

		// Certificates.
		public function CertificatesList($numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "certificates" . $apiextra, "certificates", $numpages, $options);
		}

		public function CertificatesCreate($name, $type, $typevalues, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("POST", "certificates" . $apiextra, "certificate", self::MakeJSONOptions(array_merge(array("name" => $name, "type" => $type), $typevalues), $options), 201);
		}

		public function CertificatesGetInfo($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "certificates/" . $id . $apiextra, "certificate", $options);
		}

		public function CertificatesDelete($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "certificates/" . $id . $apiextra, $options);
		}

		// Database clusters.
		public function DatabaseClustersList($numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "databases" . $apiextra, "databases", $numpages, $options);
		}

		public function DatabaseClustersCreate($name, $engine, $size, $region, $numnodes, $clusteropts = array(), $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("POST", "databases" . $apiextra, "database", self::MakeJSONOptions(array_merge(array("name" => $name, "engine" => $engine, "size" => $size, "region" => $region, "num_nodes" => $numnodes), $clusteropts), $options), 201);
		}

		public function DatabaseClustersResize($id, $size, $numnodes, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("PUT", "databases/" . $id . "/resize" . $apiextra, self::MakeJSONOptions(array("size" => $size, "num_nodes" => $numnodes), $options), 202);
		}

		public function DatabaseClustersMigrate($id, $region, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("PUT", "databases/" . $id . "/migrate" . $apiextra, self::MakeJSONOptions(array("region" => $region), $options), 202);
		}

		public function DatabaseClustersMaintenance($id, $day, $hour, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("PUT", "databases/" . $id . "/maintenance" . $apiextra, self::MakeJSONOptions(array("day" => $day, "hour" => $hour), $options), 204);
		}

		public function DatabaseClustersBackups($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "databases/" . $id . "/backups" . $apiextra, "backups", $options);
		}

		public function DatabaseClustersGetInfo($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "databases/" . $id . $apiextra, "database", $options);
		}

		public function DatabaseClustersDelete($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "databases/" . $id . $apiextra, $options);
		}

		// Database cluster read-only replicas.
		public function DatabaseReplicasList($id, $numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "databases/" . $id . "/replicas" . $apiextra, "replicas", $numpages, $options);
		}

		public function DatabaseReplicasCreate($id, $name, $size, $region, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("POST", "databases/" . $id . "/replicas" . $apiextra, "replica", self::MakeJSONOptions(array("name" => $name, "size" => $size, "region" => $region), $options), 201);
		}

		public function DatabaseReplicasGetInfo($id, $name, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "databases/" . $id . "/replicas/" . $name . $apiextra, "replica", $options);
		}

		public function DatabaseReplicasDelete($id, $name, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "databases/" . $id . "/replicas/" . $name . $apiextra, $options);
		}

		// Database cluster users.
		public function DatabaseUsersList($id, $numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "databases/" . $id . "/users" . $apiextra, "users", $numpages, $options);
		}

		public function DatabaseUsersCreate($id, $name, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("POST", "databases/" . $id . "/users" . $apiextra, "user", self::MakeJSONOptions(array("name" => $name), $options), 201);
		}

		public function DatabaseUsersGetInfo($id, $name, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "databases/" . $id . "/users/" . $name . $apiextra, "user", $options);
		}

		public function DatabaseUsersDelete($id, $name, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "databases/" . $id . "/users/" . $name . $apiextra, $options);
		}

		// Databases in a database cluster.
		public function DatabasesList($id, $numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "databases/" . $id . "/dbs" . $apiextra, "dbs", $numpages, $options);
		}

		public function DatabasesCreate($id, $name, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("POST", "databases/" . $id . "/dbs" . $apiextra, "db", self::MakeJSONOptions(array("name" => $name), $options), 201);
		}

		public function DatabasesGetInfo($id, $name, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "databases/" . $id . "/dbs/" . $name . $apiextra, "db", $options);
		}

		public function DatabasesDelete($id, $name, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "databases/" . $id . "/dbs/" . $name . $apiextra, $options);
		}

		// Databases pools for a database.
		public function DatabasePoolsList($id, $numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "databases/" . $id . "/pools" . $apiextra, "pools", $numpages, $options);
		}

		public function DatabasePoolsCreate($id, $name, $mode, $size, $db, $user, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("POST", "databases/" . $id . "/pools" . $apiextra, "pool", self::MakeJSONOptions(array("name" => $name, "mode" => $mode, "size" => $size, "db" => $db, "user" => $user), $options), 201);
		}

		public function DatabasePoolsGetInfo($id, $name, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "databases/" . $id . "/pools/" . $name . $apiextra, "pool", $options);
		}

		public function DatabasePoolsDelete($id, $name, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "databases/" . $id . "/pools/" . $name . $apiextra, $options);
		}

		// Domains.
		public function DomainsList($numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "domains" . $apiextra, "domains", $numpages, $options);
		}

		public function DomainsCreate($name, $ipaddr = null, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("POST", "domains" . $apiextra, "domain", self::MakeJSONOptions(array("name" => $name, "ip_address" => $ipaddr), $options), 201);
		}

		public function DomainsGetInfo($name, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "domains/" . $name . $apiextra, "domain", $options);
		}

		public function DomainsDelete($name, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "domains/" . $name . $apiextra, $options);
		}

		// Domain records.
		public function DomainRecordsList($domainname, $numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "domains/" . $domainname . "/records" . $apiextra, "domain_records", $numpages, $options);
		}

		public function DomainRecordsCreate($domainname, $type, $name, $data, $ttl = 1800, $typevalues = array(), $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("POST", "domains/" . $domainname . "/records" . $apiextra, "domain_record", self::MakeJSONOptions(array_merge(array("type" => $type, "name" => $name, "data" => $data, "ttl" => $ttl), $typevalues), $options), 201);
		}

		public function DomainRecordsUpdate($domainname, $id, $updatevalues, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("PUT", "domains/" . $domainname . "/records/" . self::MakeValidID($id) . $apiextra, "domain_record", self::MakeJSONOptions($updatevalues, $options));
		}

		public function DomainRecordsGetInfo($domainname, $id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "domains/" . $domainname . "/records/" . self::MakeValidID($id) . $apiextra, "domain_record", $options);
		}

		public function DomainRecordsDelete($domainname, $id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "domains/" . $domainname . "/records/" . self::MakeValidID($id) . $apiextra, $options);
		}

		// Droplets.
		public function DropletsList($numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "droplets" . $apiextra, "droplets", $numpages, $options);
		}

		public function DropletsCreate($name, $region, $size, $image, $optionalvalues = array(), $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("POST", "droplets" . $apiextra, "droplet", self::MakeJSONOptions(array_merge(array("name" => $name, "region" => $region, "size" => $size, "image" => $image), $optionalvalues), $options), 202);
		}

		public function DropletsKernelsList($id, $numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "droplets/" . self::MakeValidID($id) . "/kernels" . $apiextra, "kernels", $numpages, $options);
		}

		public function DropletsSnapshotsList($id, $numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "droplets/" . self::MakeValidID($id) . "/snapshots" . $apiextra, "snapshots", $numpages, $options);
		}

		public function DropletsBackupsList($id, $numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "droplets/" . self::MakeValidID($id) . "/backups" . $apiextra, "backups", $numpages, $options);
		}

		public function DropletsActionsList($id, $numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "droplets/" . self::MakeValidID($id) . "/actions" . $apiextra, "actions", $numpages, $options);
		}

		public function DropletsGetInfo($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "droplets/" . self::MakeValidID($id) . $apiextra, "droplet", $options);
		}

		public function DropletsDelete($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "droplets/" . $id . $apiextra, $options);
		}

		public function DropletsDeleteByTag($tagname, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "droplets?tagname=" . urlencode($tagname) . $apiextra, $options);
		}

		public function DropletsNeighborsList($id, $numpages = true, $apiextra = "", $options = array())
		{
			if ($id === "all")  return $this->RunAPIGetList("GET", "reports/droplet_neighbors" . $apiextra, "neighbors", $numpages, $options);

			return $this->RunAPIGetList("GET", "droplets/" . self::MakeValidID($id) . "/neighbors" . $apiextra, "droplets", $numpages, $options);
		}

		// Droplet actions.
		public function DropletActionsByID($id, $action, $actionvalues = array(), $apiextra = "", $options = array())
		{
			$action = preg_replace('/[^a-z]/', "_", strtolower(trim($action)));

			$result = $this->RunAPIGetOne("POST", "droplets/" . self::MakeValidID($id) . "/actions" . $apiextra, "action", self::MakeJSONOptions(array_merge(array("type" => $action), $actionvalues), $options), 201);
			if (!$result["success"])  return $result;

			$result["actions"][] = $result["action"];
			unset($result["action"]);

			return $result;
		}

		public function DropletActionsByTag($tagname, $action, $actionvalues = array(), $apiextra = "", $options = array())
		{
			$action = preg_replace('/[^a-z]/', "_", strtolower(trim($action)));

			return $this->RunAPIGetOne("POST", "droplets/actions?tagname=" . urlencode($tagname) . $apiextra, "actions", self::MakeJSONOptions(array_merge(array("type" => $action), $actionvalues), $options), 201);
		}

		// Firewalls.
		public function FirewallsList($numpages = true, $apiextra = "", $options = array())
		{
			$result = $this->RunAPIGetList("GET", "firewalls" . $apiextra, "firewalls", $numpages, $options);
			if (!$result["success"])  return $result;

			foreach ($result["data"] as $num => $firewall)
			{
				$firewall["inbound_rules"] = self::NormalizeFirewallRules($firewall["inbound_rules"], "inbound");
				$firewall["outbound_rules"] = self::NormalizeFirewallRules($firewall["outbound_rules"], "outbound");

				$result["data"][$num] = $firewall;
			}

			return $result;
		}

		public static function NormalizeFirewallRules($rules, $type)
		{
			if ($type !== "inbound" && $type !== "outbound")  return false;

			if (!is_array($rules))  $rules = array();

			$result = array();
			foreach ($rules as $rule)
			{
				if (!isset($rule["protocol"]))  continue;

				$protocol = strtolower($rule["protocol"]);
				if ($protocol !== "tcp" && $protocol !== "udp" && $protocol !== "icmp")  continue;
				if (!isset($rule["ports"]) || $rule["ports"] == 0)  $rule["ports"] = "all";
				$ports = (string)$rule["ports"];

				if ($type === "inbound")  $options = (isset($rule["sources"]) && is_array($rule["sources"]) ? $rule["sources"] : array());
				else  $options = (isset($rule["destinations"]) && is_array($rule["destinations"]) ? $rule["destinations"] : array());

				$options2 = array();
				foreach ($options as $type2 => $items)
				{
					if (!is_array($items))  continue;

					$items2 = array();
					if ($type2 === "addresses" || $type2 === "load_balancer_uids" || $type2 === "tags")
					{
						foreach ($items as $item)
						{
							$item = trim($item);
							if ($item != "")  $items2[] = $item;
						}
					}
					else if ($type2 === "droplet_ids")
					{
						foreach ($items as $item)
						{
							$item = (int)$item;
							if ($item > 0)  $items2[] = $item;
						}
					}

					if (count($items2))  $options2[$type2] = $items2;
				}

				if (!count($options2))  continue;

				$rule = array(
					"protocol" => $protocol,
				);

				if ($protocol !== "icmp")  $rule["ports"] = $ports;

				if ($type === "inbound")  $rule["sources"] = $options2;
				else  $rule["destinations"] = $options2;

				$result[] = $rule;
			}

			return $result;
		}

		public function FirewallsCreate($name, $inboundrules, $outboundrules, $dropletids = null, $tagnames = null, $apiextra = "", $options = array())
		{
			if (!is_array($dropletids))  $dropletids = null;
			if (!is_array($tagnames))  $tagnames = null;

			$inboundrules = self::NormalizeFirewallRules($inboundrules, "inbound");
			$outboundrules = self::NormalizeFirewallRules($outboundrules, "outbound");

			return $this->RunAPIGetOne("POST", "firewalls" . $apiextra, "firewall", self::MakeJSONOptions(array("name" => $name, "inbound_rules" => $inboundrules, "outbound_rules" => $outboundrules, "droplet_ids" => $dropletids, "tags" => $tagnames), $options), 202);
		}

		public function FirewallsUpdate($id, $name, $inboundrules, $outboundrules, $dropletids = null, $tagnames = null, $apiextra = "", $options = array())
		{
			if (!is_array($dropletids))  $dropletids = null;
			if (!is_array($tagnames))  $tagnames = null;

			$inboundrules = self::NormalizeFirewallRules($inboundrules, "inbound");
			$outboundrules = self::NormalizeFirewallRules($outboundrules, "outbound");

			return $this->RunAPIGetOne("PUT", "firewalls/" . $id . $apiextra, "firewall", self::MakeJSONOptions(array("name" => $name, "inbound_rules" => $inboundrules, "outbound_rules" => $outboundrules, "droplet_ids" => $dropletids, "tags" => $tagnames), $options));
		}

		public function FirewallsGetInfo($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "firewalls/" . $id . $apiextra, "firewall", $options);
		}

		public function FirewallsDelete($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "firewalls/" . $id . $apiextra, $options);
		}

		// Images.
		public function ImagesList($numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "images" . $apiextra, "images", $numpages, $options);
		}

		public function ImagesCreate($name, $url, $region, $imageopts = array(), $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("POST", "images" . $apiextra, "image", self::MakeJSONOptions(array_merge(array("name" => $name, "url" => $url, "region" => $region), $imageopts), $options), 202);
		}

		public function ImagesGetInfo($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "images/" . $id . $apiextra, "image", $options);
		}

		public function ImagesActionsList($id, $numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "images/" . $id . "/actions" . $apiextra, "actions", $numpages, $options);
		}

		public function ImagesRename($id, $newname, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("PUT", "images/" . $id . $apiextra, "image", self::MakeJSONOptions(array("name" => $newname), $options));
		}

		public function ImagesDelete($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "images/" . $id . $apiextra, $options);
		}

		// Image actions.
		public function ImageActionsByID($id, $action, $actionvalues = array(), $apiextra = "", $options = array())
		{
			$action = preg_replace('/[^a-z]/', "_", strtolower(trim($action)));

			$result = $this->RunAPIGetOne("POST", "images/" . $id . "/actions" . $apiextra, "action", self::MakeJSONOptions(array_merge(array("type" => $action), $actionvalues), $options), 201);
			if (!$result["success"])  return $result;

			$result["actions"][] = $result["action"];
			unset($result["action"]);

			return $result;
		}

		// Load Balancers.
		public function LoadBalancersList($numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "load_balancers" . $apiextra, "load_balancers", $numpages, $options);
		}

		public function LoadBalancersCreate($name, $region, $forwardingrules, $balanceropts = array(), $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("POST", "load_balancers" . $apiextra, "load_balancer", self::MakeJSONOptions(array_merge(array("name" => $name, "region" => $region, "forwarding_rules" => $forwardingrules), $balanceropts), $options), 202);
		}

		// Note:  The region must be the same slug used to create the load balancer.
		public function LoadBalancersUpdate($id, $name, $region, $forwardingrules, $balanceropts = array(), $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("PUT", "load_balancers/" . $id . $apiextra, "load_balancer", self::MakeJSONOptions(array_merge(array("name" => $name, "region" => $region, "forwarding_rules" => $forwardingrules), $balanceropts), $options));
		}

		public function LoadBalancersGetInfo($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "load_balancers/" . $id . $apiextra, "load_balancer", $options);
		}

		public function LoadBalancersDelete($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "load_balancers/" . $id . $apiextra, $options);
		}

		// Projects.
		public function ProjectsList($numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "projects" . $apiextra, "projects", $numpages, $options);
		}

		public function ProjectsCreate($name, $purpose, $projectopts, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("POST", "projects" . $apiextra, "project", self::MakeJSONOptions(array_merge(array("name" => $name, "purpose" => $purpose), $projectopts), $options), 201);
		}

		public function ProjectsUpdate($id, $name, $purpose, $projectopts, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("PUT", "projects/" . $id . $apiextra, "project", self::MakeJSONOptions(array_merge(array("name" => $name, "purpose" => $purpose), $projectopts), $options));
		}

		public function ProjectsGetInfo($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "projects/" . $id . $apiextra, "project", $options);
		}

		// Projects resources.
		public function ProjectResourcesList($id, $numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "projects/" . $id . "/resources" . $apiextra, "resources", $numpages, $options);
		}

		public function ProjectResourcesAssign($id, $resources, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("POST", "projects/" . $id . "/resources" . $apiextra, "resources", self::MakeJSONOptions(array("resources" => $resources), $options));
		}

		// Snapshots.
		public function SnapshotsList($numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "snapshots" . $apiextra, "snapshots", $numpages, $options);
		}

		public function SnapshotsGetInfo($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "snapshots/" . $id . $apiextra, "snapshot", $options);
		}

		public function SnapshotsDelete($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "snapshots/" . $id . $apiextra, $options);
		}

		// SSH keys.
		public function SSHKeysList($numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "account/keys" . $apiextra, "ssh_keys", $numpages, $options);
		}

		public function SSHKeysCreate($name, $publickey, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("POST", "account/keys" . $apiextra, "ssh_key", self::MakeJSONOptions(array("name" => $name, "public_key" => $publickey), $options), 201);
		}

		public function SSHKeysGetInfo($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "account/keys/" . $id . $apiextra, "ssh_key", $options);
		}

		public function SSHKeysRename($id, $newname, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("PUT", "account/keys/" . $id . $apiextra, "ssh_key", self::MakeJSONOptions(array("name" => $newname), $options));
		}

		public function SSHKeysDelete($id, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "account/keys/" . $id . $apiextra, $options);
		}

		// Regions.
		public function RegionsList($numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "regions" . $apiextra, "regions", $numpages, $options);
		}

		// Sizes.
		public function SizesList($numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "sizes" . $apiextra, "sizes", $numpages, $options);
		}

		// Floating IPs.
		public function FloatingIPsList($numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "floating_ips" . $apiextra, "floating_ips", $numpages, $options);
		}

		public function FloatingIPsCreate($target, $targetid, $apiextra = "", $options = array())
		{
			$target = preg_replace('/[^a-z]/', "_", strtolower(trim($target)));
			if ($target === "droplet")  $target = "droplet_id";

			return $this->RunAPIGetOne("POST", "floating_ips" . $apiextra, "floating_ip", self::MakeJSONOptions(array($target => $targetid), $options), 202);
		}

		public function FloatingIPsGetInfo($ipaddr, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "floating_ips/" . $ipaddr . $apiextra, "floating_ip", $options);
		}

		public function FloatingIPsActionsList($ipaddr, $numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "floating_ips/" . $ipaddr . "/actions" . $apiextra, "actions", $numpages, $options);
		}

		public function FloatingIPsDelete($ipaddr, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "floating_ips/" . $ipaddr . $apiextra, $options);
		}

		// Floating IP actions.
		public function FloatingIPActionsByIP($ipaddr, $action, $actionvalues = array(), $apiextra = "", $options = array())
		{
			$action = preg_replace('/[^a-z]/', "_", strtolower(trim($action)));

			$result = $this->RunAPIGetOne("POST", "floating_ips/" . $ipaddr . "/actions" . $apiextra, "action", self::MakeJSONOptions(array_merge(array("type" => $action), $actionvalues), $options), 201);
			if (!$result["success"])  return $result;

			$result["actions"][] = $result["action"];
			unset($result["action"]);

			return $result;
		}

		// Tags.
		public function TagsList($numpages = true, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetList("GET", "tags" . $apiextra, "tags", $numpages, $options);
		}

		public function TagsCreate($tagname, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("POST", "tags" . $apiextra, "tag", self::MakeJSONOptions(array("name" => $tagname), $options), 201);
		}

		public function TagsGetInfo($tagname, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetOne("GET", "tags/" . $tagname . $apiextra, "tag", $options);
		}

		public function TagsAttach($tagname, $resources, $apiextra = "", $options = array())
		{
			foreach ($resources as $num => $item)
			{
				foreach ($item as $key => $val)  $item[$key] = (string)$val;

				$resources[$num] = $item;
			}

			return $this->RunAPIGetNone("POST", "tags/" . $tagname . "/resources" . $apiextra, self::MakeJSONOptions(array("resources" => $resources), $options));
		}

		public function TagsDetach($tagname, $resources, $apiextra = "", $options = array())
		{
			foreach ($resources as $num => $item)
			{
				foreach ($item as $key => $val)  $item[$key] = (string)$val;

				$resources[$num] = $item;
			}

			return $this->RunAPIGetNone("DELETE", "tags/" . $tagname . "/resources" . $apiextra, self::MakeJSONOptions(array("resources" => $resources), $options));
		}

		public function TagsDelete($tagname, $apiextra = "", $options = array())
		{
			return $this->RunAPIGetNone("DELETE", "tags/" . $tagname . $apiextra, $options);
		}

		// Metadata.
		public function MetadataDropletGetInfo($infopath = ".json", $apiextra = "", $options = array())
		{
			return $this->RunMetadataAPI("GET", $infopath, $apiextra, $options);
		}

		// For simple API calls that return a single result.
		public function RunAPIGetOne($method, $apipath, $resultkey, $options = array(), $expected = 200)
		{
			$result = $this->UpdateBearerToken();
			if (!$result["success"])  return $result;

			$result = $this->RunAPI($method, $this->GetAPIEndpoint() . $apipath, false, $options, $expected);
			if (!$result["success"])  return $result;
			if (!isset($result["data"][0][$resultkey]))  return array("success" => false, "error" => self::DO_Translate("The result key '" . $resultkey . "' does not exist in the data returned by Digital Ocean."), "errorcode" => "missing_result_key", "info" => $result);

			$result[$resultkey] = $result["data"][0][$resultkey];
			if ($resultkey !== "data")  unset($result["data"]);

			return $result;
		}

		// For simple API calls that return a standard list.
		public function RunAPIGetList($method, $apipath, $expectedkey, $numpages = 1, $options = array(), $expected = 200)
		{
			$result = $this->UpdateBearerToken();
			if (!$result["success"])  return $result;

			$result = $this->RunAPI($method, $this->GetAPIEndpoint() . $apipath, $expectedkey, $options, $expected, $numpages);
			if (!$result["success"])  return $result;

			return $result;
		}

		// For simple API calls that return nothing (mostly deletes).
		public function RunAPIGetNone($method, $apipath, $options = array(), $expected = 204)
		{
			$result = $this->UpdateBearerToken();
			if (!$result["success"])  return $result;

			$result = $this->RunAPI($method, $this->GetAPIEndpoint() . $apipath, false, $options, $expected);
			if (!$result["success"])  return $result;

			return $result;
		}

		public function GetAPIEndpoint()
		{
			return "https://api.digitalocean.com/v2/";
		}

		public function RunMetadataAPI($method, $apipath, $apiextra = "", $options = array(), $expected = 200)
		{
			return $this->RunAPI("GET", $this->GetMetadataEndpoint() . $apipath . $apiextra, false, $options, $expected);
		}

		public function GetMetadataEndpoint()
		{
			return "http://169.254.169.254/metadata/v1";
		}

		public static function MakeValidID($id)
		{
			return preg_replace('/[^0-9]/', "", $id);
		}

		public static function MakeJSONOptions($jsonoptions, $options)
		{
			unset($options["postvars"]);

			if (!isset($options["headers"]))  $options["headers"] = array();
			$options["headers"]["Content-Type"] = "application/json";

			if (isset($options["body"]))  $options["body"] = array_merge($jsonoptions, $options["body"]);
			else  $options["body"] = json_encode($jsonoptions);

			return $options;
		}

		private static function DO_Translate()
		{
			$args = func_get_args();
			if (!count($args))  return "";

			return call_user_func_array((defined("CS_TRANSLATE_FUNC") && function_exists(CS_TRANSLATE_FUNC) ? CS_TRANSLATE_FUNC : "sprintf"), $args);
		}

		private static function InitSSLOpts($options)
		{
			$result = array_merge(array(
				"ciphers" => "ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-AES128-SHA256:ECDHE-RSA-AES128-SHA256:ECDHE-ECDSA-AES128-SHA:ECDHE-RSA-AES256-SHA384:ECDHE-RSA-AES128-SHA:ECDHE-ECDSA-AES256-SHA384:ECDHE-ECDSA-AES256-SHA:ECDHE-RSA-AES256-SHA:DHE-RSA-AES128-SHA256:DHE-RSA-AES128-SHA:DHE-RSA-AES256-SHA256:DHE-RSA-AES256-SHA:ECDHE-ECDSA-DES-CBC3-SHA:ECDHE-RSA-DES-CBC3-SHA:EDH-RSA-DES-CBC3-SHA:AES128-GCM-SHA256:AES256-GCM-SHA384:AES128-SHA256:AES256-SHA256:AES128-SHA:AES256-SHA:DES-CBC3-SHA:!DSS",
				"disable_compression" => true,
				"allow_self_signed" => false,
				"verify_peer" => true,
				"verify_depth" => 3,
				"capture_peer_cert" => true,
				"cafile" => str_replace("\\", "/", dirname(__FILE__)) . "/digitalocean_ca.pem",
				"auto_cn_match" => true,
				"auto_sni" => true
			), $options);

			return $result;
		}

		private function RunAPI($method, $url, $expectedkey, $options = array(), $expected = 200, $numpages = 1, $decodebody = true)
		{
			$options2 = array(
				"method" => $method,
				"sslopts" => self::InitSSLOpts(array())
			);
			if ($this->debug)  $options2["debug"] = true;

			$options2 = array_merge($options2, $options);

			if ($this->accesstokens["bearertoken"] !== false)
			{
				if (!isset($options2["headers"]))  $options2["headers"] = array();
				$options2["headers"]["Authorization"] = "Bearer " . $this->accesstokens["bearertoken"];
			}

			$result = array(
				"success" => true,
				"requests_left" => 0,
				"data" => array(),
				"actions" => array()
			);

			do
			{
				$found = false;

				$retries = 3;
				do
				{
					$result2 = $this->web->Process($url, $options2);
					if (!$result2["success"])  sleep(1);
					$retries--;
				} while ($retries > 0 && !$result2["success"]);

				if (!$result2["success"])  return $result2;

				if ($this->debug)
				{
					echo "------- RAW SEND START -------\n";
					echo $result2["rawsend"];
					echo "------- RAW SEND END -------\n\n";

					echo "------- RAW RECEIVE START -------\n";
					echo $result2["rawrecv"];
					echo "------- RAW RECEIVE END -------\n\n";
				}

				if ($result2["response"]["code"] != $expected)  return array("success" => false, "error" => self::DO_Translate("Expected a %d response from DigitalOcean.  Received '%s'.", $expected, $result2["response"]["line"]), "errorcode" => "unexpected_digitalocean_response", "info" => $result2);

				if (isset($result2["headers"]["Ratelimit-Remaining"]))  $result["requests_left"] = (int)$result2["headers"]["Ratelimit-Remaining"][0];

				if ($decodebody && trim($result2["body"]) !== "")
				{
					$data = json_decode($result2["body"], true);

					if ($data !== false)
					{
						if ($expectedkey !== false)
						{
							if (!isset($data[$expectedkey]) && array_key_exists($expectedkey, $data))  $data[$expectedkey] = array();

							if (!isset($data[$expectedkey]))  return array("success" => false, "error" => self::DO_Translate("The key '" . $expectedkey . "' does not exist in the data returned by Digital Ocean."), "errorcode" => "missing_expected_key", "info" => $data);

							foreach ($data[$expectedkey] as $item)  $result["data"][] = $item;
						}
						else
						{
							$result["data"][] = $data;
						}
					}

					if (isset($data["links"]) && isset($data["links"]["pages"]) && isset($data["links"]["pages"]["next"]))
					{
						$url = $data["links"]["pages"]["next"];
						if ($numpages > 0)
						{
							$found = true;

							if ($numpages !== true)  $numpages--;
						}
					}

					if (isset($data["links"]) && isset($data["links"]["actions"]))
					{
						foreach ($data["links"]["actions"] as $item)  $result["actions"][] = $item;
					}
				}

			} while ($found && $numpages);

			return $result;
		}
	}
?>