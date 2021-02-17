<?php

class db {

	private static $db = null;
	private static $query = '';
	private static $result = null;
	private static $error = null;
	public static $affected = null;

	public static function connect () {
		if(!self::$db) {
			$ini_data		= file_get_contents('php.ini');
			$settings		= parse_ini_string($ini_data);
			$database_host	= $settings['database_host'];
			$database_user	= $settings['database_user'];
			$database_pass	= $settings['database_pass'];
			$database_db	= $settings['database_db'];
			$port			= $settings['database_port'];

			self::$db		= new mysqli($database_host,$database_user,$database_pass,$database_db);

			if(self::$db->connect_errno > 0){
				exit('Database is unreachable [' . self::$db->connect_error . ']');
			}
			if(!self::$db) {
				exit('Database is not available');
			} else {
				self::$db->set_charset("utf8");
			}
		}
	}

	public static function escape($string='') {
		self::connect();
		if(!is_string($string)) {
			$string =(string)$string;
		}

		return self::$db->real_escape_string($string);
	}

	/**
	 * @param string $query
	 *
	 * @return mysqli_result|bool
	 */
	public static function query(string $query = '') {

		$result		= null;
		$signature	= '';

		if('' != trim($query)) {
			self::reset();
			self::connect();
			self::$query = $query;
			if (!self::$result = self::$db->query($query)){
				self::$error = self::$db->error;
				$bt = debug_backtrace();
				$caller = array_shift($bt);
				exit('There was an error running the query [' . self::$db->error . ']<br />File: '. $caller['file'].'<br/>Line: '. $caller['line'].'<br/>'.$query.'<br /><pre>'.print_r($bt, true).'</pre>');
			} else {
				self::$affected = self::$db->affected_rows;
			}

			$result = self::$result;
		}

		return $result;
	}

	/**
	 * [reset description]
	 * @return [type] [description]
	 */
	protected static function reset () {
		if(is_object(self::$result)) {
			self::$result->free();
		}
		self::$result = null;
		self::$error = false;
		self::$affected = null;
	}

	/**
	 * [close description]
	 * @return [type] [description]
	 */
	public static function close() {
		if(self::$db) {
			self::$db->close();
		}
	}

	/**
	 * [set_charset description]
	 * @param string $charset [description]
	 */
	public static function set_charset (string $charset = 'utf8') {
		if(in_array($charset, ['utf8','utf8mb4'])) {
			self::connect();
			self::$db->set_charset($charset);
			self::query("SET NAMES '$charset'");
		}
	}

	/**
	 * [insert description]
	 * @param  [type] $query [description]
	 * @return [type]        [description]
	 */
	public static function insert(string $query = '') {
		$result = self::query($query);
		if(self::$result !== null) {
			return self::$db->insert_id;
		} else {
			return null;
		}
	}

	// replace with a real UUID function instead of just random data
	public static function uuid() {

		$val = '';
		$length = 32;

		if (function_exists('random_bytes')) {
			$val = bin2hex(random_bytes($length));
		} else if (function_exists('mcrypt_create_iv')) {
			$val = bin2hex(mcrypt_create_iv($length, MCRYPT_DEV_URANDOM));
		} else if (function_exists('openssl_random_pseudo_bytes')) {
			$val = bin2hex(openssl_random_pseudo_bytes($length));
		}

		return crypt('512', $val);

	}
}

class message {

	/**
	 * Get a list of Messages matching the conditions
	 * @param  array          $conditions Array of conditions for dates and search terms
	 * @param  string|integer $limit      The number of records to return
	 * @return array                      An array of records or an empty array
	 */
	public static function get(array $conditions = [], int $limit = 100) : array {

		$response = [];

		$query = "SELECT `public_uuid`, `date`, `body` FROM `messages` ";

		$conditions[] = '`deleted`=0';

		$query.= 'WHERE '.implode(' AND ', $conditions);

		$query.= " LIMIT ".(int)$limit;

		$records = db::query($query);

		if(0 < $records->num_rows) {
			while($row = $records->fetch_assoc()) {
				$response[$row['public_uuid']] = ['date'=>$row['date'], 'body'=>$row['body']];
			}
		}

		return $response;
	}

