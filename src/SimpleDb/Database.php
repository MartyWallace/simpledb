<?php namespace SimpleDb;

use PDO;
use PDOStatement;
use Exception;
use SimpleDb\Data\Table;
use SimpleDb\Data\Row;
use SimpleDb\Util\Query;
use SimpleDb\Util\Utils;

/**
 * Provides access to a database.
 *
 * @property-read PDO $pdo The PDO instance managing the connection internally.
 * @property-read int $lastInsertId The last ID inserted.
 * @property-read string[] $prepared A list of all the prepared queries. This is only available in debug mode.
 *
 * @package SimpleDb
 * @author Marty Wallace
 */
class Database {

	/** @var Database */
	private static $_instance = null;

	/** @var PDO */
	private $_pdo = null;

	private $_connection = [];

	/** @var Table[] */
	private $_tables = [];

	/** @var bool */
	private $_debug = false;

	/** @var string[] */
	private $_prepared = [];

	/**
	 * Statically get the active Database instance.
	 *
	 * @return Database
	 *
	 * @throws Exception If the database has not been instantiated.
	 */
	public static function get() {
		if (empty(static::$_instance)) {
			throw new Exception('The database has not been instantiated.');
		}

		return static::$_instance;
	}

	/**
	 * Database constructor.
	 *
	 * @param string $connection The connection string formatted username:password?@host/database.
	 * @param bool $debug Whether or not to set up the connection in debug mode.
	 *
	 * @throws Exception If this is not the first instance of Database created.
	 */
	public function __construct($connection, $debug = false) {
		if (self::$_instance === null) {
			$this->_debug = $debug;

			$this->_connection = Utils::parseConnectionString($connection);
			$this->_pdo = new PDO('mysql:host=' . $this->_connection['host'] . ';dbname=' . $this->_connection['database'], $this->_connection['username'], $this->_connection['password']);

			self::$_instance = $this;
		} else {
			throw new Exception('You have already created an instance of Database - you may only have one.');
		}
	}

	public function __get($prop) {
		if ($prop === 'pdo') return $this->_pdo;
		if ($prop === 'lastInsertId') return intval($this->_pdo->lastInsertId());
		if ($prop === 'prepared') return $this->_prepared;

		return null;
	}

	/**
	 * Prepares a PDOStatement.
	 *
	 * @param string|Query $query The query to prepare.
	 *
	 * @return PDOStatement
	 */
	public function prepare($query) {
		$query = strval($query);

		if ($this->_debug) $this->_prepared[] = $query;

		return $this->_pdo->prepare($query);
	}

	/**
	 * Prepare and execute a query, returning the PDOStatement that is created when preparing the query.
	 *
	 * @param string|Query $query The query to execute.
	 * @param array|null $params Optional parameters to bind to the query.
	 *
	 * @return PDOStatement
	 *
	 * @throws Exception If the PDOStatement returns any errors, they are thrown as an exception.
	 */
	public function query($query, array $params = null) {
		$stmt = $this->prepare($query);
		$stmt->execute($params);

		if ($stmt->errorCode() !== '00000') {
			$err = $stmt->errorInfo();
			throw new Exception($err[0] . ': ' . $err[2]);
		}

		return $stmt;
	}

	/**
	 * Returns the first row provided by executing a query.
	 *
	 * @param string|Query $query The query to execute.
	 * @param array|null $params Parameters to bind to the query.
	 *
	 * @return Row
	 *
	 * @throws Exception If the internal PDOStatement returns any errors, they are thrown as an exception.
	 * @throws Exception If the provided class does not exist.
	 */
	public function one($query, array $params = null) {
		$rows = $this->all($query, $params);

		return count($rows) > 0 ? $rows[0] : null;
	}

	/**
	 * Returns all rows provided by executing a query.
	 *
	 * @param string|Query $query The query to execute.
	 * @param array|null $params Parameters to bind to the query.
	 *
	 * @return Row[]
	 *
	 * @throws Exception If the internal PDOStatement returns any errors, they are thrown as an exception.
	 * @throws Exception If the provided class does not exist.
	 */
	public function all($query, array $params = null) {
		$stmt = $this->query($query, $params);

		return $stmt->fetchAll(PDO::FETCH_CLASS, Row::class);
	}

	/**
	 * Returns the first value in the first column returned from executing a query.
	 *
	 * @param string|Query $query The query to execute.
	 * @param array|null $params Parameters to bind to the query.
	 * @param mixed $fallback A fallback value to use if no results were returned by the query.
	 *
	 * @return mixed
	 *
	 * @throws Exception If the internal PDOStatement returns any errors, they are thrown as an exception.
	 */
	public function prop($query, array $params = null, $fallback = null) {
		$result = $this->query($query, $params)->fetch(PDO::FETCH_NUM);

		if (!empty($result)) {
			return $result[0];
		}

		return $fallback;
	}

	/**
	 * Return all tables in this database.
	 *
	 * @return Table[]
	 */
	public function getTables() {
		// TODO: Going through information_schema is a more efficient way to do this, just certain what the implications
		// are at the stage (whether it's common to have full access to it, etc).
		if (empty($this->_tables)) {
			foreach ($this->all(Query::showTables()) as $row) {
				$name = $row->{'Tables_in_' . $this->_connection['database']};
				$this->_tables[$name] = new Table($name);
			}
		}
		
		return $this->_tables;
	}

	/**
	 * Return a Table instance for a specific table.
	 *
	 * @param string $name The name of the table.
	 *
	 * @return Table
	 *
	 * @throws Exception If the table does not exist.
	 */
	public function table($name) {
		$name = strval($name);
		
		if (!array_key_exists($name, $this->getTables())) {
			throw new Exception('Table "' . $name . '" does not exist.');
		}

		return $this->_tables[$name];
	}
}