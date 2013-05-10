<?php
	require_once('Smarty/Smarty.class.php');
	/**
	 * Skoba PHP Framework
	 * 
	 * @author Jordan Skoblenick <parkinglotlust@gmail.com> April 28, 2013
	 */
	
	// @todo INI file or something
	define('DB_USERNAME', 'root');
	define('DB_PASSWORD', '');
	define('DB_SERVER', 'localhost');
	define('DB_DATABASE', 'roadclosures');
	
	define('PROJECT_STATUS', 'development'); // flag to switch between development and live

	define('TEMPLATES_DIR', __DIR__.'/templates/');
	define('TEMPLATES_C_DIR', __DIR__.'/templates_c/');
	
	
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
		
		public function __construct() {
			// credential check
			if (!defined('DB_USERNAME')) Error::throwError('DB_USERNAME must be defined before instantiating a Db'); 
			if (!defined('DB_PASSWORD')) Error::throwError('DB_PASSWORD must be defined before instantiating a Db'); 
			if (!defined('DB_SERVER'))   Error::throwError('DB_SERVER must be defined before instantiating a Db'); 
			if (!defined('DB_DATABASE')) Error::throwError('DB_DATABASE must be defined before instantiating a Db'); 
			
			// sanity check
			if (!extension_loaded('mysqli')) Error::throwError('You must enable the "mysqli" extension to use the Db class');
			
			// all good, connect
			$this->connect();
		}

		/**
		 * Internal database connect function.
		 */
		protected function connect() {
			if (!$this->connected) {
				// see if the current connection is cached
				$this->connectionMD5 = md5(DB_USERNAME.DB_PASSWORD.DB_SERVER.DB_DATABASE);
				if (isset(self::$_CONNECTION_CACHE[$this->connectionMD5])) {
					// use existing connection
					$this->handle = self::$_CONNECTION_CACHE[$this->connectionMD5];
					// make sure connection is still alive
					if ($this->is_connected = $this->handle->ping()) {
						return;
					}
				}
				
				// make a new connection
				$this->handle = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
				if ($this->handle->connect_error) {
					Error::throwError('Database connection failed: '.$this->handle->connect_error);
				}
				
				// cache connection
				self::$_CONNECTION_CACHE[$this->connectionMD5] = $this->handle;
			}
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
					$handle = reset(self::$_CONNECTION_CACHE); // grab first connection
					$temp = $handle->real_escape_string($value);
					
					$value = "'".$temp."'";
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
		
		/**
		 * Frees and closes a mysqli result.
		 * 
		 * @param mysqli_result $res The mysqli result to clear
		 */
		protected function clear(mysqli_result $res) {
			$res->free_result();
		}
		
		/**
		 * Internal method for running a raw query
		 * 
		 * @param string $query SQL query to execute
		 */
		protected function query($query) {
			$result = $this->handle->query((string)$query);
			
			if ($result === false) {
				Error::throwError('Query error: '.$this->handle->error);
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
			
			// limit to one row
			$q->setLimit(1);
			
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
		 * @return array An array of values, or an empty array
		 * if  there are no values to return.
		 */
		public function getCol(Query $q) {
			$rows = [];
			
			// get result
			$res = $this->query($q->getSelect());
			
			if ($res->num_rows > 0) {
				// can either grab the first column in the row,
				// or look up our columns, grab the first one,
				// and do it by assoc. either one *could* be 
				// wrong (multiple columns), so im going with 
				// the easier one
				while ($row = $res->fetch_row()) {
					$rows[] = $row[0];
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
			$q->setColumn($oldColumns);
			
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
						Error::throwError('Column '.$column.' does not exist in returned data set');
					}
					if ($secondCol !== null && !array_key_exists($secondCol, $row)) {
						Error::throwError('Column '.$secondCol.' does not exist in returned data set');
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
		 */
		public function insert(Query $q, $ignore = false) {
			$this->query($q->getInsert($ignore));
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
	 * Error Class
	 * 
	 * @author Jordan Skoblenick <parkinglotlust@gmail.com> April 28, 2013
	 */
	
	class Error {
		/**
		 * Default "throw error" function. If we're in development mode,
		 * the exception is thrown as normal. If we're in any other mode
		 * (live, beta, whatever) the user will NOT see any juicy
		 * details.
		 * 
		 * @param string $message 
		 * @param int $code 
		 * @throws Exception
		 */
		public static function throwError($message, $code = null) {
			if (PROJECT_STATUS == 'development') {
				throw new Exception($message, $code);
			}
			else {
				throw new Exception('An unknown error occurred');
			}
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
		 * An associative array of 'field name' => 'value'.
		 * 
		 * @var array 
		 */
		protected $wheres = [];
		
		/**
		 * An associative array of 'field name' => 'operator', to
		 * match $this->wheres.
		 * 
		 * @var array
		 */
		protected $operators = [];
		
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
		protected $validOperators = ['=', 'IN', '<', '<=', '>', '>='];

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
		public function addWhere($field, $value, $operator = null) {
			if ($operator !== null && !in_array($operator, $this->validOperators)) {
				Error::throwError('Invalid operator specified: '.$operator);
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
			
			$this->wheres[$field] = $value;
			$this->operators[$field] = $operator;
		}
		
		public function setWhere($field, $value, $operator = null) {
			$this->wheres = [];
			$this->operators = [];
			$this->addWhere($field, $value, $operator);
		}
		
		public function getWheres() {
			return $this->wheres;
		}
		
		public function getOperators() {
			return $this->operators;
		}

		/**
		 * Adds a column/array of columns for a SELECT query.
		 * 
		 * @param mixed $name
		 */
		public function addColumn($name) {
			if (is_array($name)) {
				foreach ($name as $n) {
					$this->columns[$n] = $n;
				}
			}
			else {
				$this->columns[$name] = $name;
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
		public function addField($name, $value) {
			$this->fields[$name] = $value;
		}
		
		/**
		 * Adds an array of field => value pairs for an 
		 * UPDATE or INSERT query.
		 * 
		 * @param array $fields
		 */
		public function addFields(array $fields) {
			foreach ($fields as $field => $value) {
				$this->addField($field, $value);
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
			if ((string)(int)$limit !== (string)$limit) Error::throwError('Limit value is not an integer: '.$limit);
			
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
				Error::throwError('Invalid ORDER BY direction: '.$direction);
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
			$sql[] = 'SELECT';
			$sql[] = $this->buildColumns();
			$sql[] = 'FROM';
			$sql[] = $this->buildTables();
			$sql[] = $this->buildWhere();
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
			$sql[] = $this->buildWhere();
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
			if (!count($this->fields)) {
				Error::throwError('Must specify at least one field for this query');
			}
			$fields = [];
			$fieldNames = array_keys($this->fields);
			$fieldValues = array_values($this->fields);
			for ($i = 0; $i < count($this->fields); $i++) {
				$fields[] = $fieldNames[$i].'='.Db::escape($fieldValues[$i]);
			}
			return implode(', ', $fields);
		}
		
		protected function buildFieldNames() {
			if (!count($this->fields)) {
				Error::throwError('Must specify at least one field for this query');
			}
			return implode(', ', array_keys($this->fields));
		}
		
		protected function buildFieldValues() {
			if (!count($this->fields)) {
				Error::throwError('Must specify at least one field for this query');
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
				Error::throwError('You must specify at least one table');
			}
			return implode(', ', $this->tables);
		}
		
		protected function buildWhere() {
			$sql = [];
			if ($wheres = count($this->wheres)) {
				$sql[] = 'WHERE';
				$i = 0;
				foreach ($this->wheres as $field => $values) {
					$i++;
					$operator = $this->operators[$field];
					if (is_array($values)) {
						if ($operator != 'IN') {
							Error::throwError('Unknown operator used with WHERE + array: '.$operator);
						}
						foreach ($values as &$value) {
							$value = Db::escape($value);
						}
						unset($value);
						$value = '('.implode(', ', $values).')';
					}
					else {
						$value = Db::escape($values);
					}
					$sql[] = $field;
					$sql[] = $operator;
					$sql[] = $value;
					if ($i < $wheres) {
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
				$sql[] = $this->limit;
				if ($this->offset) {
					$sql[] = ',';
					$sql[] = $this->offset;
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
		public $css = array();
		/**
		 * An array of js files to load
		 * @var array 
		 */
		public $js = array();
		
		public function __construct() {
			parent::__construct();
			
			$this->registerFilter('pre', array($this, 'prefilter_percentIsset'));
			$this->registerFilter('pre', array($this, 'prefilter_doubleCurlies'));
			
			if (!defined('TEMPLATES_DIR'))   throw new Exception('You must define TEMPLATES_DIR to use the Template class'); 
			if (!defined('TEMPLATES_C_DIR')) throw new Exception('You must define TEMPLATES_C_DIR to use the Template class'); 
			
			$this->setTemplateDir(TEMPLATES_DIR); 
			$this->setCompileDir(TEMPLATES_C_DIR); 
			
			// if we're in development mode, always recompile templates
			if (PROJECT_STATUS == 'development') {
				$this->force_compile = true;
				$this->caching = 1;
				$this->compile_check = true;
			}
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
			//
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
	
	function GET($key, $type = 'str') {
		if (!isset($_GET[$key])) return null;
		return Validate($_GET[$key], $type);
	}
	
	function POST($key, $type = 'str') {
		if (!isset($_POST[$key])) return null;
		return Validate($_POST[$key], $type);
	}
	
	function SERVER($key, $type = 'str') {
		if (!isset($_SERVER[$key])) return null;
		return Validate($_SERVER[$key], $type);
	}
	
	function SESSION($key, $type = 'str') {
		if (!isset($_SESSION[$key])) return null;
		return Validate($_SESSION[$key], $type);
	}
	
	function ValidateThrow($value, $type = 'str', $test1 = null, $test2 = null) {
		if (strpos($type, 'array:') === 0 || strpos($type, 'arr:') === 0) {
			$types = explode(":", $type);
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