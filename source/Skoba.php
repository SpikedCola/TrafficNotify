<?php
require_once('Smarty3/Smarty.class.php'); // required for Template
	
/**
 * Skoba PHP Framework
 * 
 * Db Class
 * 
 * @author Jordan Skoblenick <parkinglotlust@gmail.com> April 28, 2013
 */

class Db {

	/**
	 * Current database handle.
	 * 
	 * @var mysqli
	 */
	protected $handle = null;

	/**
	 * Connected boolean.
	 * 
	 * @var bool
	 */
	protected $connected = false;

	/**
	 * A global cache of all the connections made.
	 * 
	 * @var array 
	 */
	protected static $_CONNECTION_CACHE = [];

	/**
	 * An MD5 of the current connection string, to make finding
	 * the current connection in the cache easier.
	 * @var string 
	 */
	protected $connectionMD5 = null;
	
	/**
	 * Connection Username
	 * 
	 * @var string
	 */
	protected $username = null;
	
	/**
	 * Connection Password
	 * 
	 * @var string
	 */
	protected $password = null;
	
	/**
	 * Connection Server
	 * 
	 * @var string
	 */
	protected $server = null;
	
	/**
	 * Connection Database
	 * 
	 * @var string
	 */
	protected $database = null;

	/**
	 * Instantiate and connect to a DB.
	 * 
	 * Defaults to using SKOBA_DB_ defines if parameters
	 * are not specified
	 * 
	 * @param string $database
	 * @param string $server
	 * @param string $username
	 * @param string $password
	 * @throws Exception
	 */
	public function __construct($database = null, $server = null, $username = null, $password = null) {
		// credential check
		if (!defined('SKOBA_DB_USERNAME') && !$username) { throw new Exception('SKOBA_DB_USERNAME must be defined or a username passed in before instantiating a Db'); }
		if (!defined('SKOBA_DB_PASSWORD') && !$password) { throw new Exception('SKOBA_DB_PASSWORD must be defined or a password passed in before instantiating a Db'); }
		if (!defined('SKOBA_DB_SERVER') && !$server)     { throw new Exception('SKOBA_DB_SERVER must be defined or a server passed in before instantiating a Db'); } 
		if (!defined('SKOBA_DB_DATABASE') && !$database) { throw new Exception('SKOBA_DB_DATABASE must be defined or a database passed in before instantiating a Db'); }

		// prever vars over defines
		$this->database = $database ? $database : SKOBA_DB_DATABASE;
		$this->server = $server ? $server : SKOBA_DB_SERVER;
		$this->username = $username ? $username : SKOBA_DB_USERNAME;
		$this->password = $password ? $password : SKOBA_DB_PASSWORD;
		
		// sanity check
		if (!extension_loaded('mysqli')) { throw new Exception('You must enable the "mysqli" extension to use the Db class'); }

		// all good, connect
		$this->connect();
	}

	/**
	 * Internal database connect function.
	 */
	protected function connect() {
		if (!$this->connected) {
			// see if the current connection is cached
			$this->connectionMD5 = md5($this->username.$this->password.$this->server.$this->database);
			if (isset(self::$_CONNECTION_CACHE[$this->connectionMD5])) {
				// use existing connection
				$this->handle = self::$_CONNECTION_CACHE[$this->connectionMD5];
				// make sure connection is still alive
				if ($this->is_connected = $this->handle->ping()) {
					return;
				}
			}

			// make a new connection
			$this->handle = new mysqli($this->server, $this->username, $this->password, $this->database);
			if ($this->handle->connect_error) {
				throw new Exception('Database connection failed: '.$this->handle->connect_error);
			}

			// cache connection
			self::$_CONNECTION_CACHE[$this->connectionMD5] = $this->handle;
		}
	}

	/**
	 * Looks up the structure of a table
	 * 
	 * @param string $table The table name
	 * @return array An associative array of structure details
	 */
	public function structure($table) {
		$output = array ();
		$q = new Query('SHOW COLUMNS FROM ' . $table);
		$columns = $this->getAll($q);
		foreach ($columns as $row) {
			$row['Size'] = null;
			$row['AutoIncrement'] = false;
			$row['Unique'] = false;
			$row['MySQLType'] = $row['Type'];
			// split off the (size) part of the definition
			if (preg_match('/^([a-z]+)(\(([0-9]+)\))?\s*(signed|unsigned)?$/i', $row['Type'], $temp)) {
				$row['Type'] = $temp[1];
				if (isset ($temp[3])) {
					$row['Size'] = (int) $temp[3];
				}
				if (isset ($temp[4]) && $temp[4] == 'unsigned') {
					$row['Signed'] = false;
				}
				else {
					$row['Signed'] = true;
				}
			}
			// mysql has sizes for it's special predefined types
			switch ($row['Type']) {
				case 'bit' :
					$size = 1;
					break;
				case 'tinyint' :
					$size = 8;
					break;
				case 'smallint' :
					$size = 16;
					break;
				case 'mediumint' :
					$size = 24;
					break;
				case 'int' :
					$size = 32;
					break;
				case 'bigint' :
					$size = 64;
					break;
				case 'tinyblob' :
					$size = 255;
					break;
				case 'blob' :
					$size = 65535;
					break;
				case 'mediumblob' :
					$size = 16777215;
					break;
				case 'longblob' :
					$size = 4294967295;
					break;
				case 'tinytext' :
					$size = 255;
					break;
				case 'text' :
					$size = 65535;
					break;
				case 'mediumtext' :
					$size = 16777215;
					break;
				case 'longtext' :
					$size = 4294967295;
					break;
				default:
					$size = $row['Size'];
			}
			$row['Size'] = $size;

			// combine mysql's many different types into a few common ones
			switch ($row['Type']) {
				case 'bit' :
				case 'tinyint' :
				case 'smallint' :
				case 'mediumint' :
				case 'int' :
				case 'bigint' :
					$row['Type'] = 'integer';
					break;
				case 'binary' :
				case 'varbinary' :
				case 'tinyblob' :
				case 'blob' :
				case 'mediumblob' :
				case 'longblob' :
					$row['Type'] = 'binary';
					break;
				case 'tinytext' :
				case 'text' :
				case 'char' :
				case 'varchar' :
				case 'mediumtext' :
				case 'longtext' :
					$row['Type'] = 'string';
					break;
			}

			// for int type get min and max numbers allowed
			if ($row['Type'] == 'integer') {
				if (function_exists('bcpow')) {
					$range = bcpow(2, $row['Size']);
					if ($row['Signed'] && $row['Size'] > 1) {
						$row['Minimum'] = '-' . bcdiv($range, 2);
						$row['Maximum'] = bcsub(bcdiv($range, 2), 1);
					} else {
						$row['Minimum'] = '0';
						$row['Maximum'] = bcsub($range, 1);
					}
				}
				else {
					if ($row['Signed'] && $row['Size'] > 1) {
						$row['Maximum'] = 2147483647;
						$row['Minimum'] = 0;
					} else {
						$row['Maximum'] = 2147483647;
						$row['Minimum'] = -2147483648;
					}
				}
			}

			if ($row['Null'] === 'NO') {
				$row['Null'] = false;
			}
			else {
				$row['Null'] = true;
			}

			$row['PrimaryKey'] = false;
			$row['Unique'] = false;
			if ($row['Key'] == 'PRI') {
				$row['PrimaryKey'] = true;
				$row['Unique'] = true;
			}
			elseif ($row['Key'] == 'UNI') {
				$row['Unique'] = true;
			}

			if (empty($row['Key'])) {
				$row['Key'] = null;
			}

			if ($row['Extra'] === 'auto_increment') {
				$row['AutoIncrement'] = true;
				$row['Extra'] = null;
			} 
			else {
				$row['AutoIncrement'] = false;
			}

			if (empty($row['Extra'])) {
				$row['Extra'] = null;
			}

			if ($row['Default'] == 'CURRENT_TIMESTAMP') {
				$row['Default'] .= '()';
			}

			// for int type get min and max numbers allowed
			if (preg_match('/^(float|double|decimal)\(([0-9]+),([0-9]+)\)\s*(signed|unsigned)?$/i', $row['Type'], $temp)) {
				$row['Type'] = 'float';
				$row['Digits'] = (int) $temp[2];
				$row['Precision'] = (int) $temp[3];
				if (isset ($temp[4]) && $temp[4] == 'unsigned') {
					$row['Signed'] = false;
				}
				else {
					$row['Signed'] = true;
				}
				$whole = $row['Digits'] - $row['Precision'];
				$max = '';
				for ($t = 0; $t < $whole; $t++) {
					$max .= '9';
				}
				$max .= '.';
				for ($t = 0; $t < $row['Precision']; $t++) {
					$max .= '9';
				}
				$row['Maximum'] = $max;
				if ($row['Signed']) {
					$row['Minimum'] = '-' . $max;
				}
				else {
					$row['Minimum'] = '0';
				}
			}

			// parse out enum types
			if (preg_match('/^enum\((.*)\)$/i', $row['Type'], $temp)) {
				$row['Type'] = 'enum';
				$row['Enum'] = [];
				$enums = explode(',', $temp[1]);
				foreach ($enums as $enum) {
					$enum = substr($enum, 1, strlen($enum) - 2);
					$enum = stripslashes($enum);
					$row['Enum'][] = $enum;
				}
			}

			// parse out set types
			if (preg_match('/^set\((.*)\)$/i', $row['Type'], $temp)) {
				$row['Type'] = 'set';
				$row['Set'] = [];
				$enums = explode(',', $temp[1]);
				foreach ($enums as $enum) {
					$enum = substr($enum, 1, strlen($enum) - 2);
					$enum = stripslashes($enum);
					$row['Set'][] = $enum;
				}
			}

			$output[$row['Field']] = [];
			foreach ($row as $key => $val) {
				$key = strtolower($key);
				$output[$row['Field']][$key] = $val;
			}
		}

		return $output;

	}

	public static function escape($value) {
		switch (gettype($value)) {
			case 'boolean':
				if ($value === true) {
					$value = '1';
				}
				else {
					$value = '0';
				}
				break;
			case 'integer':
			case 'double':
				$value = strval($value);
				break;
			case 'string':
				if ($value === '') {
					$value = "''";
					break;
				}
				if (!count(self::$_CONNECTION_CACHE)) {
					self::$_CONNECTION_CACHE[] = new Db();
				}
				$dbh = reset(self::$_CONNECTION_CACHE); // grab first connection
				$value = "'".$dbh->real_escape_string($value)."'";
				break;
			case 'NULL':
			case 'array':
			case 'object':
			case 'resource':
			case 'user function':
			case 'unknown type':
			default:
				$value = 'NULL';
				break;
		}
		return $value;
	}

