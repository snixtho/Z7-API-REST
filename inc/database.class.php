<?php
/**
 * @copyright 2012 - 2017 Zurvan Labs 
 * @author snixtho <snixtho@gmail.com>
 */
declare(strict_types=1);
namespace Z7API\Core;

/**
 * Contains database operation functions.
 */
trait Database_Operations {
	/**
	 * Encapsulates the provided columns
	 * with the grave accent.
	 * 
	 * @param  string &$columnsStr A reference to a list of columns seperated by comma (,).
	 * @return string The modified column list (both returns and modifies parameter reference)
	 */
	private function gravifyColumnList(string &$columnsStr) {
		$columns = explode(',', $columnsStr);

		foreach ($columns as &$col)
		{
			$col = '`' . $col . '`';
		}

		$columnsStr = implode(',', $columns);

		return $columnsStr;
	}

	/**
	 * Perform a raw query against the current database. 
	 * 
	 * (NOT RECOMMENDED, USE CUSTOM PREPARED STATEMENTS INSTEAD)
	 * 
	 * @param  string $query      Query to perform
	 * @param  int    $resultmode Type of wanted result.
	 * @return mixed              The result of the query, false on failure.
	 */
	public function query(string $query, int $resultmode=MYSQLI_STORE_RESULT) {
		return $this->dbl_current['link']->query($query, $resultmode);
	}

	public function test() {
		$this->selectdb('api');
		$result = $this->simpleSelect('z7_config', '*', '`key`=? AND `value`=?', array('api.maintainance', 'one'));
		// var_dump($result->fetch_row());
	}

	/**
	 * Perform a simple select query.
	 * 
	 * @param  string     $table     Table to perform action on.
	 * @param  string     $columns   Columns to select from.
	 * @param  string     $condition Condition to base the selection on, use ? where values will be.
	 * @param  array|null $values    An array of values with as many elements as there are ?'s in $condition.
	 * @param  array      $options   Array of options, possible options are: orderby, orderdir (ASC/DESC), limit, limitstart (where to start limitation)
	 * @return mysqli_result 		 Result of the query. NULL on error.
	 */
	public function simpleSelect(string $table, string $columns='*', string $condition='', array $values=NULL, array $options=array()) {
		if ($columns != '*')
			$this->gravifyColumnList($columns);
		
		$query = 'SELECT ' . $columns . ' FROM `' . $table . '`';

		if (trim($condition) != '')
		{
			$query .= ' WHERE ' . $condition;
		}

		// insert order by
		if (isset($options['orderby']))
		{
			$query .= ' ORDER BY `' . $options['orderby'] . '`';
			if (isset($options['orderdir']))
				$query .= ' '. $options['orderdir'];
		}

		// insert limitation
		if (isset($options['limit']) && isset($options['limitstart']))
		{
			$query .= ' LIMIT ' . (string)intval($options['limitstart']) . ', ' . (string)intval($options['limit']);
		}
		else if (isset($options['limit']))
		{
			$query .= ' LIMIT ' . (string)intval($options['limit']);
		}

		// setup a prepared statement
		$stmt = $this->prepare($query);

		if ($stmt)
		{
			$possibleValues = substr_count($condition, '?');
			if ($values != NULL && $possibleValues > 0)
			{ // bind values, don't bind any other values if $possibleValues < count($values)
				$valTypes = '';
				$checkedValues = array();
				$i = 0;
				foreach ($values as $key => $value)
				{
					if ($i == $possibleValues)
						break;

					if (is_string($key) && $key == 'blob')
						$valTypes .= 'b';
					else
						$valTypes .= strtolower(substr(gettype($value), 0, 1));

					$checkedValues[$i] = &$values[$key];
					++$i;
				}

				$params = array($valTypes);
				$params = array_merge($params, $checkedValues);

				$success = call_user_func_array(array($stmt, 'bind_param'), $params);

				if (!$success)
				{
					$stmt->close();
					throw new MySQLParamBindingException($this->dbl_current['link']->errno, $this->dbl_current['link']->error);
				}
			}

			if ($stmt->execute())
			{

				$result = $stmt->get_result();
				$stmt->close();

				return $result;
			}
			else
			{
				$errnum = $stmt->errno;
				$errmsg = $stmt->error;
				$stmt->close();
				throw new MySQLQueryException($errnum, $errmsg);
			}
		}
		else
		{
			throw new MySQLPreparedStatementException($this->dbl_current['link']->errno, $this->dbl_current['link']->error);
		}
	}

