<?php

namespace {

	use pocketmine_utils\Config;
	use pocketmine_utils\Utils;

	if(!defined("STDIN")){
		define("STDIN", fopen("php://stdin", "rt"));
	}
	function enquiry($question, $default = ""){
		echo "[?] " . rtrim($question) . " ";
		$result = trim(fgets(STDIN));
		if($result === ""){
			return $default;
		}
		return $result;
	}

	$file = Phar::running(false);
	$dir = dirname($file);
	if($file === ""){
		die("This script can only be used in phar files");
	}

	$targetConfigFile = $dir . "/Hormones/config.yml";
	$dataFolder = dirname($targetConfigFile);
	if(!is_dir($dataFolder)){
		mkdir($dataFolder, 0777, true);
	}

	$manifestFile = Phar::running() . "/plugin.yml";
	$manifest = yaml_parse_url($manifestFile);
	echo "[*] Thank you for using Hormones by LegendsOfMCPE Team";
	echo "[*] INSTALLER FOR Hormones " . $manifest["version"] . PHP_EOL;

	askDatabaseCredentials:
	$hostname = enquiry("Please enter the hostname of your MySQL database (e.g. 127.0.0.1)");
	$username = enquiry("Please enter the username of your MySQL database (e.g. root)");
	$password = enquiry("Please enter the password of your MySQL database (you can click enter and input the password into $targetConfigFile later)");
	$schema = enquiry("Please enter the schema (a.k.a. database) name to use for Hormones in your MySQL database (click enter for default `hormones`)", "hormones");
	$port = enquiry("Please enter the port of your MySQL database (click enter for default 3306)", 3306);

	echo "[ ] Testing for connection...";
	/** @noinspection PhpUsageOfSilenceOperatorInspection */
	$conn = @new mysqli($hostname, $username, $password, $schema, $port);
	if($conn->connect_error){
		echo "\r[!] Testing for connection... Failed to connect to database: '$conn->connect_error'. Please enter the credentials again.", PHP_EOL;
		goto askDatabaseCredentials;
	}
	echo "\r[X] Testing for connection... Success!", PHP_EOL;

	echo "[ ] Checking for Hormones schema...";
	$schemasResult = $conn->query("SHOW SCHEMAS");
	while(is_array($row = $schemasResult->fetch_assoc())){
		if($row["Database"] === $schema){
			$ok = true;
			break;
		}
	}
	$schemasResult->close();
	if(!isset($ok)){
		echo "\r[!] Checking for Hormones schema... not found, attempting to create...";
		$conn->query("CREATE SCHEMA `$schema`");
		if($conn->error){
			echo " Failed: '$conn->error'. Switching user to root may resolve the issue. Please enter the credentials again.", PHP_EOL;
			goto askDatabaseCredentials;
		}
		echo "\r[X] Checking for Hormones schema... not found, attempting to create... OK.", PHP_EOL;
	}else{
		echo "[X] Checking for Hormones schema... OK.", PHP_EOL;
	}
	$conn->query("USE `$schema`");

	echo "[ ] Initializing Hormones schema...";
	$query = file_get_contents(Phar::running() . "/resources/dbInit.sql");
	$conn->query($query);
	if($conn->error){
		echo "\r[!] Initializing Hormones schema... Failed: '$conn->error'. Aborting.", PHP_EOL;
		die;
	}
	echo "[X] Initializing Hormones schema... OK.", PHP_EOL;

	$type = enquiry("Please enter the type name of this server (must be the same as other servers of this type)");
	$organResult = $conn->query("SELECT flag FROM organs WHERE name='{$conn->escape_string($type)}'");
	$row = $organResult->fetch_assoc();
	$organResult->close();
	if(is_array($row)){
		$organ = (int) $row["flag"];
	}else{
		$conn->query("INSERT INTO organs (name) VALUES ('{$conn->escape_string($type)}')");
		$organ = (int) $conn->insert_id;
	}
	$serverProperties = new Config(dirname(Phar::running(false)) . "/../server.properties", Config::PROPERTIES);
	$serverID = Utils::getMachineUniqueId($serverProperties->get("server-ip", "0.0.0.0") . $serverProperties->get("server-port", 19132));
	$maxPlayers = (int) enquiry("How many players should be allowed to play on this server at the same time? (Special players can bypass this limit)");

	echo "[ ] Exporting config file...";
	yaml_emit_file($targetConfigFile, [
		"mysql" => [
			"hostname" => $hostname,
			"username" => $username,
			"password" => $password,
			"schema" => $schema,
			"port" => $port
		],
		"localize" => [
			"organ" => $organ,
			"maxPlayers" => $maxPlayers
		]
	], YAML_UTF8_ENCODING);
	echo "\r[X] Exporting config file... OK.", PHP_EOL, "[*] Your server ID is $serverID", PHP_EOL, "[X] Task completed.", PHP_EOL;
}

namespace pocketmine_utils {
	/**
	 * Class Config
	 *
	 * Config Class for simple config manipulation of multiple formats.
	 */