	public function getEnum($table, $column) {
		// @todo: can probably just use $tableInfo for this
		$res = $this->query('SHOW COLUMNS FROM `'.$table.'` LIKE '.Db::Escape($column));
		$enumValues = [];
		if ($row = $res->fetch_row()) {
			$matches = [];
			preg_match_all("/'(.*?)'/", $row[1], $matches);
			$enumValues = array_values($matches[1]);
		}
		return $enumValues;
	}

	/**
	 * Frees and closes a mysqli result.
	 * 
	 * @param mysqli_result $res The mysqli result to clear
	 */
	protected function clear(mysqli_result $res) {
		$res->free();
	}

	/**
	 * Internal method for running a raw query
	 * 
	 * @param string $query SQL query to execute
	 */
	public function query($query) {
		$result = $this->handle->query((string)$query);

		if ($result === false) {
			throw new Exception('Query error: '.$this->handle->error);
		}

		return $result;
	}

	/**
	 * Gets the first possible field specified by the query.
	 * 
	 * @param Query $q
	 * @return mixed The first possible field in the query,
	 * or false if no row is found.
	 */
	public function getOne(Query $q) {
		// save old limit
		$oldLimit = $q->getLimit();

		// limit to one row, while keeping existing offset
		$q->setLimit(1, $oldLimit[1]);

		// get result
		$res = $this->query($q->getSelect());

		// put original limit back
		$q->setLimit($oldLimit[0], $oldLimit[1]);

		if ($res->num_rows == 0) {
			return false;
		}

		$row = $res->fetch_row();

		$this->clear($res);

		return $row[0];
	}

	/**
	 * Gets a row of data from a query.
	 * 
	 * @param Query $q
	 * @return mixed An array of row data, or false
	 * if no row is found.
	 */
	public function getRow(Query $q) {
		// save old limit
		$oldLimit = $q->getLimit();

		// limit to one row
		$q->setLimit(1);

		// get result
		$res = $this->query($q->getSelect());

		// put original limit back
		$q->setLimit($oldLimit[0], $oldLimit[1]);

		if ($res->num_rows == 0) {
			return false;
		}

		$row = $res->fetch_assoc();

		$this->clear($res);

		return $row;
	}

	/**
	 * Gets a column of data from a query.
	 * 
	 * @param Query $q
	 * @param string $column
	 * @return array An array of values, or an empty array
	 * if  there are no values to return.
	 */
	public function getCol(Query $q, $column) {
		$rows = [];

		// get result
		$res = $this->query($q->getSelect());

		if ($res->num_rows > 0) {
			// can either grab the first column in the row,
			// or look up our columns, grab the first one,
			// and do it by assoc. either one *could* be 
			// wrong (multiple columns), so im going with 
			// the easier one
			while ($row = $res->fetch_assoc()) {
				if (!array_key_exists($column, $row)) {
					throw new Exception($column.' does not exist in return data set');
				}
				$rows[] = $row[$column];
			}
		}

		$this->clear($res);

		return $rows;
	}

	/**
	 * Gets all the rows of data from a query.
	 * 
	 * @param Query $q
	 * @return array An array of rows, or an empty array 
	 * if there are no rows to return.
	 */
	public function getAll(Query $q) {
		$rows = [];

		// get result
		$res = $this->query($q->getSelect());

		if ($res->num_rows > 0) {
			while ($row = $res->fetch_assoc()) {
				$rows[] = $row;
			}
		}

		$this->clear($res);

		return $rows;
	}

	/**
	 * Performs a COUNT(*) on a query and returns the number
	 * of results.
	 * 
	 * @param Query $q
	 * @return int Number of rows in the query result.
	 */
	public function getCount(Query $q) {
		// save old columns
		$oldColumns = $q->getColumns();

		// set new count column
		$q->setColumn('COUNT(*)');

		if (!$count = $this->getOne($q)) {
			$count = 0;
		}

		// restore old columns
		$q->setColumns($oldColumns);

		return $count;
	}

	/**
	 * Gets all the rows of data from a query and returns an
	 * associative array keyed by $column, and either an array of
	 * values if $secondCol is null, or a single value if 
	 * $secondCol is specified
	 * 
	 * @param Query $q
	 * @param string $column
	 * @param string $secondCol
	 * @return array Associative array of $column => (array of values, or a single value)
	 */
	public function getAssoc(Query $q, $column, $secondCol = null) {
		$rows = [];

		// get result
		$res = $this->query($q->getSelect());

		if ($res->num_rows > 0) {
			while ($row = $res->fetch_assoc()) {
				if (!array_key_exists($column, $row)) {
					throw new Exception('Column '.$column.' does not exist in returned data set');
				}
				if ($secondCol !== null && !array_key_exists($secondCol, $row)) {
					throw new Exception('Column '.$secondCol.' does not exist in returned data set');
				}
				// save whole row
				if ($secondCol === null) {
					$data = $row;
				}
				// save only this column
				else {
					$data = $row[$secondCol];
				}
				$rows[$row[$column]] = $data;
			}
		}

		$this->clear($res);

		return $rows;
	}

	/**
	 * Performs an INSERT on the given query.
	 * 
	 * @param Query $q
	 * @param bool $ignore
	 * @returns int ID of inserted row
	 */
	public function insert(Query $q, $ignore = false) {
		$this->query($q->getInsert($ignore));
		return $this->handle->insert_id;
	}

	/**
	 * Performs an UPDATE on the given query.
	 * 
	 * @param Query $q
	 */
	public function update(Query $q) {
		$this->query($q->getUpdate());
	}

	/**
	 * Performs a DELETE on the given query.
	 * 
	 * @param Query $q
	 */
	public function delete(Query $q) {
		$this->query($q->getDelete());
	}

	/**
	 * Begins a transaction.
	 */
	public function beginTransaction() {
		$this->query('START TRANSACTION');
	}

	/**
	 * Commits a transaction.
	 */
	public function commit() {
		$this->query('COMMIT');
	}

	/**
	 * Rolls back a transaction.
	 */
	public function rollback() {
		$this->query('ROLLBACK');
	}
}

/**
 * Skoba PHP Framework
 * 
 * DbTable Class
 * 
 * An abstract representation of a database table.
 * Exposes all columns as properties of the object, along
 * with the ability to create __set_(xxx) and __get_(xxx)
 * functions to extend the way certain properties work.
 * 
 * Also provides beforeSave, afterSave, beforeCreate, 
 * afterCreate, beforeLoad and afterLoad methods for data 
 * validation and whatever else you can think of.
 * 
 * @author Jordan Skoblenick <parkinglotlust@gmail.com> May 14, 2013
 */

abstract class DbTable {
	protected $tableName = null;

	protected static $db;

	protected $tableInfo = array();

	protected $data = array();

	protected $originalData = array();

	protected $loaded = false;

	protected $_id = null;

	protected $primaryKey = null;

	/**
	 * Called before serializing an object
	 */
	public function __sleep() {
		unset(self::$db);
		return array(
		    'data',
		    '_id',
		    'loaded',
		    'originalData',
		    'primaryKey',
		    'tableInfo',
		    'tableName'
		);
	}

	/**
	 * Called after unserializing an object
	 */
	public function __wakeup() {
		self::$db = new Db();
	}

	/**
	 * Allow isset() to check table columns
	 * 
	 * @param string $name
	 * @return bool
	 */
	public function __isset($name) {
		return isset($this->data[$name]);
	}
	
	/**
	 * Accepts an id to load a record, or null to create
	 * a new record
	 * 
	 * @param int $id
	 * @throws Exception
	 */
	public function __construct($id = null) {
		if (!self::$db){
			self::$db = new Db();
		}
		if (!$this->tableName) {
			throw new Exception('You must declare a $tableName override in your class before using DbTable');
		}
		$this->tableInfo = $this->db->structure($this->tableName);
		$primaryKeys = 0;
		foreach ($this->tableInfo as $t){
			if($t['key'] == 'PRI'){
				$this->primaryKey = $t['field']; 
				$primaryKeys++;
			}
		}
		if ($primaryKeys > 1) {
			throw new Exception('DbTable is designed for classes with a single primary key');
		}
		$this->_id = $id;
	}

	/**
	 * Creates a new row in the database
	 * 
	 * @param bool $ignore Whether to do an INSERT IGNORE
	 * @return int ID of created row
	 * @throws Exception
	 */
	public function create($ignore = false) {
		$this->db->beginTransaction();
		try {
			if (method_exists($this, 'beforeCreate')) {
				$this->beforeCreate();
			}
			if ($this->id) {
				throw new Exception($this->primaryKey.' is already set and may not be re-created.');
			}
			$q = new Query();
			$q->addTable($this->tableName);
			if (empty($this->data)) {
				throw new Exception('Data array is empty.');
			}
			foreach ($this->data as $column => &$value) {
				$value = self::cast($value, $this->tableInfo[$column]);
			}
			unset($value); // clean up reference
			$q->addFields($this->data);
			$id = $this->db->insert($q, $ignore);

			if (!$id) {
				// we didnt throw, but id is empty. this can happen
				// if the INSERT didnt affect a column with the 
				// AUTO_INCREMENT attribute, ex. if we've specified
				// the primary key directly. if the pk is set, use it
				$this->_id = $this->data[$this->primaryKey];
			}
			else {
				$this->_id = $this->data[$this->primaryKey] = $id;
			}

			if (method_exists($this, 'afterCreate')) {
				$this->afterCreate();
			}

			$this->db->commit();
		} 
		catch(Exception $e) {
			$this->db->rollback();
			throw $e;
		}

		$this->loaded = false;

		return $this->id;
	}

	/**
	 * Saves the current row to the database
	 * 
	 * @throws Exception
	 */
	public function save() {
		if (!$this->id) {
			return $this->create();
		}
		$saved = false;
		$this->db->beginTransaction();
		try {
			if (method_exists($this, 'beforeSave')) {
				$this->beforeSave();
			}

			foreach ($this->data as $column => &$value) {
				$value = self::cast($value, $this->tableInfo[$column]);
			}
			unset($value); // clean up reference

			// see if anything has changed
			if (array_diff_assoc($this->data, $this->originalData)) {
				unset($this->data[$this->primaryKey]);
				$q = new Query();
				$q->addTable($this->tableName);
				$q->addWhere($this->primaryKey, $this->id);
				$q->addFields($this->data);
				$this->db->update($q);
				$saved = true;
			} 

			if (method_exists($this, 'afterSave')) {
				$this->afterSave();
			}

			$this->db->commit();
		} 
		catch(Exception $e) {
			$this->db->rollback();
			throw $e;
		}

		$this->data = array();
		$this->originalData = array();
		$this->loaded = false;

		return $saved;
	}