	/**
	 * Inserts values to a table from an array.
	 * Array format is table => value.
	 *
	 * If value is an array, the first element
	 * has to be 'blob', and the second must
	 * be the actual value (used for data streams).
	 * 
	 * @param  string $table  The table to insert data to.
	 * @param  array  $fields Data to insert.
	 * @return bool 		  True if query was successful.
	 */
	public function simpleInsert(string $table, array $fields) {
		$query = 'INSERT INTO `' . $table . '`(';
		$columns = array();
		$values = array();
		$nMarks = 0;
		$valueTypes = '';

		// build the query
		foreach ($fields as $col => $value)
		{
			array_push($columns, $col);

			if (is_array($value))
			{
				if ($value[0] == 'blob')
					$valueTypes .= 'b';
				$values[$nMarks] = &$fields[$col][1];
			}
			else
			{
				$valueTypes .= strtolower(substr(gettype($value), 0, 1));
				$values[$nMarks] = &$fields[$col];
			}
			
			++$nMarks;
		}

		$colList = @$this->gravifyColumnList(implode(',', $columns));
		$valMarkList = implode(',', F::genArray('?', $nMarks));

		$query .=  $colList . ') VALUES(' . $valMarkList. ')';

		// setup a prepared statement, and execute it
		$stmt = $this->prepare($query);

		if ($stmt)
		{
			$params = array($valueTypes);
			$params = array_merge($params, $values);

			$success = call_user_func_array(array($stmt, 'bind_param'), $params);

			if ($success)
			{
				if ($stmt->execute())
				{
					$stmt->close();
					return true;
				}
				else
				{
					$errnum = $stmt->errno;
					$errmsg = $stmt->error;
					$stmt->close();
					throw new MySQLQueryException($errnum, $errmsg);
				}
			}
			else
			{
				$stmt->close();
				throw new MySQLParamBindingException($this->dbl_current['link']->errno, $this->dbl_current['link']->error);
			}
		}
		else
		{
			throw new MySQLPreparedStatementException($this->dbl_current['link']->errno, $this->dbl_current['link']->error);
		}

		return false;
	}

	/**
	 * Updates columns on a table with the provided data.
	 * 
	 * @param  string $table      Table to perform update on.
	 * @param  array  $fields     Array with columns and values to set to.
	 * @param  string $condition  The selector condition.
	 * @param  array  $condValues The values for the condition.
	 * @return bool      		  True if query was successful.
	 */
	public function simpleUpdate(string $table, array $fields, string $condition='', array $condValues=array()) {
		$query = 'UPDATE `' . $table . '` SET ';
		$values = array();
		$valueTypes = '';

		// build update section
		$i = 0;
		foreach ($fields as $column => $value)
		{
			$query .= '`' . $column . '`=?,';

			if (is_array($value))
			{
				if ($value[0] == 'blob')
					$valueTypes .= 'b';

				$values[$i] = &$fields[$column][1];
			}
			else
			{
				$valueTypes .= strtolower(substr(gettype($value), 0, 1));
				$values[$i] = &$fields[$column];
			}

			$i++;
		}

		$query = substr($query, 0, strlen($query) - 1);

		// build condition section
		if ($condition != '')
		{
			$query .= ' WHERE ' . $condition;

			$possibleValues = substr_count($condition, '?');
			$nCondValues = 0;
			foreach ($condValues as $key => $value)
			{
				if ($nCondValues == $possibleValues)
					break; // break if count($condValues) > $possibleValues

				if (is_array($value))
				{
					if ($value[0] == 'blob')
						$valueTypes .= 'b';
					$values[$i] = &$value[1];
				}
				else
				{
					$valueTypes .= strtolower(substr(gettype($value), 0, 1));
					$values[$i] = &$condValues[$key];
				}

				unset($value);
				$i++;
				$nCondValues++;
			}
		}

		// finally, execute the built query
		$stmt = $this->prepare($query);
		if ($stmt)
		{
			$params = array($valueTypes);
			$params = array_merge($params, $values);
		
			$success = call_user_func_array(array($stmt, 'bind_param'), $params);
			
			if ($success)
			{
				if ($stmt->execute())
				{
					$stmt->close();
					return true;
				}
				else
				{
					$errnum = $stmt->errno;
					$errmsg = $stmt->error;
					$stmt->close();
					throw new MySQLQueryException($errnum, $errmsg);
				}
			}
			else
			{
				$stmt->close();
				throw new MySQLParamBindingException($this->dbl_current['link']->errno, $this->dbl_current['link']->error);
			}
		}
		else
		{
			throw new MySQLPreparedStatementException($this->dbl_current['link']->errno, $this->dbl_current['link']->error);
		}
	}