	/** @noinspection PhpMultipleClassesDeclarationsInOneFile */
	class Config{
		const DETECT = -1; //Detect by file extension
		const PROPERTIES = 0; // .properties
		const CNF = Config::PROPERTIES; // .cnf
		const JSON = 1; // .js, .json
		const YAML = 2; // .yml, .yaml
		//const EXPORT = 3; // .export, .xport
		const SERIALIZED = 4; // .sl
		const ENUM = 5; // .txt, .list, .enum
		const ENUMERATION = Config::ENUM;
		/** @var array */
		private $config = [];
		private $nestedCache = [];
		/** @var string */
		private $file;
		/** @var boolean */
		private $correct = false;
		/** @var integer */
		private $type = Config::DETECT;
		public static $formats = [
			"properties" => Config::PROPERTIES,
			"cnf" => Config::CNF,
			"conf" => Config::CNF,
			"config" => Config::CNF,
			"json" => Config::JSON,
			"js" => Config::JSON,
			"yml" => Config::YAML,
			"yaml" => Config::YAML,
			//"export" => Config::EXPORT,
			//"xport" => Config::EXPORT,
			"sl" => Config::SERIALIZED,
			"serialize" => Config::SERIALIZED,
			"txt" => Config::ENUM,
			"list" => Config::ENUM,
			"enum" => Config::ENUM,
		];
		/**
		 * @param string $file Path of the file to be loaded
		 * @param int $type Config type to load, -1 by default (detect)
		 * @param array $default Array with the default values, will be set if not existent
		 * @param null &$correct Sets correct to true if everything has been loaded correctly
		 */
		public function __construct($file, $type = Config::DETECT, $default = [], &$correct = null){
			$this->load($file, $type, $default);
			$correct = $this->correct;
		}
		/**
		 * Removes all the changes in memory and loads the file again
		 */
		public function reload(){
			$this->config = [];
			$this->nestedCache = [];
			$this->correct = false;
			$this->load($this->file);
			$this->load($this->file, $this->type);
		}
		/**
		 * @param $str
		 *
		 * @return mixed
		 */
		public static function fixYAMLIndexes($str){
			return preg_replace("#^([ ]*)([a-zA-Z_]{1}[ ]*)\\:$#m", "$1\"$2\":", $str);
		}
		/**
		 * @param       $file
		 * @param int $type
		 * @param array $default
		 *
		 * @return bool
		 */
		public function load($file, $type = Config::DETECT, $default = []){
			$this->correct = true;
			$this->type = (int) $type;
			$this->file = $file;
			if(!is_array($default)){
				$default = [];
			}
			if(!file_exists($file)){
				$this->config = $default;
				$this->save();
			}else{
				if($this->type === Config::DETECT){
					$extension = explode(".", basename($this->file));
					$extension = strtolower(trim(array_pop($extension)));
					if(isset(Config::$formats[$extension])){
						$this->type = Config::$formats[$extension];
					}else{
						$this->correct = false;
					}
				}
				if($this->correct === true){
					$content = file_get_contents($this->file);
					switch($this->type){
						case Config::PROPERTIES:
						case Config::CNF:
							$this->parseProperties($content);
							break;
						case Config::JSON:
							$this->config = json_decode($content, true);
							break;
						case Config::YAML:
							$content = self::fixYAMLIndexes($content);
							$this->config = yaml_parse($content);
							break;
						case Config::SERIALIZED:
							$this->config = unserialize($content);
							break;
						case Config::ENUM:
							$this->parseList($content);
							break;
						default:
							$this->correct = false;
							return false;
					}
					if(!is_array($this->config)){
						$this->config = $default;
					}
					if($this->fillDefaults($default, $this->config) > 0){
						$this->save();
					}
				}else{
					return false;
				}
			}
			return true;
		}
		/**
		 * @return boolean
		 */
		public function check(){
			return $this->correct === true;
		}
		/**
		 * @return boolean
		 */
		public function save(){
			if($this->correct === true){
				try{
					$content = null;
					switch($this->type){
						case Config::PROPERTIES:
						case Config::CNF:
							$content = $this->writeProperties();
							break;
						case Config::JSON:
							$content = json_encode($this->config, JSON_PRETTY_PRINT | JSON_BIGINT_AS_STRING);
							break;
						case Config::YAML:
							$content = yaml_emit($this->config, YAML_UTF8_ENCODING);
							break;
						case Config::SERIALIZED:
							$content = serialize($this->config);
							break;
						case Config::ENUM:
							$content = implode("\r\n", array_keys($this->config));
							break;
					}
					file_put_contents($this->file, $content);
				}catch(\Throwable $e){
				}
				return true;
			}else{
				return false;
			}
		}
		/**
		 * @param $k
		 *
		 * @return boolean|mixed
		 */
		public function __get($k){
			return $this->get($k);
		}
		/**
		 * @param $k
		 * @param $v
		 */
		public function __set($k, $v){
			$this->set($k, $v);
		}
		/**
		 * @param $k
		 *
		 * @return boolean
		 */
		public function __isset($k){
			return $this->exists($k);
		}
		/**
		 * @param $k
		 */
		public function __unset($k){
			$this->remove($k);
		}
		/**
		 * @param $key
		 * @param $value
		 */
		public function setNested($key, $value){
			$vars = explode(".", $key);
			$base = array_shift($vars);
			if(!isset($this->config[$base])){
				$this->config[$base] = [];
			}
			$base =& $this->config[$base];
			while(count($vars) > 0){
				$baseKey = array_shift($vars);
				if(!isset($base[$baseKey])){
					$base[$baseKey] = [];
				}
				$base =& $base[$baseKey];
			}
			$base = $value;
			$this->nestedCache[$key] = $value;
		}
		/**
		 * @param       $key
		 * @param mixed $default
		 *
		 * @return mixed
		 */
		public function getNested($key, $default = null){
			if(isset($this->nestedCache[$key])){
				return $this->nestedCache[$key];
			}
			$vars = explode(".", $key);
			$base = array_shift($vars);
			if(isset($this->config[$base])){
				$base = $this->config[$base];
			}else{
				return $default;
			}
			while(count($vars) > 0){
				$baseKey = array_shift($vars);
				if(is_array($base) and isset($base[$baseKey])){
					$base = $base[$baseKey];
				}else{
					return $default;
				}
			}
			return $this->nestedCache[$key] = $base;
		}
		/**
		 * @param       $k
		 * @param mixed $default
		 *
		 * @return boolean|mixed
		 */
		public function get($k, $default = false){
			return ($this->correct and isset($this->config[$k])) ? $this->config[$k] : $default;
		}
		/**
		 * @param string $k key to be set
		 * @param mixed $v value to set key
		 */
		public function set($k, $v = true){
			$this->config[$k] = $v;
		}
		/**
		 * @param array $v
		 */
		public function setAll($v){
			$this->config = $v;
		}
		/**
		 * @param      $k
		 * @param bool $lowercase If set, searches Config in single-case / lowercase.
		 *
		 * @return boolean
		 */
		public function exists($k, $lowercase = false){
			if($lowercase === true){
				$k = strtolower($k); //Convert requested  key to lower
				$array = array_change_key_case($this->config, CASE_LOWER); //Change all keys in array to lower
				return isset($array[$k]); //Find $k in modified array
			}else{
				return isset($this->config[$k]);
			}
		}
		/**
		 * @param $k
		 */
		public function remove($k){
			unset($this->config[$k]);
		}
		/**
		 * @param bool $keys
		 *
		 * @return array
		 */
		public function getAll($keys = false){
			return ($keys === true ? array_keys($this->config) : $this->config);
		}
		/**
		 * @param array $defaults
		 */
		public function setDefaults(array $defaults){
			$this->fillDefaults($defaults, $this->config);
		}
		/**
		 * @param $default
		 * @param $data
		 *
		 * @return integer
		 */
		private function fillDefaults($default, &$data){
			$changed = 0;
			foreach($default as $k => $v){
				if(is_array($v)){
					if(!isset($data[$k]) or !is_array($data[$k])){
						$data[$k] = [];
					}
					$changed += $this->fillDefaults($v, $data[$k]);
				}elseif(!isset($data[$k])){
					$data[$k] = $v;
					++$changed;
				}
			}
			return $changed;
		}
		/**
		 * @param $content
		 */
		private function parseList($content){
			foreach(explode("\n", trim(str_replace("\r\n", "\n", $content))) as $v){
				$v = trim($v);
				if($v == ""){
					continue;
				}
				$this->config[$v] = true;
			}
		}
		/**
		 * @return string
		 */
		private function writeProperties(){
			$content = "#Properties Config file\r\n#" . date("D M j H:i:s T Y") . "\r\n";
			foreach($this->config as $k => $v){
				if(is_bool($v) === true){
					$v = $v === true ? "on" : "off";
				}elseif(is_array($v)){
					$v = implode(";", $v);
				}
				$content .= $k . "=" . $v . "\r\n";
			}
			return $content;
		}
		/**
		 * @param $content
		 */
		private function parseProperties($content){
			if(preg_match_all('/([a-zA-Z0-9\-_\.]*)=([^\r\n]*)/u', $content, $matches) > 0){ //false or 0 matches
				foreach($matches[1] as $i => $k){
					$v = trim($matches[2][$i]);
					switch(strtolower($v)){
						case "on":
						case "true":
						case "yes":
							$v = true;
							break;
						case "off":
						case "false":
						case "no":
							$v = false;
							break;
					}
					$this->config[$k] = $v;
				}
			}
		}
	}