	/**
	 * Clears any changes to the current row
	 */
	public final function reload() {
		if ($this->loaded) {
			$this->data = $this->originalData;
			if (method_exists($this, 'afterLoad')) {
				$this->afterLoad();
			}
		} 
		else {
			$this->load();
		}
	}

	/**
	 * Load the database row into the data array
	 */
	public function load($id = null){
		if ($this->loaded) {
			return;
		}

		$this->loaded = true;

		if ($id) {
			$this->_id = $id;
		}

		if ($this->id){
			$q = new Query();
			$q->addTable($this->tableName);
			$q->addWhere($this->primaryKey, $this->id);
			if ($data = $this->db->getRow($q)) {
				// clear existing data
				$this->data = array();
				foreach ($data as $column => $value) {
					if (!array_key_exists($column, $this->data)) {
						$this->data[$column] = self::cast($value, $this->tableInfo[$column]);
					}
				}
			} 
			else {
				throw new Exception('An id was specified but a corresponding database row does not exist in '.$this->tableName.' for '.$this->id);
			}
		} 
		else {
			foreach ($this->tableInfo as $key => $value) {
				if (!array_key_exists($key, $this->data)) {
					$this->data[$key] = isset($this->tableInfo[$key]['default']) ? $this->tableInfo[$key]['default'] : null;
				}
			}
		}

		$this->originalData = $this->data;

		if (method_exists($this, 'afterLoad')) {
			$this->afterLoad();
		}
	}

	/**
	 * Casts a database value to the type given by the column
	 * definition
	 * 
	 * @param mixed $value
	 * @param array $validation
	 * @return mixed
	 */
	protected static function cast($value, array $validation) {
		if ($value !== null || $validation['null'] === false) {
			switch ($validation['type']) {
				case 'bit':
				case 'integer':
					return intval($value);
				case 'string':
					return strval($value);
				case 'float':
					return floatval($value);
			}
		}
		return $value;
	}

	/**
	 * Magic "getter" to convert $object->property
	 * into a handy __get_property() function
	 * 
	 * @property mixed $var Property to return
	 * @return mixed
	 */
	public function __get($var) {
		switch ($var) {
			// netbeans doesnt self::db->insert() syntax
			// so this will let us do $this->db even with
			// the static db
			case 'db':
				return self::$db;
			case 'id':
				return $this->_id;
			case 'primaryKey':
				return $this->primaryKey;
			case 'loaded':
				return $this->loaded;
		}
		if (!$this->loaded) {
			$this->load();
		}
		switch ($var) {
			case 'data':
				return $this->data;
			case 'originalData':
				return $this->originalData;
			default:
				$value = null;
				$matches = [];
				$getter = '_get_'.$var;
				// first try for data/original values
				if (array_key_exists($var, $this->tableInfo)) {
					if (array_key_exists($var, $this->data)) {
						$value = $this->data[$var];
					}
				} 
				elseif(preg_match('/^original_(.*)/',$var,$matches)) {
					$originalVar = $matches[1];
					if(array_key_exists($originalVar, $this->originalData)) {
						$value = $this->originalData[$originalVar];
					}
				}
				// now check for getter functions
				if (method_exists($this, $getter)) {
					$value = $this->$getter($value);
				}
				elseif (!array_key_exists($var, $this->tableInfo) && (!isset($originalVar) || !array_key_exists($originalVar, $this->tableInfo))) {
					throw new Exception($var.' does not exist in '.$this->tableName);
				}
				return $value;
		}
	}

	/**
	 * Magic "setter" to convert eg. $object->height = 12
	 * into a handy __set_height($value[=12]) function
	 * 
	 * @property mixed $var Property to set
	 * @property mixed $value Value to set
	 */
	public function __set($var, $value) {
		if(!$this->loaded) {
			$this->load();
		}
		switch ($var) {
			case 'id':
			case $this->primaryKey:
				throw new Exception('You cannot change '.$this->primaryKey.' once set');
			default:
				$setter = '_set_'.$var;
				if (method_exists($this, $setter)) {
					$value = $this->$setter($value);
				}
				elseif (!in_array($var, array_keys($this->tableInfo))) {
					throw new Exception($var.' does not exist in '.$this->tableName);
				}
				if (array_key_exists($var, $this->tableInfo)) {
					self::validate($var, $value, $this->tableInfo[$var]);
					$this->data[$var] = self::cast($value, $this->tableInfo[$var]);
				}
				return $value;
		}
	}

	/**
	 * Validate a value against database column constraints
	 * 
	 * @param string $column
	 * @param mixed $value
	 * @param array $validation
	 * @throws Exception
	 */
	protected static function validate($column, $value, array $validation = array()) {
		if ($value === null) {
			if ($validation['null'] === false) {
				throw new Exception($column.' is a required field');
			}
		}
		elseif ($validation['default'] !== null) {
			switch ($validation['type']) {
				case 'integer':
					$min = intval($validation['minimum']);
					$max = intval($validation['maximum']);
					try {
						$valid = ValidateThrow($value, 'int', $min, $max);
					} 
					catch (Exception $e) {
						throw new Exception($column.' : '.$value.' is not a valid integer between '.$min.' and '.$max);
					}
					break;
				case 'string':
					$min = 0;
					$max = $validation['size'];
					try {
						$valid = ValidateThrow($value, 'str', $min, $max);
					} 
					catch (Exception $e) {
						throw new Exception($column.' : '.$value.' is not a valid string between '.$min.' and '.$max.' characters');
					}
					break;
				case 'float':
					$min = floatval($validation['minimum']);
					$max = floatval($validation['maximum']);
					try {
						$valid = ValidateThrow($value, 'float', $min, $max);
					} 
					catch (Exception $e) {
						throw new Exception($column.' : '.$value.' is not a valid decimal number between '.$min.' and '.$max);
					}
					break;
				case 'enum':
					try {
						$valid = ValidateThrow($value, 'string', $validation['enum']);
					} 
					catch (Exception $e) {
						throw new Exception($column.' : '. $value.' is not one of: '.join(', ',$validation['enum']));
					}
					break;
				case 'set':
					$values = explode(',', $value);
					foreach ($values as $v) {
						if (!in_array($v, $validation['set'])) {
							throw new Exception($column.': '.$value.' is not one of: '.implode(', ', $validation['set']));
						}
					}
					break;
				case 'date':
					if ($value && Validate($value, 'str', '/^[12][0-9]{3}-([1][012]|[0][0-9])-([3][0-9]|[012][0-9])$/') === null) {
						throw new Exception($column.': '.$value.' should be a date in the format: YYYY-MM-DD.');
					}
					break;
				case 'timestamp':
					if ($value && strtotime($value) === false) {
						throw new Exception($column.': '.$value.' should be a valid date/time');
					}
					break;
				default:
					throw new Exception($column.': '.$validation['type'].' validation does not exist');
			}
		}
	}
}

	/**
	 * Skoba PHP Framework
	 * 
	 * DbTableMultipleKeys Class
	 * 
	 * Exactly the same as DbTable, but supports more than 1 
	 * primary key.
	 * 
	 * @author Jordan Skoblenick <parkinglotlust@gmail.com> May 14, 2013
	 */
	
	abstract class DbTableMultipleKeys extends DbTable {
		/**
		 * @var array Array of table keys
		 */
		protected $primaryKeys = [];
		
		/**
		 * Accepts an id to load a record, or null to create
		 * a new record
		 * 
		 * @param int $id
		 * @throws Exception
		 */
		public function __construct($keys = null) {
			if (!self::$db){
				self::$db = new Db();
			}
			if (!$this->tableName) {
				throw new Exception('You must declare a $tableName override in your class before using DbTable');
			}
			$this->tableInfo = $this->db->structure($this->tableName);
			foreach ($this->tableInfo as $t){
				if($t['key'] == 'PRI'){
					$this->primaryKeys[] = $t['field']; 
				}
			}
			if (count($this->primaryKeys) < 2) {
				throw new Exception('DbTableMultipleKeys is designed for classes with 2 or more primary keys');
			}
			
			if (is_array($keys)) {
				$i = 0;
				foreach ($keys as $key => $value) {
					if (in_array($key, $this->primaryKeys)) {
						$this->data[$key] = $value;
						$i++;
					}
				}
				if ($i == count($this->primaryKeys)) {
					$this->load();
				}
			}
		}
		
		/**
		 * Creates a new row in the database
		 * 
		 * @param bool $ignore Whether to do an INSERT IGNORE
		 * @return int ID of created row
		 * @throws Exception
		 */
		public function create($ignore = false) {
			$this->db->beginTransaction();
			try {
				if (method_exists($this, 'beforeCreate')) {
					$this->beforeCreate();
				}
				
				$q = new Query();
				$q->addTable($this->tableName);
				if (empty($this->data)) {
					throw new Exception('Data array is empty.');
				}
				foreach ($this->data as $column => &$value) {
					$value = self::cast($value, $this->tableInfo[$column]);
				}
				unset($value); // clean up reference
				$q->addFields($this->data);
				foreach ($this->primaryKeys as $pk) {
					$q->addWhere($pk, $this->data[$pk]);
				}
				
				if (!$ignore && $this->db->getRow($q)) {
					throw new Exception('A row already exists for the given primary keys');
				}
				
				$this->db->insert($q, $ignore);
				
				if (method_exists($this, 'afterCreate')) {
					$this->afterCreate();
				}

				$this->db->commit();
			} 
			catch(Exception $e) {
				$this->db->rollback();
				throw $e;
			}

			$this->loaded = false;

			return $this->id;
		}
		
		/**
		 * Saves the current row to the database
		 * 
		 * @throws Exception
		 */
		public function save() {
			if (!$this->loaded) {
				return $this->create();
			}
			$this->db->beginTransaction();
			try {
				if (method_exists($this, 'beforeSave')) {
					$this->beforeSave();
				}

				foreach ($this->data as $column => &$value) {
					$value = self::cast($value, $this->tableInfo[$column]);
				}
				unset($value); // clean up reference
				// see if anything has changed
				if (array_diff_assoc($this->data, $this->originalData)) {
					$data = $this->data;
					foreach ($this->primaryKeys as $pk) {
						unset($data[$pk]);
					}
					$q = new Query();
					$q->addTable($this->tableName);
					foreach ($this->primaryKeys as $pk) {
						$q->addWhere($pk, $this->data[$pk]);
					}
					$q->addFields($data);
					$this->db->update($q);
				} 
				
				if (method_exists($this, 'afterSave')) {
					$this->afterSave();
				}

				$this->db->commit();
			} 
			catch(Exception $e) {
				$this->db->rollback();
				throw $e;
			}

			$this->data = array();
			$this->originalData = array();
			$this->loaded = false;
		}

		/**
		 * Load the database row into the data array
		 */
		public function load($id = null){
			if ($this->loaded) {
				return;
			}

			$this->loaded = true;

			$ready = true;
			foreach ($this->primaryKeys as $pk) {
				if (!ValidateArrayKey($pk, $this->data)) {
					$ready = false;
				}
			}
			if ($ready) {
				$q = new Query();
				$q->addTable($this->tableName);
				foreach ($this->primaryKeys as $pk) {
					$q->addWhere($pk, $this->data[$pk]);
				}
				$this->data = array();
				if ($data = $this->db->getRow($q)) {
					foreach ($data as $column => $value) {
						if (!array_key_exists($column, $this->data)) {
							$this->data[$column] = self::cast($value, $this->tableInfo[$column]);
						}
					}
				} 
				else {
					throw new Exception('An id was specified but a corresponding database row does not exist in '.$this->tableName);
				}
			} 
			else {
				foreach ($this->tableInfo as $key => $value) {
					if (!array_key_exists($key, $this->data)) {
						$this->data[$key] = !empty($this->tableInfo[$key]['default']) ? $this->tableInfo[$key]['default'] : null;
					}
				}
			}

			$this->originalData = $this->data;

			if (method_exists($this, 'afterLoad')) {
				$this->afterLoad();
			}
		}
		
		function __get_primaryKeys() {
			return $this->primaryKeys;
		}
		
		/**
		 * Magic "setter" to convert eg. $object->height = 12
		 * into a handy __set_height($value[=12]) function
		 * 
		 * @property mixed $var Property to set
		 * @property mixed $value Value to set
		 */
		public function __set($var, $value) {
			if(!$this->loaded) {
				$this->load();
			}
			if (in_array($var, $this->primaryKeys) && ValidateArrayKey($var, $this->data)) {
				throw new Exception('You may not change '.$var.' once set');
			}
			$setter = '_set_'.$var;
			if (method_exists($this, $setter)) {
				$value = $this->$setter($value);
			}
			elseif (!in_array($var, array_keys($this->tableInfo))) {
				throw new Exception($var.' does not exist in '.$this->tableName);
			}
			if (array_key_exists($var, $this->tableInfo)) {
				self::validate($var, $value, $this->tableInfo[$var]);
				$this->data[$var] = self::cast($value, $this->tableInfo[$var]);
			}
			return $value;
		}
	}