	/**
	 * Performs a delete query on a table
	 * 
	 * @param  string $table     The table to delete from.
	 * @param  string $condition The condition to base the delete on.
	 * @param  array  $fields    Values for the condition.
	 * @param  array  $options   Options are limit, limitstart
	 * @return bool              True if query was successful.
	 */
	public function simpleDelete(string $table, string $condition='', array $fields=array(), array $options=array()) {
		$query = 'DELETE FROM ' . $table;
		$values = array();
		$valueTypes = '';
		$nMarks = 0;

		// build condition
		if ($condition != '')
		{
			$possibleValues = substr_count($condition, '?');
			$query .= ' WHERE ' . $condition;
			
			if ($fields != NULL & count($fields) > 0)
			{
				foreach ($fields as $key => $value)
				{
					if ($possibleValues == $nMarks)
						break;

					if (is_string($key) && $key == 'blob')
						$valueTypes .= 'b';
					else
						$valueTypes .= strtolower(substr(gettype($value), 0, 1));

					$values[$nMarks] = &$fields[$key];
					++$nMarks;
				}
			}
		}

		if (isset($options['limit']) && isset($options['limitstart']))
		{
			$query .= ' LIMIT ' . (string)intval($options['limitstart']) . ', ' . (string)intval($options['limit']);
		}
		else if (isset($options['limit']))
		{
			$query .= ' LIMIT ' . (string)intval($options['limit']);
		}

		$stmt = $this->prepare($query);
		if ($stmt)
		{
			$params = array($valueTypes);
			$params = array_merge($params, $values);

			$success = call_user_func_array(array($stmt, 'bind_param'), $params);
			if ($success)
			{
				if ($stmt->execute())
				{
					$stmt->close();
					return true;
				}
				else
				{
					$errnum = $stmt->errno;
					$errmsg = $stmt->error;
					$stmt->close();
					throw new MySQLQueryException($errnum, $errmsg);
				}
			}
			else
			{
				$stmt->close();
				throw new MySQLParamBindingException($this->dbl_current['link']->errno, $this->dbl_current['link']->error);
			}
		}
		else
		{
			throw new MySQLPreparedStatementException($this->dbl_current['link']->errno, $this->dbl_current['link']->error);
		}
	}

	/**
	 * Checks if a table exists in the current selected database.
	 * @param  string $table Table to check for
	 * @return bool        	 True if the table exists, false if not.
	 */
	public function tableExists(string $table) : bool {
		$query = 'SHOW TABLES WHERE `Tables_in_' . $this->current_db . '`=?';

		$stmt = $this->prepare($query);
		if ($stmt)
		{
			$success = $stmt->bind_param('s', $table);
			if ($success)
			{
				if ($stmt->execute())
				{
					$result = $stmt->get_result();
					return $result->num_rows > 0;
				}
				else
				{
					$errnum = $stmt->errno;
					$errmsg = $stmt->error;
					$stmt->close();
					throw new MySQLQueryException($errnum, $errmsg);
				}
			}
			else
			{
				$stmt->close();
				throw new MySQLParamBindingException($this->dbl_current['link']->errno, $this->dbl_current['link']->error);
			}
		}
		else
		{
			throw new MySQLPreparedStatementException($this->dbl_current['link']->errno, $this->dbl_current['link']->error);
		}
	}

	/**
	 * Check if a column exsits in the provided table.
	 * 
	 * @param  string $table  Table to check.
	 * @param  string $column Column to check.
	 * @return bool           True if column exists, false if not.
	 */
	public function columnExists(string $table, string $column) : bool {
		$query = 'SHOW COLUMNS IN ' . $table . ' WHERE `Field`=?';

		$stmt = $this->prepare($query);
		if ($stmt)
		{
			$success = $stmt->bind_param('s', $column);
			if ($success)
			{
				if ($stmt->execute())
				{
					$result = $stmt->get_result();
					return $result->num_rows > 0;
				}
				else
				{
					$errnum = $stmt->errno;
					$errmsg = $stmt->error;
					$stmt->close();
					throw new MySQLQueryException($errnum, $errmsg);
				}
			}
			else
			{
				$stmt->close();
				throw new MySQLParamBindingException($this->dbl_current['link']->errno, $this->dbl_current['link']->error);
			}
		}
		else
		{
			throw new MySQLPreparedStatementException($this->dbl_current['link']->errno, $this->dbl_current['link']->error);
		}
	}