	/**
	 * Store a message in the database
	 * More can be done to handle errors here, but excluded for speed
	 * @param  string $body Untrustworthy string from the user
	 * @return array        Array of the public and private uuids for the message, or an empty array if it fails to store
	 */
	public static function store(string $body = '') : array {

		$response = [
			'private_uuid'	=> '',
			'public_uuid'	=> '',
		];

		$private_uuid	= db::uuid();
		$public_uuid	= db::uuid();

		$query = "INSERT INTO `messages` (`public_uuid`, `private_uuid`, `date`, `body`) VALUES ('".$public_uuid."', '".$private_uuid."', '".date('Y-m-d H:i:s')."', '".db::escape($body)."');";
		$insert = db::insert($query);

		if($insert) {
			$response['private_uuid']	= $private_uuid;
			$response['public_uuid']	= $public_uuid;
		}

		return $response;
	}

	/**
	 * Update the identified message
	 * @param  string $private_uuid The Private UUID Created when the message was created
	 * @param  string $public_uuid  The Public UUID Create when the message was created
	 * @param  string $body         The new content to be stored
	 * @return array                Array indicating success or failure
	 */
	public static function update(string $private_uuid = '', string $public_uuid = '', string $body = '') : array {

		$response = [
			'success' => false
		];

		$private_uuid	= db::escape($private_uuid);
		$public_uuid	= db::escape($public_uuid);
		$body			= db::escape($body);

		$query = "UPDATE `messages` SET `body`='".db::escape($body)."' WHERE `public_uuid`='".db::escape($public_uuid)."' AND `private_uuid`='".db::escape($private_uuid)."' LIMIT 1;";
		$result = db::query($query);

		// validation could be done here to make sure it's updated before returning success
		$response['success'] = true;

		return $response;
	}

	/**
	 * Update the identified message - same as above - but mark as deleted
	 * @param  string $private_uuid The Private UUID Created when the message was created
	 * @param  string $public_uuid  The Public UUID Create when the message was created
	 * @param  string $body         The new content to be stored
	 * @return array                Array indicating success or failure
	 */
	public static function remove(string $private_uuid = '', string $public_uuid = '') : array {

		$response = [
			'success' => false
		];

		$private_uuid	= db::escape($private_uuid);
		$public_uuid	= db::escape($public_uuid);

		$query = "UPDATE `messages` SET `deleted`=1 WHERE `public_uuid`='".db::escape($public_uuid)."' AND `private_uuid`='".db::escape($private_uuid)."' LIMIT 1;";
		$result = db::query($query);

		// Validation could be done here to make sure it's updated before returning success
		$response['success'] = true;

		return $response;
	}

}

class api {

	private static $logger = null;

	private static $headers = null;

	private static $header_whitelist = [
		'MESSAGES-MATCHING',
		'MESSAGES-FROM',
		'MESSAGES-UNTIL',
		'MESSAGES-LIMIT',
		'MESSAGE-UUID-PRIVATE',
		'MESSAGE-UUID-PUBLIC'
	];

	private static $method	= null;

	private static $allowed_methods = [
		'GET', 'PUT', 'POST', 'DELETE'
	];

	private static $error	= '';

	private static $errors = [
		'STUB-MISSING'		=> [
			'HTTP_CODE'		=> 403,
			'HTTP_MESSAGE'	=> 'You got me. I have not yet coded this function. Try again later!'
		],
		'NO-HEADERS'		=> [
			'HTTP_CODE'		=> 403,
			'HTTP_MESSAGE'	=> 'No User Agent Headers Detected. Please include User Agent headers in your requests. See https://github.com/mystream/ctech for more information.'
		],
		'INVALID-METHOD'	=> [
			'HTTP_CODE'		=> 403,
			'HTTP_MESSAGE'	=> 'An Invalid Request Method was Detected. Please use only GET, PUT, POST or Delete. See https://github.com/mystream/ctech for more information.'
		]
	];