/**
 * Runs at the end of execution. If an error is caught, report it.
 */
register_shutdown_function('skobaShutdownHandler');
function skobaShutdownHandler() {
	$error = error_get_last();
	// only handle fatal errors - others are caught in set_error_handler
	if ($error && $error['type'] == E_ERROR) {  
		ErrorHandler::ReportError(new ErrorException($error['message'], $error['type'], 0, $error['file'], $error['line']));
	}
}

/**
 * Catches any unhandled exceptions, reports them to us, and handles them accordingly
 */
set_exception_handler('skobaExceptionHandler');
function skobaExceptionHandler($ex) {
	ErrorHandler::ReportError($ex);
	ErrorHandler::DisplayErrorPage($ex);
}

/**
 * Catches any PHP errors and converts them into an exception to be caught
 */
set_error_handler('skobaErrorHandler');
function skobaErrorHandler($number, $message, $file, $line, $context) {
	if (error_reporting() == 0) {
		// this happens if we call a function with @
		return;
	}
	
	$previousException = null;
	// if possible, pass up previous exception so we get some context
	if ($context) {
		foreach ($context as $item) {
			if (is_subclass_of($item, 'Exception')) {
				$previousException = $item;
				break;
			}
		}
	}
	
	ErrorHandler::ReportError(new ErrorException($message, $number, 0, $file, $line, $previousException));
	return false; // handle errors with the default handler
}

class ErrorHandler {
	/**
	 * Displays an error page based on whether we're CLI, debug, live, etc
	 * 
	 * @param mixed $error Exception or JSON object
	 */
	public static function DisplayErrorPage(Exception $ex) {
		if (php_sapi_name() == 'cli' || (defined('PROJECT_STATUS') && PROJECT_STATUS == 'development')) {
			if (php_sapi_name() != 'cli' && isset($ex->xdebug_message)) {
				// if xdebug is enabled (and not CLI), let's use the xdebug formatted message
				$message = "<br /><font size='1'><table class='xdebug-error' dir='ltr' border='1' cellspacing='0' cellpadding='1'>";
				$message .= sprintf("<tr><th align='left' bgcolor='#f57900' colspan=\"5\"><span style='background-color: #cc0000; color: #fce94f; font-size: x-large;'>( ! )</span> Fatal error: Uncaught exception '%s' with message '%s' in %s on line <i>%s</i></th></tr>", get_class($ex), $ex->getMessage(), $ex->getFile(), $ex->getLine()
				);
				$message .= $ex->xdebug_message;
				$message .= "</table></font>";
			}
			else {
				// build trace
				$trace = $ex->getTrace();
				$result = array();
				$key = 0;
				foreach ($trace as $key => $stackPoint) {
					foreach ($stackPoint['args'] as $argKey => $arg) {
						if (is_object($arg)) {
							$stackPoint['args'][$argKey] = get_class($arg);
						}
					}
					$result[] = sprintf(
						"#%s %s(%s): %s(%s)", 
						$key, 
						isset($stackPoint['file']) ? $stackPoint['file'] : '', 
						isset($stackPoint['line']) ? $stackPoint['line'] : '', 
						$stackPoint['function'], 
						isset($stackPoint['args']) ? json_encode($stackPoint['args']) : ''
					);
				}
				// trace always ends with {main}
				$result[] = '#'.++$key.' {main}';

				$messageText = sprintf(
					"<b>PHP Fatal error:</b>  Uncaught exception '<b>%s</b>' with message '<b>%s</b>' in %s:%s\n<b>Stack trace:</b>\n%s\nthrown in <b>%s</b> on line <b>%s</b>", get_class($ex), $ex->getMessage(), $ex->getFile(), $ex->getLine(), implode("\n", $result), $ex->getFile(), $ex->getLine()
				);

				if (php_sapi_name() == 'cli') {
					// cli shouldnt see any html tags
					$message = strip_tags($messageText);
				}
				else {
					$message = "<pre>$messageText</pre>";
				}
			}
			die($message);
		}
		else {
			// maybe header: 503, for now just play it safe
			die('An unknown error occurred');
		}
	}

	/**
	 * Shortcut for creating an SQS client object
	 * 
	 * @return \Aws\Sqs\SqsClient
	 */
	protected static function LoadSQSClient() {
		require_once('Amazon AWS/aws-autoloader.php');

		// create sqs client
		return \Aws\Sqs\SqsClient::factory([
			    'key' => SQS_KEY,
			    'secret' => SQS_SECRET_KEY,
			    'region' => 'us-east-1'
		]);
	}

	/**
	 * Reports an error to our error server
	 * 
	 * Expects either an Exception object, or a raw JSON message object
	 * 
	 * @param mixed $error Exception or JSON object
	 */
	public static function ReportError(Exception $ex) {
		if (defined('DISABLE_ERROR_REPORTING') && DISABLE_ERROR_REPORTING) {
			return;
		}

		global $argv;

		$filename = $ex->getFile();
		// dont report errors for these freaking plugins
		$stupidPlugins = [
		    'css3_web_pricing_tables_grids',
		    'feedburner-plugin',
		    'google-analytics-for-wordpress',
		    'gravityforms',
		    'gravityformsaweber',
		    'jetpack',
		    'membermouse',
		    'nextgen-gallery',
		    'nrelate-related-content',
		    'redirection',
		    'share-and-follow',
		    'u-design/lib/plugin-activation/class-tgm-plugin-activation', // plugin in theme file
		    'ubermenu',
		    'ubermenu-icons',
		    'w3-total-cache',
		    'widget-context',
		    'wordpress-https',
		    'wordpresscom-popular-posts',
		    'wpseo-video',
		    'wp-pagenavi',
		    'wp-postratings',
		    'yop-poll'
		];
		if (preg_match('$wp-content[\\\\/](plugins|themes)[\\\\/]('.implode('|', $stupidPlugins).')$i', $filename)) {
			return;
		}

		$message = self::BuildMessageFromException($ex);
		
		try {
			// send message
			$client = self::LoadSQSClient();
			$client->sendMessage([
			    'QueueUrl' => SQS_QUEUE_URL,
			    'MessageBody' => $message
			]);
		}
		catch (Exception $ex) {
			// nothing we can do if we're live
			if (defined('PROJECT_STATUS') && PROJECT_STATUS == 'development') {
				self::DisplayErrorPage($ex);
			}
		}
	}
	