	/**
	 * Get information form a table.
	 * @param  string $table Table to check.
	 * @return array         Array of fields containing info about the table. False on error.
	 */
	public function getTableInfo(string $table) {
		$query = 'SHOW TABLE STATUS FROM `' . $this->current_db . '` WHERE `Name`=?';

		$stmt = $this->prepare($query);
		if ($stmt)
		{
			$success = $stmt->bind_param('s', $table);
			if ($success)
			{
				if ($stmt->execute())
				{
					$result = $stmt->get_result();
					return $result->fetch_assoc();
				}
				else
				{
					$errnum = $stmt->errno;
					$errmsg = $stmt->error;
					$stmt->close();
					throw new MySQLQueryException($errnum, $errmsg);
				}
			}
			else
			{
				$stmt->close();
				throw new MySQLParamBindingException($this->dbl_current['link']->errno, $this->dbl_current['link']->error);
			}
		}
		else
		{
			throw new MySQLPreparedStatementException($this->dbl_current['link']->errno, $this->dbl_current['link']->error);
		}
	}

	/**
	 * Get the table engine for the specified table.
	 * 
	 * @param  string $table Table to check.
	 * @return string        A string representing the engine type. Empty on error.
	 */
	public function getTableEngine(string $table) : string {
		$info = $this->getTableInfo($table);
		if ($info)
		{
			return $info['Engine'];
		}
		else
		{
			return '';
		}
	}
};


/**
 * Contains prepared statement functionality.
 */
trait Database_PreparedStatements {
	/**
	 * Prepares a new sql statement.
	 * 
	 * @param string $query The query to use.
	 */
	public function prepare(string $query) {
		return $this->dbl_current['link']->prepare($query);
	}
};

/**
 * Mapin database handler.
 */
class Database {
	use Database_Operations;
	use Database_PreparedStatements;

	/**
	 * The encoding to use with the database connection.
	 *
	 * @var string
	 */
	const DB_CHARSET = 'utf8';

	/**
	 * Encoding to use on columns.
	 *
	 * @var string
	 */
	const COLUMN_COLLATION = 'utf8_bin';

	/**
	 * Holds the singleton instance of the database manager.
	 *
	 * @var Database
	 * */
	private static $db_instance = NULL;
	
	/**
	 * Holds the link for the MyBB database.
	 * 
	 * @var mysqli
	 */
	// private $dbl_mybb = NULL;

	/**
	 * Hold sthe link for the API database.
	 * 
	 * @var mysqli
	 */
	private $dbl_api = NULL;

	/**
	 * Holds an array of database connection references.
	 * 
	 * @var array
	 */
	private $db_links = array();

	/**
	 * Holds the current selected database.
	 * 
	 * @var &mysqli
	 */
	private $dbl_current = NULL;

	/**
	 * Holds the current database type.
	 * 
	 * @var string
	 */
	private $current_db = '';
	
	function __construct() {
		global $config;

		// connect api
		$this->connectNew('api', $config['db']['name']['api'],
							$config['db']['user']['api'],
							$config['db']['pass']['api'],
							$config['db']['host']);

		// all done, select api db first
		$this->selectdb('api');
	}
	
	/*
	 * Returns the instance of the singleton database manager.
	 * */
	public static function instance() {
		if (static::$db_instance == NULL)
			static::$db_instance = new Database();
		return static::$db_instance;
	}

	/**
	 * Connects a new database.
	 * @param  string      $name The name of the new database.
	 * @param  string      $user Username of the user to use with the connection.
	 * @param  string      $pass Password for the user.
	 * @param  string      $host Host/IP to the database, default: 127.0.0.1.
	 * @param  int|integer $port The port to the database, default: 3306 (MySQL)
	 */
	public function connectNew(string $nameId, string $name, string $user, string $pass, string $host='127.0.0.1', int $port=3306) {
		if (array_key_exists($name, $this->db_links))
		{
			throw new DatabaseAlreadyExistsException(0, "The database with name '$name' exists already.");
		}

		$dbConn = @new \mysqli($host, $user, $pass, $name);

		if ($dbConn->connect_errno)
		{
			throw new MySQLException($dbConn->connect_errno, $dbConn->connect_error);
		}

		$dbConn->set_charset(self::DB_CHARSET);
		$this->db_links[$nameId] = array(
			'link' => $dbConn,
			'name' => $name
		);
	}

	/**
	 * Selects what database to currently work with.
	 * 
	 * @param  string $type Database to work with.
	 */
	
	public function selectdb(string $type) {
		if (array_key_exists($type, $this->db_links))
		{
			$this->dbl_current = &$this->db_links[$type];
			$this->current_db = $this->db_links[$type]['name'];
		}
	}

	/**
	 * Returns a reference to the current selected database.
	 * 
	 * @return mysqli A reference to the current db resource.
	 */
	public function &getCurrentResource() {
		return $this->dbl_current['link'];
	}
};

/**
 * Contains constants for MySQL error codes to prevent magic numbers.
 */
class MySQLErrors {
	/**
	 * Duplicate key during update/insert of a row.
	 */
	const DUPLICATE_KEY = 1062;
}