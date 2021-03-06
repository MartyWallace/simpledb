<?php namespace SimpleDb\Util;

/**
 * A utility class for composing queries.
 *
 * @package SimpleDb\Util;
 * @author Marty Wallace
 */
class Query {

	/**
	 * Create a new SELECT query.
	 *
	 * @param string $table The table to select from.
	 * @param string|string[] $fields The fields to select.
	 *
	 * @return Query
	 */
	public static function select($table, $fields = '*') {
		if (!is_array($fields)) {
			$fields = [$fields];
		}

		return new Query('SELECT ' . implode(', ', $fields) . ' FROM ' . $table);
	}

	/**
	 * Create a new DELETE query.
	 *
	 * @param string $table The table to delete from.
	 *
	 * @return Query
	 */
	public static function delete($table) {
		return new Query('DELETE FROM ' . $table);
	}

	/**
	 * Create a new INSERT query.
	 *
	 * @param string $table The table name.
	 * @param array $columns The columns to insert data for.
	 * @param array $update If provided, create an ON DUPLICATE KEY UPDATE for these columns.
	 *
	 * @return Query
	 */
	public static function insert($table, array $columns, array $update = []) {
		$values = array_map(function($column) { return ':' . $column; }, $columns);
		$base = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES(' . implode(', ', $values) . ')';

		if (!empty($update)) {
			$updates = array_map(function($column) { return $column . ' = :' . $column; }, $update);
			$base .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
		}

		return new Query($base);
	}

	/**
	 * Create a new DESCRIBE query.
	 *
	 * @param string $table The table to describe.
	 *
	 * @return Query
	 */
	public static function describe($table) {
		return new Query('DESCRIBE ' . $table);
	}

	/**
	 * Create a SHOW TABLES query.
	 *
	 * @return Query
	 */
	public static function showTables() {
		return new Query('SHOW TABLES');
	}

	/** @var string[] */
	private $_query = [
		'operation' => [],
		'where' => [],
		'order' => [],
		'limit' => []
	];

	/**
	 * @internal Use a static Query generator instead.
	 */
	public function __construct($operation = '') {
		$this->_query['operation'][] = $operation;
	}

	public function __toString() {
		return $this->compile();
	}

	/**
	 * Append literal MySQL.
	 *
	 * @param string $query The literal query to append.
	 *
	 * @return $this
	 */
	public function literal($query) {
		$this->_query[] = $query;

		return $this;
	}

	/**
	 * Append a WHERE statement to match against an input list of values.
	 *
	 * @param string|string[] $columns The columns to add WHERE, WHERE AND statements for with ? placeholders.
	 *
	 * @return $this
	 */
	public function where($columns) {
		foreach ($columns as $column) {
			$this->_query['where'][] = (count($this->_query['where']) === 0 ? 'WHERE ' : 'AND ') . $column . ' = ?';
		}

		return $this;
	}

	/**
	 * Append an ORDER BY statement.
	 *
	 * @param array|string $listOrColumn
	 * @param string $mode The sort mode (ASC or DESC).
	 *
	 * @return $this
	 */
	public function order($listOrColumn, $mode = null) {
		if (is_array($listOrColumn)) {
			$this->_query['order'][] = 'ORDER BY ';

			foreach ($listOrColumn as $column => $m) {
				$this->_query['order'][] = $column . ' ' . strtoupper($m) . ', ';
			}

			$this->_query['order'][] = rtrim(array_pop($this->_query['order']), ', ');
		} else {
			$this->_query['order'][] = 'ORDER BY ' . $listOrColumn . ' ' . strtoupper($mode);
		}

		return $this;
	}

	/**
	 * Append a LIMIT statement.
	 *
	 * @param int $startOrCount The start position of the limit. If this is the only argument, it is used as the count
	 * from the first result instead.
	 * @param int $count The amount of record to limit to.
	 *
	 * @return $this
	 */
	public function limit($startOrCount, $count = null) {
		$this->_query['limit'][] = 'LIMIT ' . intval($startOrCount) . ($count !== null ? ', ' . intval($count) : '');

		return $this;
	}

	/**
	 * Get the compiled query.
	 *
	 * @return string
	 */
	public function compile() {
		$blocks = [];

		foreach ($this->_query as $type => $block) {
			if (count($block) > 0) {
				$blocks[] = implode(' ', $block);
			}
		}

		return trim(preg_replace('/\s+/', ' ', implode(' ', $blocks)));
	}

}