	protected static function BuildMessageFromException(Exception $ex, array $passedInTrace = [], $currentDepth = 0, $includeExtraParams = true) {
		global $argv;
		
		$filename = $ex->getFile();
		$line = $ex->getLine();
		
		// sanity check - dont get stuck forever looping
		if ($currentDepth > 10) {
			// looped too much - just return a basic message we know (or are pretty sure) will build
			return @json_encode([
			    'timestamp' => time(),
			    'code' => $ex->getCode(),
			    'message' => $ex->getMessage(),
			    'line' => $line,
			    'file' => $filename
			]);
		}
		
		// if we have a passed-in trace, dont bother cleaning it
		if ($passedInTrace) {
			$trace = $passedInTrace;
		}
		else {
			// trace array not specified - create one from the passed exception
			$trace = $ex->getTrace();
			
			// loop array looking for things to remove
			foreach ($trace as $frameNumber => $frame) {
				if (isset($frame['function']) && in_array($frame['function'], ['skobaErrorHandler', 'skobaShutdownHandler'])) {
					// remove the error handler frame, in case we've come here
					// from the error handler
					unset($trace[$frameNumber]);
					continue;
				}
				if (isset($frame['line']) && isset($frame['file']) && $frame['line'] == $line && $frame['file'] == $filename) {
					// if we came here from the error handler we will have another
					// frame for the original call. no sense having duplicates
					unset($trace[$frameNumber]);
					continue;
				}
			}
			
			// reindex keys
			$trace = array_values($trace);
		}
		
		// attempt to build message
		// if this fails, or is too long, 
		// we will start stripping things
		$params = [
		    'timestamp' => time(),
		    'code' => $ex->getCode(),
		    'message' => $ex->getMessage(),
		    'line' => $line,
		    'file' => $filename,
		    'trace' => $trace
		];
		if ($includeExtraParams) {
			$params['extra'] = [
			    'SERVER' => $_SERVER,
			    'GET' => $_GET,
			    'POST' => $_POST,
			    'COOKIE' => $_COOKIE,
			    'FILES' => $_FILES,
			    'ENV' => $_ENV,
			    'argv' => $argv
			];
		}
		
		$message = @json_encode($params);
		
		// if message failed to build,  we might have a smarty
		// frame that has recursion
		if (!$message) {
			// if we have a stack trace, lets try removing some stuff
			// only run this if we're not recursing
			if ($trace && !$passedInTrace) {
				foreach ($trace as &$frame) {
					foreach ($frame['args'] as &$arg) {
						if ($arg instanceof Smarty) {
							// found a smarty object, lets remove it
							$arg = '[Smarty object removed by Skoba framework]';
						}
					}
					unset($arg);
				}
				unset($frame);
				
				// rebuild message with new trace
				return self::BuildMessageFromException($ex, $trace, ++$currentDepth);
			}
			else {
				// dunno - just return a basic message we know (or are pretty sure) will build
				return @json_encode([
				    'timestamp' => time(),
				    'code' => $ex->getCode(),
				    'message' => $ex->getMessage(),
				    'line' => $line,
				    'file' => $filename
				]);
			}
		}
		else {
			// message was built successfully
			// check length - if message is over 256kB we need to truncate
			if (strlen($message) > (256 * 1024)) {				
				if ($trace) {
					array_shift($trace); // remove last element on array - not sure if it would be better to remove first or last

					// try with shorter trace
					return self::BuildMessageFromException($ex, $trace, ++$currentDepth);
				}
				elseif ($includeExtraParams) {
					// no trace to chop off - try without "extra" parameters
					return self::BuildMessageFromException($ex, $trace, ++$currentDepth, false);
				}
				else {
					// dunno - just return a basic message we know (or are pretty sure) will build
					return @json_encode([
					    'timestamp' => time(),
					    'code' => $ex->getCode(),
					    'message' => $ex->getMessage(),
					    'line' => $line,
					    'file' => $filename
					]);
				}
			}
		}
		
		return $message;
	}
}

class Errors extends ErrorHandler {
	/**
	 * Mandrill API key for sending email alerts
	 */
	const MANDRILL_KEY = 'oCc7jX7xlHGoXgiVD6QXVg';
	
	/**
	 * Logs an error to our database
	 * 
	 * @param object $message
	 */
	public static function LogIncident($message) {
		$db = new Db();
		$q = new Query();
		$q->addTable('incidents');
		$q->addFields([
		    'code' => $message->code,
		    'file' => $message->file,
		    'line' => $message->line,
		    'message' => $message->message,
		    'trace' => @json_encode($message->trace),
		    'extra' => @json_encode($message->extra),
		    'timestamp' => $message->timestamp
		]);
		return $db->insert($q);
	}
	
	/**
	 * Sends an email to one or more email addresses regarding an error that occurred
	 * 
	 * @param array $emailAddresses 
	 * @param object $message
	 */
	public static function EmailErrorReport(array $emailAddresses, $message) {
		require_once('SwiftMailer/swift_required.php'); // sending mail requires swift

		$transport = Swift_SmtpTransport::newInstance('smtp.mandrillapp.com', 465, 'ssl')
		->setUsername('parkinglotlust@gmail.com')
		->setPassword('25750b44-b9a5-489f-9d6c-c74869aa9fda');
		
		$mailer = Swift_Mailer::newInstance($transport);

		$mailTemplate = new Template();
		$mailTemplate->assign('message', $message);
		
		$message = Swift_Message::newInstance()
		->setSubject('"'.basename($message->file).'" on line '.$message->line)
		->setFrom(array('errors@parkinglotlust.com' => 'Error Notifications'))
		->setBody($mailTemplate->fetch('emails/error_notification_text.tpl'));
		
		foreach ($emailAddresses as $email) {
			$message->setTo([$email => $email]);
		}
		
		$mailer->send($message);
	}
	
	/**
	 * Fetches an array of error messages currently in the queue
	 * 
	 * Processed error messages should be deleted using DeleteError
	 * 
	 * @return array An array of error objects, keyed by ReceiptHandle
	 * @throws Exception If response or message body fail to parse
	 */
	public static function CheckForErrors() {
		$client = self::LoadSQSClient();
		$result = $client->receiveMessage([
		    'QueueUrl' => SQS_QUEUE_URL,
		    'VisibilityTimeout' => 5,
		    'MaxNumberOfMessages' => 10 // hide messages received in this request from future requests for 10 seconds (prevents us from seeing the same messages over and over)
		]);
		$errorMessages = [];
		if (isset($result['Messages'])) {
			foreach ($result['Messages'] as $message) {
				if (!($json = json_decode($message['Body'])) && !($json = json_decode(rawurldecode($message['Body'])))) {
					throw new Exception('Failed to decode message body: '.print_r($message['Body'], true));
				}
				$json->message = urldecode($json->message);
				$json->file = urldecode($json->file);
				$errorMessages[$message['ReceiptHandle']] = $json;
			}
		}
		return $errorMessages;
	}
	
	public static function DeleteError($receiptHandle) {
		$client = self::LoadSQSClient();
		$client->deleteMessage([
		    'QueueUrl' => SQS_QUEUE_URL,
		    'ReceiptHandle' => $receiptHandle
		]);
	}
	
	public static function ErrorCodeToString($code) {
		switch ($code) {
			case E_ERROR: 
				return 'Fatal Error';
			case E_WARNING: 
				return 'Warning';
			case E_PARSE:
				return 'Parse Error';
			case E_NOTICE:
				return 'Notice';
			case E_CORE_ERROR: 
				return 'Core Error';
			case E_CORE_WARNING: 
				return 'Core Warning';
			case E_COMPILE_ERROR: 
				return 'Compile Error';
			case E_COMPILE_WARNING:
				return 'Compile Warning';
			case E_USER_ERROR:
				return 'User Error';
			case E_USER_WARNING: 
				return 'User Warning';
			case E_USER_NOTICE: 
				return 'User Notice';
			case E_STRICT: 
				return 'Strict Standards Notice';
			case E_RECOVERABLE_ERROR: 
				return 'Recoverable Error';
			case E_DEPRECATED:
				return 'Deprecated';
			case E_USER_DEPRECATED:
				return 'User Deprecated';
			case 1234:
				return 'Javascript';
			case 0:
				return 'Exception';
		}
		return $code;
	}
	
	protected static $colors = [
	    'red' => [
		'bg' => '#ff0000',
		'text' => '#ffffff'
	    ],
	    'orange' => [
		'bg' => '#ff8000',
		'text' => '#000000'
	    ],
	    'yellow' => [
		'bg' => '#ffff00',
		'text' => '#000000'
	    ]
	];
	
	public static function ErrorCodeToColor($code) {
		switch ($code) {
			case E_ERROR: 
			case E_USER_ERROR:
			case E_CORE_ERROR: 
			case E_COMPILE_ERROR: 
			case E_RECOVERABLE_ERROR: 
			case E_PARSE:
				return self::$colors['red'];
			case E_WARNING: 
			case E_USER_WARNING: 
			case E_CORE_WARNING: 
			case E_COMPILE_WARNING:
			case E_DEPRECATED:
			case E_USER_DEPRECATED:
				return self::$colors['orange'];
			case E_NOTICE:
			case E_USER_NOTICE: 
			case E_STRICT: 
				return self::$colors['yellow'];
		}
		return self::$colors['red'];		
	}
}



/**
 * Skoba PHP Framework
 * 
 * Query Class
 * 
 * @author Jordan Skoblenick <parkinglotlust@gmail.com> April 28, 2013
 */

class Query {

	/**
	 * An associative array of 'table_name' => 'table_name' 
	 * (to maintain a unique array).
	 * 
	 * @var array
	 */
	protected $tables = [];

	/**
	 * An associative array of 'field name' => 'array (operator/value/field)'.
	 * 
	 * @var array 
	 */
	protected $wheres = [];

	/**
	 * An associative array of 'field name' => 'field name'.
	 * 
	 * @var array 
	 */
	protected $groups = [];

	/**
	 * An array of 'raw where statements'.
	 * 
	 * @var array 
	 */
	protected $rawWheres = [];

	/**
	 * An array of 'raw fields'.
	 * 
	 * @var array 
	 */
	protected $rawFields = [];

	/**
	 * An associative array of 'field name' => 'array (operator/value/field)'.
	 * 
	 * @var array 
	 */
	protected $havings = [];

	/**
	 * An associative array of 'column name' => 'column name' 
	 * for SELECT queries.
	 * 
	 * @var array
	 */
	protected $columns = [];

	/**
	 * An associative array of 'field name' => 'field name' 
	 * for INSERT/UPDATE queries.
	 * 
	 * @var array
	 */
	protected $fields = [];

	/**
	 * An associative array of 'table name' => 'on query'.
	 * 
	 * @var array
	 */
	protected $innerJoins = [];

	/**
	 * An associative array of 'table name' => 'on query'.
	 * 
	 * @var array
	 */
	protected $leftJoins = [];

	/**
	 * An associative array of 'column name' => 'asc/desc'.
	 * 
	 * @var array
	 */
	protected $orderBys = [];

	/**
	 * A stack of AND/OR arrays for use with beginAnd/beginOr
	 * 
	 * By default WHEREs are AND'd together
	 * 
	 * @var array 
	 */
	protected $and_or_stack = [
	    [
		'type' => 'AND',
		'cards' => []
	    ]
	];

	/**
	 * The LIMIT (max number of rows) for this query
	 * 
	 * @var int
	 */
	protected $limit = 0;

	/**
	 * The OFFSET of the LIMIT portion for this query
	 * 
	 * @var int
	 */
	protected $offset = 0;