	/** @noinspection PhpMultipleClassesDeclarationsInOneFile */
	class Utils{
		public static $online = true;
		public static $ip = false;
		public static $os;
		private static $serverUniqueId = null;
		/**
		 * Generates an unique identifier to a callable
		 *
		 * @param callable $variable
		 *
		 * @return string
		 */
		public static function getCallableIdentifier(callable $variable){
			if(is_array($variable)){
				return sha1(strtolower(spl_object_hash($variable[0])) . "::" . strtolower($variable[1]));
			}else{
				return sha1(strtolower($variable));
			}
		}
		/**
		 * Gets this machine / server instance unique ID
		 * Returns a hash, the first 32 characters (or 16 if raw)
		 * will be an identifier that won't change frequently.
		 * The rest of the hash will change depending on other factors.
		 *
		 * @param string $extra optional, additional data to identify the machine
		 *
		 * @return UUID
		 */
		public static function getMachineUniqueId($extra = ""){
			if(self::$serverUniqueId !== null and $extra === ""){
				return self::$serverUniqueId;
			}
			$machine = php_uname("a");
			$machine .= file_exists("/proc/cpuinfo") ? implode(preg_grep("/(model name|Processor|Serial)/", file("/proc/cpuinfo"))) : "";
			$machine .= sys_get_temp_dir();
			$machine .= $extra;
			$os = Utils::getOS();
			if($os === "win"){
				/** @noinspection PhpUsageOfSilenceOperatorInspection */
				@exec("ipconfig /ALL", $mac);
				$mac = implode("\n", $mac);
				if(preg_match_all("#Physical Address[. ]{1,}: ([0-9A-F\\-]{17})#", $mac, $matches)){
					foreach($matches[1] as $i => $v){
						if($v == "00-00-00-00-00-00"){
							unset($matches[1][$i]);
						}
					}
					$machine .= implode(" ", $matches[1]); //Mac Addresses
				}
			}elseif($os === "linux"){
				if(file_exists("/etc/machine-id")){
					$machine .= file_get_contents("/etc/machine-id");
				}else{
					/** @noinspection PhpUsageOfSilenceOperatorInspection */
					@exec("ifconfig", $mac);
					$mac = implode("\n", $mac);
					if(preg_match_all("#HWaddr[ \t]{1,}([0-9a-f:]{17})#", $mac, $matches)){
						foreach($matches[1] as $i => $v){
							if($v == "00:00:00:00:00:00"){
								unset($matches[1][$i]);
							}
						}
						$machine .= implode(" ", $matches[1]); //Mac Addresses
					}
				}
			}elseif($os === "android"){
				/** @noinspection PhpUsageOfSilenceOperatorInspection */
				$machine .= @file_get_contents("/system/build.prop");
			}elseif($os === "mac"){
				$machine .= `system_profiler SPHardwareDataType | grep UUID`;
			}
			$data = $machine . PHP_MAXPATHLEN;
			$data .= PHP_INT_MAX;
			$data .= PHP_INT_SIZE;
			$data .= get_current_user();
			foreach(get_loaded_extensions() as $ext){
				$data .= $ext . ":" . phpversion($ext);
			}
			$uuid = UUID::fromData($machine, $data);
			if($extra === ""){
				self::$serverUniqueId = $uuid;
			}
			return $uuid;
		}
		/**
		 * Gets the External IP using an external service, it is cached
		 *
		 * @param bool $force default false, force IP check even when cached
		 *
		 * @return string
		 */
		public static function getIP($force = false){
			if(Utils::$online === false){
				return false;
			}elseif(Utils::$ip !== false and $force !== true){
				return Utils::$ip;
			}
			$ip = trim(strip_tags(Utils::getURL("http://checkip.dyndns.org/")));
			if(preg_match('#Current IP Address\: ([0-9a-fA-F\:\.]*)#', $ip, $matches) > 0){
				Utils::$ip = $matches[1];
			}else{
				$ip = Utils::getURL("http://www.checkip.org/");
				if(preg_match('#">([0-9a-fA-F\:\.]*)</span>#', $ip, $matches) > 0){
					Utils::$ip = $matches[1];
				}else{
					$ip = Utils::getURL("http://checkmyip.org/");
					if(preg_match('#Your IP address is ([0-9a-fA-F\:\.]*)#', $ip, $matches) > 0){
						Utils::$ip = $matches[1];
					}else{
						$ip = trim(Utils::getURL("http://ifconfig.me/ip"));
						if($ip != ""){
							Utils::$ip = $ip;
						}else{
							return false;
						}
					}
				}
			}
			return Utils::$ip;
		}
		/**
		 * Returns the current Operating System
		 * Windows => win
		 * MacOS => mac
		 * iOS => ios
		 * Android => android
		 * Linux => Linux
		 * BSD => bsd
		 * Other => other
		 *
		 * @param bool $recalculate
		 * @return string
		 */
		public static function getOS($recalculate = false){
			if(self::$os === null or $recalculate){
				$uname = php_uname("s");
				if(stripos($uname, "Darwin") !== false){
					if(strpos(php_uname("m"), "iP") === 0){
						self::$os = "ios";
					}else{
						self::$os = "mac";
					}
				}elseif(stripos($uname, "Win") !== false or $uname === "Msys"){
					self::$os = "win";
				}elseif(stripos($uname, "Linux") !== false){
					/** @noinspection PhpUsageOfSilenceOperatorInspection */
					if(@file_exists("/system/build.prop")){
						self::$os = "android";
					}else{
						self::$os = "linux";
					}
				}elseif(stripos($uname, "BSD") !== false or $uname === "DragonFly"){
					self::$os = "bsd";
				}else{
					self::$os = "other";
				}
			}
			return self::$os;
		}