	private static function report() : void {
		if(self::$logger) {
			try{
				$logger->log((object)[
					'error'		=> self::$error,
					'headers'	=> self::$headers,
					'method'	=> self::$method
				]);
			} catch(Exception $e) {

			}
		}
	}


	/**
	 * Initialise the Request Handler
	 */
	public static function initialise(stdClass $logger = null) : void {
		/**
		 * Get all the Headers supplied by the User Agent, if any.
		 */
		self::$headers	= getallheaders();
		if(isset($_SERVER['REDIRECT_REDIRECT_REQUEST_METHOD'])) {
			self::$method	= trim(strtoupper($_SERVER['REDIRECT_REDIRECT_REQUEST_METHOD']));
		} else {
			self::$method	= trim(strtoupper($_SERVER['REQUEST_METHOD']));
		}

		/**
		 * Run basic security checks
		 */
		self::security();

		/**
		 * Hand off the request handler to a GET, PUT, POST or DELETE Handler
		 */
		$operation = 'do_'.strtolower(self::$method);

		if(method_exists('api', $operation)) {
			self::$operation();
		} else {
			self::quit('STUB-MISSING');
		}
	}

	/**
	 * Get matching messages
	 */
	private static function do_get() : void {
		$matching	= (array_key_exists('MESSAGES-MATCHING', self::$headers) && '' != trim(self::$headers['MESSAGES-MATCHING'])) ? trim(self::$headers['MESSAGES-MATCHING']) : '';
		$from		= (array_key_exists('MESSAGES-FROM', self::$headers) && '' != trim(self::$headers['MESSAGES-FROM'])) ? trim(self::$headers['MESSAGES-FROM']) : '';
		$until		= (array_key_exists('MESSAGES-UNTIL', self::$headers) && '' != trim(self::$headers['MESSAGES-UNTIL'])) ? trim(self::$headers['MESSAGES-UNTIL']) : '';
		$limit		= (array_key_exists('MESSAGES-LIMIT', self::$headers) && '' != trim(self::$headers['MESSAGES-LIMIT'])) ? (int)trim(self::$headers['MESSAGES-LIMIT']) : 0;

		$from_checked	= '0000-00-00';
		$until_checked	= date('Y-m-d',strtotime('+1 day'));
		$limits			= 100;

		$conditions	= [];

		// If a Search term was provided, escape it and include it in the query
		// This could be done with some query abstraction layer, depending on what's available
		if('' != $matching) {
			$conditions[] = "`body` LIKE '%".db::escape($matching)."%' ";
		}

		// Make sure the 'From' date is well formatted
		if('' != $from) {
			if(preg_match('/^\d\d\d\d-\d\d-\d\d/$', $from)) {
				$parts = explode('-', $from);
				if(checkdate($parts[1], $parts[2], $parts[0])){
					$from_checked	= preg_replace('/^\d/','',$parts[0]).'-'.preg_replace('/^\d/','',$parts[1]).'-'.preg_replace('/^\d/','',$parts[2]);
					$conditions[] = "`date` >= '".$from_checked."'";
				}
			}
		}

		// Make sure the 'Until' date is well formatted
		if('' != $until) {
			if(preg_match('/^\d\d\d\d-\d\d-\d\d/$', $until)) {
				$parts = explode('-', $until);
				if(checkdate($parts[1], $parts[2], $parts[0])){
					$until_checked	= preg_replace('/^\d/','',$parts[0]).'-'.preg_replace('/^\d/','',$parts[1]).'-'.preg_replace('/^\d/','',$parts[2]);

					if($until_checked <= $from_checked) {
						$until_checked = date('Y-m-d',strtotime('+1 day'));
						$conditions[] = "`date` <= '".$until_checked."'";
					}
				}
			}
		}

		// Make sure the Limit is a positive integer
		if(0 < $limit) {
			$limits = (int)$limit;
		}

		// Make this a query class that caches results
		$messages	= message::get($conditions, $limits);

		$payload	= json_encode($messages);

		self::reply($payload);
	}