	/**
	 * An array of valid operators for WHERE clauses.
	 * 
	 * @var array
	 */
	protected $validOperators = ['=', '!=', 'IN', 'NOT IN', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE'];

	/**
	 * A raw SQL statement to execute
	 * 
	 * @param string $sql
	 */	
	protected $sql = null;

	public function __construct($sql = null) {
		$this->sql = $sql;
	}

	/**
	 * Marks the start of a series of WHERE statements which should be OR'd together
	 * 
	 * Ex.
	 * 
	 * $q->beginOr();
	 *   $q->beginAnd();
	 *     $q->addWhere('field1','value1');
	 *     $q->addWhere('field2','value2');
	 *   $q->endAnd();
	 *   $q->beginAnd();
	 *     $q->addWhere('field3','value3');
	 *     $q->addWhere('field4','value4');
	 *   $q->endAnd();
	 * $q->endOr();
	 * 
	 */
	public function beginOr() {
		$this->and_or_stack[] = [
		    'type' => 'OR', 
		    'cards' => []
		];
	}

	/**
	 * Marks the start of a series of WHERE statements which should be AND'd together.
	 * Only necessary when using beginOr() as WHERE statements are AND'd by default
	 * 
	 * Ex.
	 * 
	 * $q->beginOr();
	 *   $q->beginAnd();
	 *     $q->addWhere('field1','value1');
	 *     $q->addWhere('field2','value2');
	 *   $q->endAnd();
	 *   $q->beginAnd();
	 *     $q->addWhere('field3','value3');
	 *     $q->addWhere('field4','value4');
	 *   $q->endAnd();
	 * $q->endOr();
	 * 
	 */
	public function beginAnd() {
		$this->and_or_stack[] = [
		    'type' => 'AND', 
		    'cards' => []
		];
	}

	/**
	 * Marks the end of a series of WHERE statements which should be OR'd together
	 */
	public function endOr() {
		return $this->endAndOr('OR');
	}

	/**
	 * Marks the end of a series of WHERE statements which should be AND'd together
	 */
	public function endAnd() {
		return $this->endAndOr('AND');
	}

	/**
	 * Internal helper for ending AND / OR
	 * 
	 * @param string $type AND or OR
	 * @throws Exception
	 */
	private function endAndOr($type) {
		// find the most recent stack item
		$max = count($this->and_or_stack) - 1;
		$mostRecent = $this->and_or_stack[$max];

		// ensure we are closing the right one
		if ($mostRecent['type'] != $type) {
			throw new Exception('endOr/endAnd called in the wrong order');
		}

		// remove current item from the stack
		unset($this->and_or_stack[$max]);
		// rebuild keys
		$this->and_or_stack = array_values($this->and_or_stack);

		// place it in the previous stack of cards
		if ($mostRecent['cards']) {
			$this->and_or_stack[$max - 1]['cards'][] = $mostRecent;
		}
	}

	/**
	 * Build the cards into a string of WHERE ...
	 * 
	 * @param string $type AND/OR
	 * @param array $cards An array of AND/ORs
	 * @param boolean $braces Add braces to the string (default false, only add with sub-AND/OR)
	 * @return string
	 */
	private function compileAndOr($type, $cards, $braces = false) {
		$elements = array();
		foreach ($cards as $card) {
			if (is_array($card)) {
				$elements[] = $this->compileAndOr($card['type'], $card['cards'], true);
			}
			else {
				$elements[] = $card;
			}
		}
		// merge those with the type
		$string = implode(" {$type} ", $elements);

		if ($braces) {
			$string = "({$string})";
		}

		return $string;
	}

	/**
	 * Compile the AND/OR stack as a WHERE query
	 * 
	 * @return string
	 * @throws Exception
	 */
	private function buildWhere() {
		if (count($this->and_or_stack) > 1) {
			throw new Exception('Unclosed beginOr/beginAnd');
		}

		if (!$this->wheres) {
			return '';
		}

		return 'WHERE '.$this->compileAndOr($this->and_or_stack[0]['type'], $this->and_or_stack[0]['cards']);
	}

	/**
	 * Helper to add complete WHERE strings
	 * 
	 * @param string $string
	 */
	private function addToWhere($string) {
		// find the most recent item and add it to the array of cards
		$this->and_or_stack[count($this->and_or_stack) - 1]['cards'][] = $string;
		$this->wheres[] = $string;
	}

	/**
	 * Adds a single table or an array of tables to the query.
	 * 
	 * @param string $table
	 */
	public function addTable($table) {
		$this->tables[$table] = $table;
	}

	public function setTable($table) {
		$this->tables = [];
		$this->addTable($table);
	}

	public function getTables() {
		return $this->tables;
	}

	/**
	 * Adds a WHERE clause to a query.
	 * 
	 * @param string $field
	 * @param mixed $value
	 */
	public function addWhere($field, $value = null, $operator = null) {
		$sql = [];

		if ($operator !== null && !in_array($operator, $this->validOperators)) {
			throw new Exception('Invalid operator specified: '.$operator);
		}

		if (is_array($field)) {
			// assume we're passing an assoc array of $field => $value pairs
			foreach ($field as $key => $val) {
				$this->addWhere($key, $val);
			}
			return;
		}
		// dont want to accidentally trigger this if we're doing, say, $q->addWhere('field', null);
		elseif ($field && $value === null && $operator === null && func_num_args() == 1) {
			$sql[] = $field;
		}
		else {
			if ($operator === null) {
				if (is_array($value)) {
					// assume operator is 'IN' if not specified and 
					// we have an array of values
					$operator = 'IN'; 
				}	
				else {
					// assume operator is '=' if not specified
					$operator = '='; 
				}
			}

			if (is_array($value)) {
				if ($operator != 'IN') {
					throw new Exception('Unknown operator used with WHERE + array: '.$operator);
				}
				$values = $value;
				foreach ($values as &$value) {
					$value = Db::escape($value);
				}
				unset($value);
				$value = '('.implode(', ', $values).')';
			}
			else {
				if ($value === null) {
					if ($operator == '=') {
						$operator = 'IS';
					}
					elseif ($operator == '!=') {
						$operator = 'IS NOT';
					}
				}
				$value = Db::escape($value);
			}
			$sql[] = $field;
			$sql[] = $operator;
			$sql[] = $value;
		}

		$this->addToWhere(implode(' ', $sql));
	}

	public function setWhere($field, $value, $operator = null) {
		$this->clearWhere();
		$this->addWhere($field, $value, $operator);
	}

	public function getWheres() {
		return $this->wheres;
	}

	public function clearWhere() {
		// clear any open and/or statements 
		$this->and_or_stack = [
		    [
			'type' => 'AND',
			'cards' => []
		    ]
		];
		$this->wheres = [];
	}

	public function addGroup($field) {
		$this->groups[$field] = $field;
	}

	public function getGroups() {
		return $this->groups;
	}

	/**
	 * Adds a HAVING clause to a query.
	 * 
	 * @param string $field
	 * @param mixed $value
	 */
	public function addHaving($field, $value, $operator = null) {
		if ($operator !== null && !in_array($operator, $this->validOperators)) {
			throw new Exception('Invalid operator specified: '.$operator);
		}

		if ($operator === null) {
			if (is_array($value)) {
				// assume operator is 'IN' if not specified and 
				// we have an array of values
				$operator = 'IN'; 
			}	
			else {
				// assume operator is '=' if not specified
				$operator = '='; 
			}
		}

		$this->havings[] = array(
		    'field' => $field,
		    'value' => $value,
		    'operator' => $operator
		);
	}

	public function setHaving($field, $value, $operator = null) {
		$this->havings = [];
		$this->addHaving($field, $value, $operator);
	}

	public function getHaving() {
		return $this->havings;
	}

	/**
	 * Adds a column of columns for a SELECT query.
	 * 
	 * @param string $name
	 */
	public function addColumn($name) {
		$this->columns[] = $name;
	}

	/**
	 * Adds an array of columns for a SELECT query.
	 * 
	 * @param array $name
	 */
	public function addColumns(array $name) {
		foreach ($name as $n) {
			$this->columns[] = $n;
		}
	}

	/**
	 * Clears current columns and adds a column/array of
	 * columns for a SELECT query.
	 * 
	 * @param mixed $column
	 */
	public function setColumn($column) {
		$this->columns = [];
		$this->addColumn($column);
	}

	/**
	 * Clears current columns and adds an array of
	 * columns for a SELECT query.
	 * 
	 * @param array $column
	 */
	public function setColumns(array $column) {
		$this->columns = [];
		$this->addColumns($column);
	}

	/**
	 * Get an array of the current columns.
	 * 
	 * @return array
	 */
	public function getColumns() {
		return $this->columns;
	}

	/**
	 * Adds a field for an UPDATE or INSERT query.
	 * 
	 * @param string $name
	 * @param mixed $value
	 */
	public function addField($name, $value = null) {
		if (!$value && func_num_args() == 1) {
			$this->rawFields[] = $name;
		}
		else {
			$this->fields[$name] = $value;
		}
	}

	/**
	 * Adds an array of field name => field value for an UPDATE or INSERT query.
	 * 
	 * @param array $values
	 */
	public function addFields(array $values) {
		foreach ($values as $key => $value) {
			$this->fields[$key] = $value;
		}
	}

	public function setField($name, $value) {
		$this->fields = [];
		$this->addField($name, $value);
	}

	public function getFields() {
		return $this->fields;
	}

	/**
	 * Adds an INNER JOIN to a query.
	 * 
	 * @param type $table
	 * @param type $on
	 */
	public function addInnerJoin($table, $on) {
		$this->innerJoins[$table] = $on;
	}

	public function setInnerJoin($table, $on) {
		$this->innerJoins = [];
		$this->addInnerJoin($table, $on);
	}

	public function getInnerJoins() {
		return $this->innerJoins;
	}

	/**
	 * Alias for addInnerJoin
	 */
	public function addJoin($table, $on) {
		$this->addInnerJoin($table, $on);
	}

	/**
	 * Adds a LEFT JOIN to a query.
	 * 
	 * @param type $table
	 * @param type $on
	 */
	public function addLeftJoin($table, $on) {
		$this->leftJoins[$table] = $on;
	}

	public function setLeftJoin($table, $on) {
		$this->leftJoins = [];
		$this->addLeftJoin($table, $on);
	}

	public function getLeftJoins() {
		return $this->leftJoins;
	}

	/**
	 * An alias of setLimit (for consistency).
	 * 
	 * @param int $limit
	 * @param int $offset
	 */
	public function addLimit($limit, $offset = null) {
		$this->setLimit($limit, $offset);
	}

	/**
	 * Sets the LIMIT of the query.
	 * 
	 * @param int $limit
	 * @param int $offset
	 */
	public function setLimit($limit, $offset = null) {
		if ((string)(int)$limit !== (string)$limit) { 
			throw new Exception('Limit value is not an integer: '.$limit);
		}

		$this->limit = $limit;

		if ($offset === null) {
			$this->offset = 0;
		}
		else {
			$this->offset = $offset;
		}
	}

	/**
	 * Gets the current limit and offset of the query.
	 * 
	 * @return array An array of (0 => limit, 1 => offset)
	 */
	public function getLimit() {
		return array(
		    $this->limit,
		    $this->offset
		);
	}

	/**
	 * Adds a column to the ORDER BY of the query.
	 * 
	 * @param string $column
	 * @param string $direction
	 */
	public function addOrder($column, $direction = 'asc') {
		$direction = strtolower($direction);
		if (!in_array($direction, array('asc', 'desc'))) {
			throw new Exception('Invalid ORDER BY direction: '.$direction);
		}
		$this->orderBys[$column] = $direction;
	}

	/**
	 * Sets the ORDER BY of the query.
	 * 
	 * @param string $column
	 * @param string $direction 'asc' or 'desc'
	 */
	public function setOrder($column, $direction = 'asc') {
		$this->orderBys = [];
		$this->addOrder($column, $direction);
	}

	/**
	 * Gets the current order and offset of the query.
	 * 
	 * @return array An array of 'column name' => 'asc/desc'
	 */
	public function getOrder() {
		return $this->orderBys;
	}

	/**
	 * Builds a SELECT query for the current Query
	 * 
	 * @return string A SELECT query for the current Query
	 */
	public function getSelect() {
		if ($this->sql) {
			return $this->sql;
		}
		$sql[] = 'SELECT';
		$sql[] = $this->buildColumns();
		$sql[] = 'FROM';
		$sql[] = $this->buildTables();
		$sql[] = $this->buildJoins();
		$sql[] = $this->buildWhere();
		$sql[] = $this->buildGroup();
		$sql[] = $this->buildHaving();
		$sql[] = $this->buildOrderBy();
		$sql[] = $this->buildLimit();
		return trim(implode(' ', $sql));
	}

	/**
	 * Builds an INSERT query for the current Query
	 * 
	 * @param bool Creates an INSERT IGNORE if true
	 * @return string An INSERT query for the current Query
	 */
	public function getInsert($ignore = false) {
		$sql[] = 'INSERT';
		if ($ignore) {
			$sql[] = 'IGNORE';
		}
		$sql[] = 'INTO';
		$sql[] = $this->buildTables();
		$sql[] = '('.$this->buildFieldNames().')';
		$sql[] = 'VALUES';
		$sql[] = '('.$this->buildFieldValues().')';
		return trim(implode(' ', $sql));
	}

	/**
	 * Builds an UPDATE query for the current Query
	 * 
	 * @return string An UPDATE query for the current Query
	 */
	public function getUpdate() {
		$sql[] = 'UPDATE';
		$sql[] = $this->buildTables();
		$sql[] = 'SET';
		$sql[] = $this->buildFieldNameValuePairs();
		$sql[] = $this->buildWhere();
		$sql[] = $this->buildGroup();
		$sql[] = $this->buildHaving();
		$sql[] = $this->buildOrderBy();
		$sql[] = $this->buildLimit();
		return trim(implode(' ', $sql));
	}

	/**
	 * Builds a DELETE query for the current Query
	 * 
	 * @return string A DELETE query for the current Query
	 */
	public function getDelete() {
		$sql[] = 'DELETE FROM';
		$sql[] = $this->buildTables();
		$sql[] = $this->buildWhere();
		$sql[] = $this->buildLimit();
		return trim(implode(' ', $sql));
	}

	/**
	 * Builds a COUNT(*) query for the current Query
	 * 
	 * @return string A COUNT(*) query for the current Query
	 */
	public function getCount() {
		$sql[] = 'SELECT COUNT(*) FROM';
		$sql[] = $this->buildTables();
		$sql[] = $this->buildJoins();
		$sql[] = $this->buildWhere();
		$sql[] = $this->buildGroup();
		$sql[] = $this->buildHaving();
		$sql[] = $this->buildOrderBy();
		$sql[] = $this->buildLimit();
		return trim(implode(' ', $sql));
	}

	protected function buildColumns() {
		if (count($this->columns)) {
			return implode(', ', $this->columns);
		}
		return '*';
	}

	/**
	 * Builds a string of "field name = 'field value'" pairs for 
	 * an UPDATE statement
	 * 
	 * @return string "field name = 'field value'[, ...]"
	 */
	protected function buildFieldNameValuePairs() {
		if (!count($this->fields) && !count($this->rawFields)) {
			throw new Exception('Must specify at least one field for this query');
		}
		$fields = [];
		if ($this->fields) {
			$fieldNames = array_keys($this->fields);
			$fieldValues = array_values($this->fields);
			for ($i = 0; $i < count($this->fields); $i++) {
				$fields[] = $fieldNames[$i].'='.Db::escape($fieldValues[$i]);
			}
		}
		if ($this->rawFields) {
			foreach ($this->rawFields as $f) {
				$fields[] = $f;
			}
		}
		return implode(', ', $fields);
	}

	protected function buildFieldNames() {
		if (!count($this->fields)) {
			throw new Exception('Must specify at least one field for this query');
		}
		// escape field with backslash
		$fields = array_keys($this->fields);
		foreach ($fields as &$field) {
			if (!preg_match('/^`.*`$/', $field)) {
				$field = "`{$field}`";
			}
		}
		unset($field);
		return implode(', ', $fields);
	}

	protected function buildFieldValues() {
		if (!count($this->fields)) {
			throw new Exception('Must specify at least one field for this query');
		}
		$fields = array_values($this->fields);
		foreach ($fields as &$field) {
			$field = Db::escape($field);
		}
		unset($field);
		return implode(', ', $fields);
	}

	protected function buildTables() {
		if (!count($this->tables)) {
			throw new Exception('You must specify at least one table');
		}
		$tables = $this->tables;
		if (defined('SKOBA_DB_TABLE_PREFIX')) {
			$tables = array_map(function($table) { 
				return SKOBA_DB_TABLE_PREFIX.$table;
			}, $tables);
		}
		return implode(', ', $tables);
	}

	protected function buildJoins() {
		$sql = [];
		foreach ($this->leftJoins as $table => $on) {
			$sql[] = 'LEFT JOIN '.$table.' ON ('.$on.')';
		}
		foreach ($this->innerJoins as $table => $on) {
			$sql[] = 'INNER JOIN '.$table.' ON ('.$on.')';
		}

		return implode(' ', $sql);
	}

	protected function buildGroup() {
		if (!$this->groups) {
			return '';
		}
		return 'GROUP BY '.implode(',', $this->groups);
	}

	protected function buildHaving() {
		$sql = [];
		if ($having = count($this->havings)) {
			$sql[] = 'HAVING';
			$i = 0;
			foreach ($this->havings as $having) {
				$i++;
				if (is_array($having['value'])) {
					if ($having['operator'] != 'IN') {
						throw new Exception('Unknown operator used with HAVING + array: '.$having['operator']);
					}
					foreach ($having['value'] as &$value) {
						$value = Db::escape($value);
					}
					unset($value);
					$value = '('.implode(', ', $having['value']).')';
				}
				else {
					if ($having['value'] === null) {
						if ($having['operator'] == '=') {
							$having['operator'] = 'IS';
						}
						elseif ($having['operator'] == '!=') {
							$having['operator'] = 'IS NOT';
						}
					}
					$value = Db::escape($having['value']);
				}
				$sql[] = $having['field'];
				$sql[] = $having['operator'];
				$sql[] = $value;
				if ($i < $having) {
					$sql[] = 'AND';
				}
			}
		}
		return implode(' ', $sql);
	}

	protected function buildLimit() {
		$sql = [];
		if ($this->limit) {
			$sql[] = 'LIMIT';
			if ($this->offset) {
				$sql[] = $this->offset;
				$sql[] = ',';
				$sql[] = $this->limit;
			}
			else {
				$sql[] = $this->limit;
			}
		}
		return implode(' ', $sql);
	}

	protected function buildOrderBy() {
		$sql = [];
		if ($orderBys = count($this->orderBys)) {
			$sql[] = 'ORDER BY';
			foreach ($this->orderBys as $column => $direction) {
				$order[] = $column.' '.strtoupper($direction);
			}
			$sql[] = implode(', ', $order);
		}
		return implode(' ', $sql);
	}		
}

/**
 * Skoba PHP Framework
 * 
 * Request Class
 * 
 * Provides GET/POST/etc functionality
 * 
 * @author Jordan Skoblenick <parkinglotlust@gmail.com> May 9, 2013
 */
class Request {
	public static $CURL_OPTS = array(
	    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.11 (KHTML, like Gecko) Chrome/23.0.1271.64 Safari/537.11'
	);