		public static function getRealMemoryUsage(){
			$stack = 0;
			$heap = 0;
			if(Utils::getOS() === "linux" or Utils::getOS() === "android"){
				$mappings = file("/proc/self/maps");
				foreach($mappings as $line){
					if(preg_match("#([a-z0-9]+)\\-([a-z0-9]+) [rwxp\\-]{4} [a-z0-9]+ [^\\[]*\\[([a-zA-z0-9]+)\\]#", trim($line), $matches) > 0){
						if(strpos($matches[3], "heap") === 0){
							$heap += hexdec($matches[2]) - hexdec($matches[1]);
						}elseif(strpos($matches[3], "stack") === 0){
							$stack += hexdec($matches[2]) - hexdec($matches[1]);
						}
					}
				}
			}
			return [$heap, $stack];
		}
		public static function getMemoryUsage($advanced = false){
			$reserved = memory_get_usage();
			$VmSize = null;
			$VmRSS = null;
			if(Utils::getOS() === "linux" or Utils::getOS() === "android"){
				$status = file_get_contents("/proc/self/status");
				if(preg_match("/VmRSS:[ \t]+([0-9]+) kB/", $status, $matches) > 0){
					$VmRSS = $matches[1] * 1024;
				}
				if(preg_match("/VmSize:[ \t]+([0-9]+) kB/", $status, $matches) > 0){
					$VmSize = $matches[1] * 1024;
				}
			}
			if($VmRSS === null){
				$VmRSS = memory_get_usage();
			}
			if(!$advanced){
				return $VmRSS;
			}
			if($VmSize === null){
				$VmSize = memory_get_usage(true);
			}
			return [$reserved, $VmRSS, $VmSize];
		}
		public static function getCoreCount($recalculate = false){
			static $processors = 0;
			if($processors > 0 and !$recalculate){
				return $processors;
			}else{
				$processors = 0;
			}
			switch(Utils::getOS()){
				case "linux":
				case "android":
					if(file_exists("/proc/cpuinfo")){
						foreach(file("/proc/cpuinfo") as $l){
							if(preg_match('/^processor[ \t]*:[ \t]*[0-9]+$/m', $l) > 0){
								++$processors;
							}
						}
					}else{
						/** @noinspection PhpUsageOfSilenceOperatorInspection */
						if(preg_match("/^([0-9]+)\\-([0-9]+)$/", trim(@file_get_contents("/sys/devices/system/cpu/present")), $matches) > 0){
							$processors = (int) ($matches[2] - $matches[1]);
						}
					}
					break;
				case "bsd":
				case "mac":
					$processors = (int) `sysctl -n hw.ncpu`;
					$processors = (int) `sysctl -n hw.ncpu`;
					break;
				case "win":
					$processors = (int) getenv("NUMBER_OF_PROCESSORS");
					break;
			}
			return $processors;
		}
		/**
		 * Returns a prettified hexdump
		 *
		 * @param string $bin
		 *
		 * @return string
		 */
		public static function hexdump($bin){
			$output = "";
			$bin = str_split($bin, 16);
			foreach($bin as $counter => $line){
				$hex = chunk_split(chunk_split(str_pad(bin2hex($line), 32, " ", STR_PAD_RIGHT), 2, " "), 24, " ");
				$ascii = preg_replace('#([^\x20-\x7E])#', ".", $line);
				$output .= str_pad(dechex($counter << 4), 4, "0", STR_PAD_LEFT) . "  " . $hex . " " . $ascii . PHP_EOL;
			}
			return $output;
		}

