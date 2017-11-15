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
				'mysql:host='.$this->host.';dbname='.$this->dbName.';',
				$this->user,
				$this->password,
				array(PDO::ATTR_TIMEOUT => 1)
			);
		} catch (PDOException $exception){
			return $exception;
		}

		return true;
	}

	private function doQuery($query, $mode = PDO::FETCH_COLUMN){
		$obj = $this->query($query);

		if($obj){
			return $obj->fetchAll($mode);
		}

		return NULL;
	}

	public function getDatabaseNames(){
		return $this->doQuery('SHOW DATABASES');
	}

	public function getTableNames(){
		$data = [$this->dbName];
		$query = $this->prePrepare('SHOW TABLES IN $other', $data);

		return $this->doQuery($query);
	}

	public function tableExists($table){
		$tableNames = $this->getTableNames();

		if(in_array(strtolower($table), $tableNames)){
			return true;
		}

		return false;
	}

	public function getColumnNames($table){
		$data = [$table];
		$query = $this->prePrepare('DESCRIBE $', $data);

		return $this->doQuery($query);
	}

	public function columExists($table, $column){
		$columnNames = $this->getColumnNames($table);

		if(in_array(strtolower($column), $columnNames)){
			return true;
		}

		return false;
	}

	public function prepare($query, $data = [], $index = false){
		$query = $this->prePrepare($query, $data);

		$obj = parent::prepare($query);
		$this->lastQuerry = $query;

		foreach($data as $key => $val){
			$obj->bindValue($key + 1, $val);
		}

		if(!$obj->execute()){
			return $this->errorInfo();
		}

		$result = $obj->fetchAll(PDO::FETCH_ASSOC);

		if($index){
			$structuredResult = array();
			foreach($result as $value){
				$structuredResult[$value[$index]] = $value;
			}

			return $structuredResult;
		}

		return $result;
	}

	private function prePrepare($query, &$data){
		$pattern = '/(?<!\$)\$(?!\$)/';

		$numberOfMatches = preg_match_all($pattern, $query);

		for($i = 0; $i < $numberOfMatches; $i++){
			if(preg_match('/^\w+$/', $data[0])){
				$query = preg_replace($pattern, array_shift($data), $query, 1);
			} else{
				return false;
			}
		}

		str_replace('$$', '$', $query);

		return $query;
	}

	public function getRows($table, $index = false, $where = false, $fields = []){
		$query = 'SELECT ';

		if(count($fields) === 0){
			$query .= '* ';
		} else{
			$query .= $this->makeParameterList('$', count($fields) - 1);
		}

		$query .= 'FROM $';

		if($where){
			$whereData = $this->generateWhereQuery($where);
			$query .= $whereData['query'];
		}

		$query .= ';';

		$completeData = array_merge($fields, [$table], $whereData['fields'], $whereData['values']);

		return $this->prepare($query, $completeData, $index);
	}

	private function makeParameterList($string, $times){
		return str_repeat($string.', ', $times).$string.' ';
	}

	private function generateWhereQuery($where){
		$result = array( 'query' => ' WHERE', 'values' => [], 'fields' => [] );
		$start = true;

		foreach($where as $field => $value){
			if($start){
				$result['query'] .= ' $ = ?';
			} else{
				$result['query'] .= ' AND $ = ?';
			}

			$result['values'][] = $value;
			$result['fields'][] = $field;
		}

		return $result;
	}

	public function getRow($table, $where = false, $fields = []){
		$result = $this->getRows($table, false, $where, $fields);

		if(count($result) !== 1 || !isset($result[0])){
			return false;
		}

		return $result[0];
	}

	public function setRow($table, $data, $fields = []){
		$query = 'INSERT INTO $ ';
		$completeData = array_merge([$table], $fields);
		$completeData = array_merge($completeData, $data);

		if(!empty($fields)){
			$query .= '(';
			$query .= $this->makeParameterList('$', count($fields) - 1);
			$query .= ')';
		}

		$query .= 'VALUES (';
		$query .= $this->makeParameterList('?', count($data) - 1);
		$query .= ');';

		return $this->prepare($query, $completeData);
	}

	public function changeRow($table, $data, $where, $fields = []){
		if(empty($fields)){
			$fields = $this->getColumnNames($table);
		}

		$query = 'UPDATE $ SET ';
		$query .= $this->makeParameterList('$ = ?', count($fields) - 1);

		$whereData = $this->generateWhereQuery($where);
		$query .= $whereData['query'];

		$completeData = array_merge([$table], $fields, $whereData['fields'], $data, $whereData['values']);

		return $this->prepare($query, $completeData);
	}
}

?>