	protected static $DEFAULT_CURL_OPTIONS = array(
	    CURLOPT_RETURNTRANSFER => true,
	    CURLOPT_SSL_VERIFYPEER => false,
	    CURLOPT_SSL_VERIFYHOST => false
	);

	/**
	 * GET a $url
	 * 
	 * @param string $url url to get
	 * @param array $opts An optional array of options for this specific request
	 * @return array containing response, info and error
	 */
	static function get($url, array $opts = []) {
		$ch = curl_init($url);

		curl_setopt_array($ch, self::$DEFAULT_CURL_OPTIONS);
		if (self::$CURL_OPTS) {
			curl_setopt_array($ch, self::$CURL_OPTS);
		}
		if ($opts) {
			curl_setopt_array($ch, $opts);
		}

		$response = curl_exec($ch);
		$info = curl_getinfo($ch);
		$error = curl_error($ch);

		curl_close($ch);

		return array(
		    'response' => $response,
		    'info' => $info,
		    'error' => $error
		);
	}

	/**
	 * POST $data to a $url
	 * 
	 * @param string $url url to POST to
	 * @param array $data data to POST
	 * @param array $opts An optional array of options for this specific request
	 * @return array containing response, info and error
	 */
	static function post($url, array $data, array $opts = []) {
		$ch = curl_init($url);

		curl_setopt_array($ch, self::$DEFAULT_CURL_OPTIONS);
		if (self::$CURL_OPTS) {
			curl_setopt_array($ch, self::$CURL_OPTS);
		}
		if ($opts) {
			curl_setopt_array($ch, $opts);
		}
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data, '', '&'));

		$response = curl_exec($ch);
		$info = curl_getinfo($ch);
		$error = curl_error($ch);

		curl_close($ch);

		return array(
		    'response' => $response,
		    'info' => $info,
		    'error' => $error
		);
	}
}