		/**
		 * Returns a string that can be printed, replaces non-printable characters
		 *
		 * @param $str
		 *
		 * @return string
		 */
		public static function printable($str){
			if(!is_string($str)){
				return gettype($str);
			}
			return preg_replace('#([^\x20-\x7E])#', '.', $str);
		}
		/**
		 * This function tries to get all the entropy available in PHP, and distills it to get a good RNG.
		 *
		 *
		 * @param int $length default 16, Number of bytes to generate
		 * @param bool $secure default true, Generate secure distilled bytes, slower
		 * @param bool $raw default true, returns a binary string if true, or an hexadecimal one
		 * @param string $startEntropy default null, adds more initial entropy
		 * @param int &$rounds Will be set to the number of rounds taken
		 * @param int &$drop Will be set to the amount of dropped bytes
		 *
		 * @return string
		 */
		public static function getRandomBytes($length = 16, $secure = true, $raw = true, $startEntropy = "", &$rounds = 0, &$drop = 0){
			static $lastRandom = "";
			$output = "";
			$length = abs((int) $length);
			$secureValue = "";
			$rounds = 0;
			$drop = 0;
			while(!isset($output{$length - 1})){
				//some entropy, but works ^^
				$weakEntropy = [
					is_array($startEntropy) ? implode($startEntropy) : $startEntropy,
					__DIR__,
					PHP_OS,
					microtime(),
					(string) lcg_value(),
					(string) PHP_MAXPATHLEN,
					PHP_SAPI,
					(string) PHP_INT_MAX . "." . PHP_INT_SIZE,
					serialize($_SERVER),
					get_current_user(),
					(string) memory_get_usage() . "." . memory_get_peak_usage(),
					php_uname(),
					phpversion(),
					zend_version(),
					(string) getmypid(),
					(string) getmyuid(),
					(string) mt_rand(),
					(string) getmyinode(),
					(string) getmygid(),
					(string) rand(),
					function_exists("zend_thread_id") ? ((string) zend_thread_id()) : microtime(),
					function_exists("getrusage") ? implode(getrusage()) : microtime(),
					function_exists("sys_getloadavg") ? implode(sys_getloadavg()) : microtime(),
					serialize(get_loaded_extensions()),
					sys_get_temp_dir(),
					(string) disk_free_space("."),
					(string) disk_total_space("."),
					uniqid(microtime(), true),
					file_exists("/proc/cpuinfo") ? file_get_contents("/proc/cpuinfo") : microtime(),
				];
				shuffle($weakEntropy);
				$value = hash("sha512", implode($weakEntropy), true);
				$lastRandom .= $value;
				foreach($weakEntropy as $k => $c){ //mixing entropy values with XOR and hash randomness extractor
					$value ^= hash("sha256", $c . microtime() . $k, true) . hash("sha256", mt_rand() . microtime() . $k . $c, true);
					$value ^= hash("sha512", ((string) lcg_value()) . $c . microtime() . $k, true);
				}
				unset($weakEntropy);
				if($secure === true){
					if(file_exists("/dev/urandom")){
						$fp = fopen("/dev/urandom", "rb");
						$systemRandom = fread($fp, 64);
						fclose($fp);
					}else{
						$systemRandom = str_repeat("\x00", 64);
					}
					$strongEntropyValues = [
						is_array($startEntropy) ? hash("sha512", $startEntropy[($rounds + $drop) % count($startEntropy)], true) : hash("sha512", $startEntropy, true), //Get a random index of the startEntropy, or just read it
						$systemRandom,
						function_exists("openssl_random_pseudo_bytes") ? openssl_random_pseudo_bytes(64) : str_repeat("\x00", 64),
						function_exists("mcrypt_create_iv") ? mcrypt_create_iv(64, MCRYPT_DEV_URANDOM) : str_repeat("\x00", 64),
						$value,
					];
					$strongEntropy = array_pop($strongEntropyValues);
					foreach($strongEntropyValues as $value){
						$strongEntropy = $strongEntropy ^ $value;
					}
					$value = "";
					//Von Neumann randomness extractor, increases entropy
					$bitcnt = 0;
					for($j = 0; $j < 64; ++$j){
						$a = ord($strongEntropy{$j});
						for($i = 0; $i < 8; $i += 2){
							$b = ($a & (1 << $i)) > 0 ? 1 : 0;
							if($b != (($a & (1 << ($i + 1))) > 0 ? 1 : 0)){
								$secureValue |= $b << $bitcnt;
								if($bitcnt == 7){
									$value .= chr($secureValue);
									$secureValue = 0;
									$bitcnt = 0;
								}else{
									++$bitcnt;
								}
								++$drop;
							}else{
								$drop += 2;
							}
						}
					}
				}
				$output .= substr($value, 0, min($length - strlen($output), $length));
				unset($value);
				++$rounds;
			}
			$lastRandom = hash("sha512", $lastRandom, true);
			return $raw === false ? bin2hex($output) : $output;
		}
		/*
		public static function angle3D($pos1, $pos2){
			$X = $pos1["x"] - $pos2["x"];
			$Z = $pos1["z"] - $pos2["z"];
			$dXZ = sqrt(pow($X, 2) + pow($Z, 2));
			$Y = $pos1["y"] - $pos2["y"];
			$hAngle = rad2deg(atan2($Z, $X) - M_PI_2);
			$vAngle = rad2deg(-atan2($Y, $dXZ));
			return array("yaw" => $hAngle, "pitch" => $vAngle);
		}*/
		/**
		 * GETs an URL using cURL
		 *
		 * @param     $page
		 * @param int $timeout default 10
		 * @param array $extraHeaders
		 *
		 * @return bool|mixed
		 */
		public static function getURL($page, $timeout = 10, array $extraHeaders = []){
			if(Utils::$online === false){
				return false;
			}
			$ch = curl_init($page);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(["User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:12.0) Gecko/20100101 Firefox/12.0 PocketMine-MP"], $extraHeaders));
			curl_setopt($ch, CURLOPT_AUTOREFERER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
			curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
			curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int) $timeout);
			curl_setopt($ch, CURLOPT_TIMEOUT, (int) $timeout);
			$ret = curl_exec($ch);
			curl_close($ch);
			return $ret;
		}
		/**
		 * POSTs data to an URL
		 *
		 * @param              $page
		 * @param array|string $args
		 * @param int $timeout
		 * @param array $extraHeaders
		 *
		 * @return bool|mixed
		 */
		public static function postURL($page, $args, $timeout = 10, array $extraHeaders = []){
			if(Utils::$online === false){
				return false;
			}
			$ch = curl_init($page);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
			curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
			curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
			curl_setopt($ch, CURLOPT_AUTOREFERER, true);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(["User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:12.0) Gecko/20100101 Firefox/12.0 PocketMine-MP"], $extraHeaders));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int) $timeout);
			curl_setopt($ch, CURLOPT_TIMEOUT, (int) $timeout);
			$ret = curl_exec($ch);
			curl_close($ch);
			return $ret;
		}
	}

	/** @noinspection PhpMultipleClassesDeclarationsInOneFile */
	class UUID{
		private $parts = [0, 0, 0, 0];
		private $version = null;
		public function __construct($part1 = 0, $part2 = 0, $part3 = 0, $part4 = 0, $version = null){
			$this->parts[0] = (int) $part1;
			$this->parts[1] = (int) $part2;
			$this->parts[2] = (int) $part3;
			$this->parts[3] = (int) $part4;
			$this->version = $version === null ? ($this->parts[1] & 0xf000) >> 12 : (int) $version;
		}
		public function getVersion(){
			return $this->version;
		}
		public function equals(UUID $uuid){
			return $uuid->parts[0] === $this->parts[0] and $uuid->parts[1] === $this->parts[1] and $uuid->parts[2] === $this->parts[2] and $uuid->parts[3] === $this->parts[3];
		}
		/**
		 * Creates an UUID from an hexadecimal representation
		 *
		 * @param string $uuid
		 * @param int $version
		 * @return UUID
		 */
		public static function fromString($uuid, $version = null){
			return self::fromBinary(hex2bin(str_replace("-", "", trim($uuid))), $version);
		}
		/**
		 * Creates an UUID from a binary representation
		 *
		 * @param string $uuid
		 * @param int $version
		 * @return UUID
		 */
		public static function fromBinary($uuid, $version = null){
			if(strlen($uuid) !== 16){
				throw new \InvalidArgumentException("Must have exactly 16 bytes");
			}
			return new UUID(Binary::readInt(substr($uuid, 0, 4)), Binary::readInt(substr($uuid, 4, 4)), Binary::readInt(substr($uuid, 8, 4)), Binary::readInt(substr($uuid, 12, 4)), $version);
		}
		/**
		 * Creates an UUIDv3 from binary data or list of binary data
		 *
		 * @param string ...$data
		 * @return UUID
		 */
		public static function fromData(...$data){
			$hash = hash("md5", implode($data), true);
			return self::fromBinary($hash, 3);
		}
		public static function fromRandom(){
			return self::fromData(Binary::writeInt(time()), Binary::writeShort(getmypid()), Binary::writeShort(getmyuid()), Binary::writeInt(mt_rand(-0x7fffffff, 0x7fffffff)), Binary::writeInt(mt_rand(-0x7fffffff, 0x7fffffff)));
		}
		public function toBinary(){
			return Binary::writeInt($this->parts[0]) . Binary::writeInt($this->parts[1]) . Binary::writeInt($this->parts[2]) . Binary::writeInt($this->parts[3]);
		}
		public function toString(){
			$hex = bin2hex(self::toBinary());
			//xxxxxxxx-xxxx-Mxxx-Nxxx-xxxxxxxxxxxx 8-4-4-12
			if($this->version !== null){
				return substr($hex, 0, 8) . "-" . substr($hex, 8, 4) . "-" . hexdec($this->version) . substr($hex, 13, 3) . "-8" . substr($hex, 17, 3) . "-" . substr($hex, 20, 12);
			}
			return substr($hex, 0, 8) . "-" . substr($hex, 8, 4) . "-" . substr($hex, 12, 4) . "-" . substr($hex, 16, 4) . "-" . substr($hex, 20, 12);
		}
		public function __toString(){
			return $this->toString();
		}
	}

	/** @noinspection PhpMultipleClassesDeclarationsInOneFile */
	class Binary{
		const BIG_ENDIAN = 0x00;
		const LITTLE_ENDIAN = 0x01;

		/**
		 * Reads a 3-byte big-endian number
		 *
		 * @param $str
		 *
		 * @return mixed
		 */
		public static function readTriad($str){
			return unpack("N", "\x00" . $str)[1];
		}
		/**
		 * Writes a 3-byte big-endian number
		 *
		 * @param $value
		 *
		 * @return string
		 */
		public static function writeTriad($value){
			return substr(pack("N", $value), 1);
		}
		/**
		 * Reads a 3-byte little-endian number
		 *
		 * @param $str
		 *
		 * @return mixed
		 */
		public static function readLTriad($str){
			return unpack("V", $str . "\x00")[1];
		}
		/**
		 * Writes a 3-byte little-endian number
		 *
		 * @param $value
		 *
		 * @return string
		 */
		public static function writeLTriad($value){
			return substr(pack("V", $value), 0, -1);
		}
//		/**
//		 * Writes a coded metadata string
//		 *
//		 * @param array $data
//		 *
//		 * @return string
//		 */
//		public static function writeMetadata(array $data){
//			$m = "";
//			foreach($data as $bottom => $d){
//				$m .= chr(($d[0] << 5) | ($bottom & 0x1F));
//				switch($d[0]){
//					case Entity::DATA_TYPE_BYTE:
//						$m .= self::writeByte($d[1]);
//						break;
//					case Entity::DATA_TYPE_SHORT:
//						$m .= self::writeLShort($d[1]);
//						break;
//					case Entity::DATA_TYPE_INT:
//						$m .= self::writeLInt($d[1]);
//						break;
//					case Entity::DATA_TYPE_FLOAT:
//						$m .= self::writeLFloat($d[1]);
//						break;
//					case Entity::DATA_TYPE_STRING:
//						$m .= self::writeLShort(strlen($d[1])) . $d[1];
//						break;
//					case Entity::DATA_TYPE_SLOT:
//						$m .= self::writeLShort($d[1][0]);
//						$m .= self::writeByte($d[1][1]);
//						$m .= self::writeLShort($d[1][2]);
//						break;
//					case Entity::DATA_TYPE_POS:
//						$m .= self::writeLInt($d[1][0]);
//						$m .= self::writeLInt($d[1][1]);
//						$m .= self::writeLInt($d[1][2]);
//						break;
//					case Entity::DATA_TYPE_LONG:
//						$m .= self::writeLLong($d[1]);
//						break;
//				}
//			}
//			$m .= "\x7f";
//
//			return $m;
//		}
//		/**
//		 * Reads a metadata coded string
//		 *
//		 * @param      $value
//		 * @param bool $types
//		 *
//		 * @return array
//		 */
//		public static function readMetadata($value, $types = false){
//			$offset = 0;
//			$m = [];
//			$b = ord($value{$offset});
//			++$offset;
//			while($b !== 127 and isset($value{$offset})){
//				$bottom = $b & 0x1F;
//				$type = $b >> 5;
//				switch($type){
//					case Entity::DATA_TYPE_BYTE:
//						$r = self::readByte($value{$offset});
//						++$offset;
//						break;
//					case Entity::DATA_TYPE_SHORT:
//						$r = self::readLShort(substr($value, $offset, 2));
//						$offset += 2;
//						break;
//					case Entity::DATA_TYPE_INT:
//						$r = self::readLInt(substr($value, $offset, 4));
//						$offset += 4;
//						break;
//					case Entity::DATA_TYPE_FLOAT:
//						$r = self::readLFloat(substr($value, $offset, 4));
//						$offset += 4;
//						break;
//					case Entity::DATA_TYPE_STRING:
//						$len = self::readLShort(substr($value, $offset, 2));
//						$offset += 2;
//						$r = substr($value, $offset, $len);
//						$offset += $len;
//						break;
//					case Entity::DATA_TYPE_SLOT:
//						$r = [];
//						$r[] = self::readLShort(substr($value, $offset, 2));
//						$offset += 2;
//						$r[] = ord($value{$offset});
//						++$offset;
//						$r[] = self::readLShort(substr($value, $offset, 2));
//						$offset += 2;
//						break;
//					case Entity::DATA_TYPE_POS:
//						$r = [];
//						for($i = 0; $i < 3; ++$i){
//							$r[] = self::readLInt(substr($value, $offset, 4));
//							$offset += 4;
//						}
//						break;
//					case Entity::DATA_TYPE_LONG:
//						$r = self::readLLong(substr($value, $offset, 4));
//						$offset += 8;
//						break;
//					default:
//						return [];
//				}
//				if($types === true){
//					$m[$bottom] = [$r, $type];
//				}else{
//					$m[$bottom] = $r;
//				}
//				$b = ord($value{$offset});
//				++$offset;
//			}
//
//			return $m;
//		}
		/**
		 * Reads a byte boolean
		 *
		 * @param $b
		 *
		 * @return bool
		 */
		public static function readBool($b){
			return self::readByte($b, false) === 0 ? false : true;
		}
		/**
		 * Writes a byte boolean
		 *
		 * @param $b
		 *
		 * @return bool|string
		 */
		public static function writeBool($b){
			return self::writeByte($b === true ? 1 : 0);
		}
		/**
		 * Reads an unsigned/signed byte
		 *
		 * @param string $c
		 * @param bool $signed
		 *
		 * @return int
		 */
		public static function readByte($c, $signed = true){
			$b = ord($c{0});
			if($signed){
				if(PHP_INT_SIZE === 8){
					return $b << 56 >> 56;
				}else{
					return $b << 24 >> 24;
				}
			}else{
				return $b;
			}
		}
		/**
		 * Writes an unsigned/signed byte
		 *
		 * @param $c
		 *
		 * @return string
		 */
		public static function writeByte($c){
			return chr($c);
		}
		/**
		 * Reads a 16-bit unsigned big-endian number
		 *
		 * @param $str
		 *
		 * @return int
		 */
		public static function readShort($str){
			return unpack("n", $str)[1];
		}
		/**
		 * Reads a 16-bit signed big-endian number
		 *
		 * @param $str
		 *
		 * @return int
		 */
		public static function readSignedShort($str){
			if(PHP_INT_SIZE === 8){
				return unpack("n", $str)[1] << 48 >> 48;
			}else{
				return unpack("n", $str)[1] << 16 >> 16;
			}
		}
		/**
		 * Writes a 16-bit signed/unsigned big-endian number
		 *
		 * @param $value
		 *
		 * @return string
		 */
		public static function writeShort($value){
			return pack("n", $value);
		}
		/**
		 * Reads a 16-bit unsigned little-endian number
		 *
		 * @param      $str
		 *
		 * @return int
		 */
		public static function readLShort($str){
			return unpack("v", $str)[1];
		}
		/**
		 * Reads a 16-bit signed little-endian number
		 *
		 * @param      $str
		 *
		 * @return int
		 */
		public static function readSignedLShort($str){
			if(PHP_INT_SIZE === 8){
				return unpack("v", $str)[1] << 48 >> 48;
			}else{
				return unpack("v", $str)[1] << 16 >> 16;
			}
		}
		/**
		 * Writes a 16-bit signed/unsigned little-endian number
		 *
		 * @param $value
		 *
		 * @return string
		 */
		public static function writeLShort($value){
			return pack("v", $value);
		}
		public static function readInt($str){
			if(PHP_INT_SIZE === 8){
				return unpack("N", $str)[1] << 32 >> 32;
			}else{
				return unpack("N", $str)[1];
			}
		}
		public static function writeInt($value){
			return pack("N", $value);
		}
		public static function readLInt($str){
			if(PHP_INT_SIZE === 8){
				return unpack("V", $str)[1] << 32 >> 32;
			}else{
				return unpack("V", $str)[1];
			}
		}
		public static function writeLInt($value){
			return pack("V", $value);
		}
		public static function readFloat($str){
			return ENDIANNESS === self::BIG_ENDIAN ? unpack("f", $str)[1] : unpack("f", strrev($str))[1];
		}
		public static function writeFloat($value){
			return ENDIANNESS === self::BIG_ENDIAN ? pack("f", $value) : strrev(pack("f", $value));
		}
		public static function readLFloat($str){
			return ENDIANNESS === self::BIG_ENDIAN ? unpack("f", strrev($str))[1] : unpack("f", $str)[1];
		}
		public static function writeLFloat($value){
			return ENDIANNESS === self::BIG_ENDIAN ? strrev(pack("f", $value)) : pack("f", $value);
		}
		public static function printFloat($value){
			return preg_replace("/(\\.\\d+?)0+$/", "$1", sprintf("%F", $value));
		}
		public static function readDouble($str){
			return ENDIANNESS === self::BIG_ENDIAN ? unpack("d", $str)[1] : unpack("d", strrev($str))[1];
		}
		public static function writeDouble($value){
			return ENDIANNESS === self::BIG_ENDIAN ? pack("d", $value) : strrev(pack("d", $value));
		}
		public static function readLDouble($str){
			return ENDIANNESS === self::BIG_ENDIAN ? unpack("d", strrev($str))[1] : unpack("d", $str)[1];
		}
		public static function writeLDouble($value){
			return ENDIANNESS === self::BIG_ENDIAN ? strrev(pack("d", $value)) : pack("d", $value);
		}
		public static function readLong($x){
			if(PHP_INT_SIZE === 8){
				$int = unpack("N*", $x);
				return ($int[1] << 32) | $int[2];
			}else{
				$value = "0";
				for($i = 0; $i < 8; $i += 2){
					$value = bcmul($value, "65536", 0);
					$value = bcadd($value, self::readShort(substr($x, $i, 2)), 0);
				}
				if(bccomp($value, "9223372036854775807") == 1){
					$value = bcadd($value, "-18446744073709551616");
				}
				return $value;
			}
		}
		public static function writeLong($value){
			if(PHP_INT_SIZE === 8){
				return pack("NN", $value >> 32, $value & 0xFFFFFFFF);
			}else{
				$x = "";
				if(bccomp($value, "0") == -1){
					$value = bcadd($value, "18446744073709551616");
				}
				$x .= self::writeShort(bcmod(bcdiv($value, "281474976710656"), "65536"));
				$x .= self::writeShort(bcmod(bcdiv($value, "4294967296"), "65536"));
				$x .= self::writeShort(bcmod(bcdiv($value, "65536"), "65536"));
				$x .= self::writeShort(bcmod($value, "65536"));
				return $x;
			}
		}
		public static function readLLong($str){
			return self::readLong(strrev($str));
		}
		public static function writeLLong($value){
			return strrev(self::writeLong($value));
		}
	}
}