	/**
	 * Capture and store a message from the user
	 */
	private static function do_put() : void {
		$body =	file_get_contents("php://input");

		$message = message::store($body);

		self::reply($message);
	}

	/**
	 * Update the identified Message
	 */
	private static function do_post() : void {
		$private_uuid	= (array_key_exists('MESSAGE-UUID-PRIVATE', self::$headers) && '' != trim(self::$headers['MESSAGE-UUID-PRIVATE'])) ? trim(self::$headers['MESSAGE-UUID-PRIVATE']) : '';
		$public_uuid	= (array_key_exists('MESSAGE-UUID-PUBLIC', self::$headers) && '' != trim(self::$headers['MESSAGE-UUID-PUBLIC'])) ? trim(self::$headers['MESSAGE-UUID-PUBLIC']) : '';

		$body =	file_get_contents("php://input");

		$updated = message::update($private_uuid, $public_uuid, $body);

		self::reply(json_encode($updated));
	}

	/**
	 * Remove the identified message
	 */
	private static function do_delete() : void {
		$private_uuid	= (array_key_exists('MESSAGE-UUID-PRIVATE', self::$headers) && '' != trim(self::$headers['MESSAGE-UUID-PRIVATE'])) ? trim(self::$headers['MESSAGE-UUID-PRIVATE']) : '';
		$public_uuid	= (array_key_exists('MESSAGE-UUID-PUBLIC', self::$headers) && '' != trim(self::$headers['MESSAGE-UUID-PUBLIC'])) ? trim(self::$headers['MESSAGE-UUID-PUBLIC']) : '';

		$removed = message::remove($private_uuid, $public_uuid);

		self::reply(json_encode($removed));
	}

	/**
	 * Additional layers of security could go here:
	 * IP blocking
	 * Rate Limiting
	 * etc
	 */
	private static function security() : void {
		/**
		 * Do not continue if there are no headers available for parsing.
		 */
		if(!is_array(self::$headers) || 0 == count(self::$headers)) {
			self::quit('NO-HEADERS');
		}

		/**
		 * Standardise the Headers to uppercase to avoid dealing with
		 * mixed case variants
		 *
		 * Trim and standardise values to lowercase as well
		 */
		$temporary_headers = [];
		foreach(self::$headers as $header => $value) {
			$upper_header = trim(strtoupper($header));
			if(in_array($upper_header, self::$header_whitelist)){
				$temporary_headers[$upper_header] = trim(strtolower($value));
			}
		}
		self::$headers = $temporary_headers;

		/**
		 * Do not consume any more resources if the method is unacceptable
		 */
		if(!in_array(self::$method, self::$allowed_methods)) {
			self::quit('INVALID-METHOD');
		}

	}

	/**
	 * Send the response back to the user
	 *
	 * @param  string $payload Data to return to the user
	 * @return void
	 */
	private static function reply(string $payload = '') : void {
		header('Status: 200');

		// could add logging here

		exit($payload);
	}

	/**
	 * If we have to quit the application, do it gracefully with an appropriate
	 * header and message so the end user can make changes to try again.
	 *
	 * @param  string $reason Key for how to handle the error
	 * @return void
	 */
	private static function quit(string $reason = '') : void {
		if(array_key_exists($reason, self::$errors)) {
			header('Status: ' . self::$errors[$reason]['HTTP_CODE']);
			header('Message: ' . self::$errors[$reason]['HTTP_MESSAGE']);

			self::$error = $reason;
			self::report();

			exit;
		} else {
			header('Status: 404');
			header('Message: You broke it :(');

			self::$error = 'Unfinished';
			self::report();

			exit;
		}
	}
}

api::initialise();

?>