/**
 * Skoba PHP Framework
 * 
 * Template Class
 * 
 * Adds the following modifiers:
 * - {{$var}} - auto html-escaped
 * - {%var} - auto isset() check (can be combined with {{}})
 * 
 * @author Jordan Skoblenick <parkinglotlust@gmail.com> April 28, 2013
 * @requires Smarty3 
 */
class Template extends Smarty {
	/**
	 * The page title
	 * @var string 
	 */
	public $title = null;
	/**
	 * A wrapper template
	 * @var string 
	 */
	public $wrapper = null;
	/**
	 * An array of css files to load
	 * @var array 
	 */
	protected $css = array();
	/**
	 * An array of js files to load
	 * @var array 
	 */
	protected $js = array();

	public function __construct() {
		parent::__construct();

		$this->registerFilter('pre', array($this, 'prefilter_percentIsset'));
		$this->registerFilter('pre', array($this, 'prefilter_doubleCurlies'));

		if (!defined('TEMPLATES_DIR'))   throw new Exception('You must define TEMPLATES_DIR to use the Template class'); 
		if (!defined('TEMPLATES_C_DIR')) throw new Exception('You must define TEMPLATES_C_DIR to use the Template class'); 
		if (!defined('CACHE_DIR')) throw new Exception('You must define CACHE_DIR to use the Template class'); 

		$this->setTemplateDir(TEMPLATES_DIR); 
		$this->setCompileDir(TEMPLATES_C_DIR); 
		$this->setCacheDir(CACHE_DIR);

		// if we're in development mode, always recompile templates
		if (PROJECT_STATUS == 'development') {
			$this->force_compile = true;
			$this->caching = 1;
			$this->compile_check = true;
		}
	}

	/**
	 * Adds a JS file to the internal array, to be 
	 * automatically added to a template
	 * 
	 * @param string $file
	 */
	public function js($file) {
		$this->js[] = $file;
	}

	/**
	 * Adds a CSS file to the internal array, to be 
	 * automatically added to a template
	 * 
	 * @param string $file
	 */
	public function css($file) {
		$this->css[] = $file;
	}

	/**
	 * Converts {{$var}} into {$var|escape:'html'}
	 * 
	 * @param string $code 
	 */
	protected function prefilter_doubleCurlies($code) {
		return preg_replace('/\{\{([\$%][^\}]+)\}\}/', '{$1|escape:"html"}', $code);
	}

	/**
	 * Converts {%var} into {if isset($var}}{$var}{/if}. Also works
	 * with double curlies: {{%var}} -> {if isset($var)}{{$var}}{/if}
	 * 
	 * @param string $code 
	 */
	protected function prefilter_percentIsset($code) {
		return preg_replace('/(\{{1,2})%([^\}]+)(\}{1,2})/', '{if isset(\$$2)}$1\$$2$3{/if}', $code);
	}

	/**
	 * Fetches a template. Implicitly called by $this->display()
	 * 
	 * @global array $errors
	 * @param string $template Template to display. ".tpl" will be added if omitted
	 * @return string Template contents
	 */
	public function fetch($template = null, $cache_id = null, $compile_id = null, $parent = null, $display = false, $merge_tpl_vars = true, $no_output_filter = false) {
		global $errors;
		//$this->compile_id = md5($id);

		// auto add .tpl extension if not specified
		if ($template && pathinfo($template, PATHINFO_EXTENSION) != 'tpl') {
			$template .= '.tpl';
		}

		// auto assign errors array
		if (is_array($errors)) {
			$this->assign('errors', $errors);
		}			
		$this->assign('_title', $this->title);
		$this->assign('_css', $this->css);
		$this->assign('_js', $this->js);
		$this->assign('_content', $template);

		if ($this->wrapper) {
			return parent::fetch($this->wrapper, $cache_id, $compile_id, $parent, $display, $merge_tpl_vars, $no_output_filter);
		} else {
			return parent::fetch($template, $cache_id, $compile_id, $parent, $display, $merge_tpl_vars, $no_output_filter);
		}
	}
}

/**
 * Basic user class
 * 
 * @author Jordan Skoblenick <parkinglotlust@gmail.com> 2013-06-19
 */
	
class user extends DbTable {
	protected $tableName = 'users';
	
	protected $userIdField = 'user_id';
	protected $usernameField = 'username';
	protected $passwordField = 'password';
	
	public function exists($email) {
		$q = new Query();
		$q->addTable($this->tableName);
		$q->addWhere($this->usernameField, $email);
		if ($user = $this->db->getRow($q)) {
			return $user['user_id'];
		}
		return false;
	}
	
	public function login($email, $password) {
		$q = new Query();
		$q->addTable($this->tableName);
		$q->addWhere($this->usernameField, $email);
		if (!$user = $this->db->getRow($q)) {
			throw new UserException('Email address not found');
		}

		if (isset($user['salt']) && $user['salt']) {
			// user might have an old md5 password - 
			// check and upgrade it to the new encoding scheme
			if (md5(md5($password).$user['salt']) == $user['password']) {
				$this->load($user['user_id']);
				$this->changePassword($password);
				return true;
			}
		}
		if (crypt($password, $user[$this->passwordField]) == $user[$this->passwordField]) {
			$this->load($user['user_id']);
			return true;
		}
		throw new UserException('Incorrect password');
	}
	
	public function changePassword($password) {
		do {
			$strong = false;
			$salt = openssl_random_pseudo_bytes(15, $strong);
		} while (!$strong);
		$this->salt = null;
		$this->password = crypt($password, '$2a$07$'.base64_encode($salt));
		$this->save();
	}
	
	protected function beforeCreate() {
		$this->beforeSave();
	}
	protected function afterCreate() {
		$this->afterSave();
	}
	protected function beforeSave() {
		
	}
	protected function afterSave() {
		
	}
}




/**
 * A type of exception we *can* show to the user
 */
class UserException extends Exception { }


/**
 * Redirect to a given URL. Prevents forgotten dies
 * 
 * @param string $url
 */
function redirect($url) {
	header('Location: '.$url);
	die;
}

/**
 * Skoba PHP Framework
 * 
 * Validation functions. Don't really want to make it a static class
 * so this will just be a collection of useful Validation functions
 * 
 * @author Jordan Skoblenick <parkinglotlust@gmail.com> May 4, 2013
 */

function Validate($value, $type = 'str', $test1 = null, $test2 = null) {
	try {
		return ValidateThrow($value, $type, $test1, $test2);
	}
	catch (Exception $ex) {
		return null;
	}
}

function ValidateArrayKey($key, $array, $type = 'str', $test1 = null, $test2 = null) {
	if (!is_array($array)) return null;
	if (!array_key_exists($key, $array)) return null;
	return Validate($array[$key], $type, $test1, $test2);
}

function GET($key, $type = 'str', $test1 = null) {
	if (!isset($_GET[$key])) return null;
	return Validate($_GET[$key], $type, $test1);
}

function POST($key, $type = 'str', $test1 = null) {
	if (!isset($_POST[$key])) return null;
	return Validate($_POST[$key], $type, $test1);
}

function SERVER($key, $type = 'str', $test1 = null) {
	if (!isset($_SERVER[$key])) return null;
	return Validate($_SERVER[$key], $type, $test1);
}

function SESSION($key, $type = 'str', $test1 = null) {
	if (!isset($_SESSION[$key])) return null;
	return Validate($_SESSION[$key], $type, $test1);
}

function ValidateThrow($value, $type = 'str', $test1 = null, $test2 = null) {
	if (preg_match('/^arr(ay)?:/', $type)) {
		$types = explode(":", $type, 2);
		$type = $types[1];
		if (!is_array($value)) {
			$value = array($value);
		}
		foreach ($value as $k => $v) {
			$value[$k] = Validate($v, $type, $test1, $test2);
		}
		return $value;
	}
	switch ($type) {
		case "int":
		case "integer":
			if (!is_numeric($value)) {
				throw new Exception('Integer: value is not numeric');
			}
			if (intval($value) != $value) {
				throw new Exception('Integer: value is a decimal');
			}
			if (is_array($test1) && !in_array($value, $test1)) {
				throw new Exception('Integer: value is not in list of options');
			}
			if (!is_array($test1) && !is_null($test1) && $value < $test1) {
				throw new Exception('Integer: value is less than minimum');
			}
			if (!is_null($test2) && $value > $test2) {
				throw new Exception('Integer: value is greater than maximum');
			}
			return @intval($value);
		case "dec":
		case "decimal":
		case "float":
			if (!is_numeric($value)) {
				throw new Exception('Float: value is not numeric');
			}
			if (floatval($value) != $value) {
				throw new Exception('Float: value is not a float');
			}
			if (is_array($test1) && !in_array($value, $test1)) {
				throw new Exception('Float: value is not in list of options');
			}
			if (!is_array($test1) && !is_null($test1) && $value < $test1) {
				throw new Exception('Float: value is less than minimum');
			}
			if (!is_null($test2) && $value > $test2) {
				throw new Exception('Float: value is greater than maximum');
			}
			return @floatval($value);
		case "str":
		case "string":
			if (is_resource($value) || is_object($value) || is_array($value)) {
				throw new Exception('String: value cannot be converted to a string');
			}
			if (is_bool($value)) {
				$value = ($value ? 'true' : 'false');
			}
			$value = @strval($value);
			if ($value === false) {
				$value = '';
			}
			if (is_array($test1) && !in_array($value, $test1)) {
				throw new Exception('String: value is not in list of options');
			}
			if (is_string($test1) && !preg_match($test1, $value)) {
				throw new Exception('String: value does not match expression');
			}
			if (is_int($test1) && strlen($value) < $test1) {
				throw new Exception('String: value is less than minimum length');
			}
			if (is_int($test2) && strlen($value) > $test2) {
				throw new Exception('String: value is greater than maximum length');
			}
			return $value;
		case "bool":
		case "boolean":
			if (is_bool($value)) {
				return $value;
			} elseif (is_object($value) || is_array($value)) {
				throw new Exception('Boolean: type cannot be converted to a boolean');
			}
			$value2 = strtoupper($value);
			if ($value2 === "1" || $value2 === 1 || $value2 == "ON" || $value2 === "TRUE" || $value2 === "T" || $value2 === "YES" || $value2 === "Y") {
				return true;
			}
			if ($value2 === "0" || $value2 === 0 || $value2 == "OFF" || $value2 === "FALSE" || $value2 === "F" || $value2 === "NO" || $value2 === "N") {
				return false;
			}
			throw new Exception('Boolean: value cannot be converted to a boolean');
		case "email":
			if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
				throw new Exception('Email: value is not a valid email format');
			}
			return $value;
	}
	return($value);
}

