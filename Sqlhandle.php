<?php

/**
 * Sqlhandle: A class to siplify the usage of the PDO class
 *
 * @autor Florian Heidebrecht
 * edited by Philipp Caldwell
 *
 */
class Sqlhandle extends PDO{

	private $host;
	private $dbName;
	private $user;
	private $password;
	public $lastQuerry = '';

	public function __construct($host = 'localhost', $dbName = '', $user = 'root', $password = ''){
		$this->host = $host;
		$this->dbName = $dbName;
		$this->user = $user;
		$this->password = $password;

		$this->conn();
	}

	public function setDbName($value){
		$this->dbName = $value;
		$this->conn();
	}

	private function conn(){
		try{
			parent::__construct(
					'mysql:host=' . $this->host . ';dbname=' . $this->dbName . ';', $this->user, $this->password, array(PDO::ATTR_TIMEOUT => 1)
			);
		} catch (PDOException $exception){
			return $exception;
		}

		return true;
	}

	public function getDatabaseNames(){
		return $this->prepare('SHOW DATABASES');
	}

	public function getTableNames(){
		$database = [$this->dbName];
		$query = 'SHOW TABLES IN %d';

		return $this->prepare($query, NULL, NULL, NULL, $database);
	}

	public function tableExists($table){
		$tableNames = $this->getTableNames();

		if(in_array(strtolower($table), $tableNames)){
			return true;
		}

		return false;
	}

	public function getColumnNames($tableName){
		$table = [$tableName];
		$query = 'Describe %t';
		return $this->prepare($query, $table);
	}

	public function columExists($table, $column){
		$columnNames = $this->getColumnNames($table);

		if(in_array(strtolower($column), $columnNames)){
			return true;
		}

		return false;
	}

	public function prepare($query, $tables = [], $columns = [], $values = [], $database = []){
		$this->preEvaluation($query, $tables, $columns, $database);
		$obj = parent::prepare($query);
		$this->lastQuerry = $query;

		foreach($values as $key => $value){
			$obj->bindValue($key += 1, $value);
		}

		if(!$obj->execute()){
			return $this->errorInfo();
		}

		$result = $obj->fetchAll(PDO::FETCH_ASSOC);

		return $result;
	}

	private function preEvaluation(&$rawquery, $tables = [], $columns = [], $database = []){
		$rawquery = preg_replace_callback("/%(?'amt'\d+)(?'el'\w+|\?)/", function($matches){

			if($matches['el'] != '?'){
				$matches['el'] = '%' . $matches['el'];
			}

			return implode(',', array_fill(0, $matches['amt'], $matches['el']));
		}, $rawquery);

		$this->bindValuesToQuery($rawquery, 't', $tables);
		$this->bindValuesToQuery($rawquery, 'c', $columns);
		$this->bindValuesToQuery($rawquery, 'd', $database);

		return true;
	}

	public function bindValuesToQuery(&$query, $label, $data = []){
		foreach($data as $value){
			if(preg_match('/^\w+$/', $value)){
				$query = preg_replace('/%' . $label . '/', $value, $query, 1);
			} else{
				return false;
			}
		}
		return $query;
	}

	//TODO Fix Where
	public function select($tableName, $cloumns, $where = 'WHERE 1=1'){
		$countColumns = count($cloumns);
		$table = [$tableName];

		$tableSelector = '*';
		if($countColumns > 0){
			//%8c as example
			$tableSelector = '%' . $countColumns . 'c';
		}

		$query = 'SELECT ' . $tableSelector . ' ' . $where;

		$this->prepare($query, $table, $cloumns);
	}

	public function insert($tableName, $cloumns, $values = [], $where = 'WHERE 1=1'){
		$countColumns = count($cloumns);
		$table = [$tableName];

		$replacer = '*';
		if($countColumns > 0){
			$replacer = '%' . $countColumns;
		}
		$query = "Insert Into %t " . $replacer . "c VALUES (%" . $countColumns . '?' . ") " . $where . ';';

		$this->prepare($query, $table, $cloumns, $values);
	}

	public function update_($tableName, $cloumnsString, $where = 'WHERE 1=1'){
		$table = [$tableName];
		$query = 'UPDATE %t SET' . $cloumnsString . ' ' . $where . ';';

		$this->prepare($query, $table, [], $values);
	}

	public function delete($tableName, $where = 'WHERE 1=1'){
		$table = [$tableName];
		$query = "DELETE FROM %t " . $where . ';';

		$this->prepare($query, $table);
	}

	public function fetchResult($data, $columnName = 'id', $mode = ''){
		$structuredResult = array();
		foreach($data as $value){
			$structuredResult[$value[$columnName]] = $value;
		}

		return $structuredResult;
	}

}
?>
