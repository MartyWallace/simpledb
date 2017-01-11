<?php namespace SimpleDb;

use Exception;
use JsonSerializable;

/**
 * A model can be populated by raw data from rows returned from a query.
 *
 * @package SimpleDb
 * @author Marty Wallace
 */
abstract class Model implements JsonSerializable {

	/** @var string[] */
	private $_fields = [];

	/** @var mixed[] */
	private $_data = [];

	/** @var mixed[] */
	private $_unknown = [];

	/** @var Relation[] */
	private $_relations = [];

	/**
	 * Return an array of fields that this model should handle. The keys of the returned array should be the names of
	 * the fields, and the values should be the types for those fields.
	 *
	 * @see Field::INT
	 * @see Field::STRING
	 * @see Field::DATETIME
	 *
	 * @return string[]
	 */
	abstract protected function fields();

	/**
	 * Returns an array of relations that this model has to other models.
	 *
	 * @return array[]
	 */
	protected function relations() {
		return [];
	}

	/**
	 * @param Populator $populator The populator (usually a {@link Row} or {@link Rows} instance) that will provide data
	 * necessary to populate the newly created model.
	 *
	 * @return static|Models
	 */
	public static function from(Populator $populator) {
		return $populator->populate(static::class);
	}

	public function __construct(array $data = []) {
		$this->_fields = $this->fields();
		$this->_relations = $this->relations();

		foreach ($this->_relations as $name => $relation) {
			if (!($relation instanceof Relation)) {
				throw new Exception('Relation "' . $name . '" must inherit SimpleDb\Relation.');
			}

			if ($this->hasField($name)) {
				throw new Exception('Cannot declare relation "' . $name . '" on "' . static::class . '" - a field with the same name already exists.');
			}
		}

		foreach ($this->_fields as $field => $type) {
			$this->_data[$field] = null;
		}

		$this->fill($data);
	}

	public function __get($prop) {
		if (array_key_exists($prop, $this->_relations)) {
			return $this->getRelated($prop);
		}

		return $this->get($prop);
	}

	public function __set($prop, $value) {
		$this->set($prop, $value);
	}

	public function __isset($prop) {
		return array_key_exists($prop, $this->_data) || array_key_exists($prop, $this->_unknown) || array_key_exists($prop, $this->_relations);
	}

	/**
	 * Fill this model with data from a source array. The source array keys are the field names and their values the
	 * values to fill those fields with.
	 *
	 * @param array $data
	 */
	public function fill(array $data) {
		foreach ($data as $field => $value) {
			$this->set($field, $value);
		}
	}

	/**
	 * Gets a value from this model. If the value is associated with the {@link Model::fields() declared fields} for
	 * this model, the value is {@link Field::toRefined() refined} first.
	 *
	 * @param string $field The name of the field to get a value from.
	 *
	 * @return mixed
	 */
	public function get($field) {
		if ($this->hasField($field) && array_key_exists($field, $this->_data)) return Field::toRefined($this->_data[$field], $this->_fields[$field]);
		else if (array_key_exists($field, $this->_unknown)) return $this->_unknown[$field];

		return null;
	}

	/**
	 * Sets the value of a field on this model. If the field is a {@link Model::fields() listed field}, the value of
	 * that field is {@link Field::toPrimitive() made primitive} first so that it is able to be stored in MySQL.
	 *
	 * @param string $field The field to set.
	 * @param mixed $value The value to allocate to the field.
	 */
	public function set($field, $value) {
		if ($this->hasField($field)) $this->_data[$field] = Field::toPrimitive($value, $this->_fields[$field]);
		else $this->_unknown[$field] = $value;
	}

	/**
	 * Determine whether this model has a field in the list of {@link Model::fields() declared fields}.
	 *
	 * @param string $field The field to check for.
	 *
	 * @return bool
	 */
	public function hasField($field) {
		return array_key_exists($field, $this->fields());
	}

	public function getPrimitiveData() {
		return $this->_data;
	}

	public function getRefinedData() {
		$data = [];

		foreach ($this->_fields as $field => $type) {
			$data[$field] = Field::toRefined($this->get($field), $type);
		}

		return $data;
	}

	/**
	 * Fetch a related model.
	 *
	 * @param string $name The relation name {@link Model::relations() specified}.
	 *
	 * @returns Model|Models
	 *
	 * @throws Exception If the relation name is unknown.
	 */
	public function getRelated($name) {
		if (array_key_exists($name, $this->_relations)) {
			return $this->_relations[$name]->fetch($this);
		} else {
			throw new Exception('Unknown relation "' . $name . '".');
		}
	}

	public function jsonSerialize() {
		return $this->getRefinedData();
	}

}