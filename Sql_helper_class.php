<?php

/**
 * SQLBoost:
 *
 * This class makes SQL a lot easier and
 * provides you with full controll over it.
 *
 * @author Florian Heidebrecht
 *
 * tables, fields, values
 * values generic prefilter from user or standard
 */
class SqlBoost extends PDO{

	private $host;
	private $dbName;
	private $user;
	private $password;
	private $castMethod = 2;
	private $debug;
	private $debugIndex = 1;
	private $debugMessage = array(
		'noVariables' => 'Variables are not allowed in a query!!!',
		'invalidArgs' => 'Table or Columns are not clean!!!'
	);

	public function __construct($host = 'localhost', $dbName = '', $user = 'root', $password = '', $debug = false){
		$this->host = $host;
		$this->dbName = $dbName;
		$this->user = $user;
		$this->password = $password;
		$this->debug = $debug;
		//error_reporting(0);
		try{
			parent::__construct(
					'mysql:host=' . $this->host . ';dbname=' . $this->dbName . ';', $this->user, $this->password, array(PDO::ATTR_TIMEOUT => 1)
			);
		} catch (PDOException $e){
			throw $e;
		}
	}

	public function setDatabaseName($name){
		$query = 'use %';
		$this->execute($query, $name);
	}

	public function getDatabaseNames(){
		$this->setCastMethod(7);
		return $this->execute('SHOW DATABASES;');
	}

	public function isTable($table, $dbname = ''){
		$systemTables = $this->getTableNames($dbname);
		return in_array($table, $systemTables);
	}

	public function getTableNames($dbname = NULL){
		if(!isset($dbname)){
			$dbname = $this->dbName;
		}

		$dbname = $this->toArray($dbname);

		$this->setCastMethod(7);
		return $this->execute('SHOW TABLES IN %', $dbname);
	}

	public function isColumn($table, $column){
		$systemColumns = $this->getColumnNames($table);

		return in_array($column, $systemColumns);
	}

	public function getColumnNames($table){
		$table = $this->toArray($table);
		$this->setCastMethod(7);

		return $this->execute('DESCRIBE %', $table);
	}

	public function execute($query, $tables = [], $columns = [], $values = []){
		$rawArgs = get_defined_vars();
		$args = $this->prepareQuery($rawArgs);

		if(!$args){
			return false;
		}

		$obj = $this->bindValuesToQuery($args['query'], $args['values']);

		if(!$obj->execute()){
			return $this->showDebug();
		}

		return $this->castResult($obj);
	}

	private function bindValuesToQuery($query, $values){
		$obj = parent::prepare($query);

		foreach($values as $key => $value){
			$obj->bindValue($key += 1, $value);
		}

		return $obj;
	}

	private function prepareQuery($args){

		if(!(strpos($args['query'], '$') === false)){
			$this->showDebug($this->debugMessage['noVariables'], 1);
			return false;
		}

		$args['tables'] = $this->toArray($args['tables']);
		$args['columns'] = $this->toArray($args['columns']);

		$rawMergedValues = array_merge($args['columns'], $args['tables']);
		$mergedValues = $this->checkReplacements($rawMergedValues);

		if(!$mergedValues){
			$this->showDebug($this->debugMessage['invalidArgs'], 1);
			return false;
		}

		$this->replaceValues($args['query'], $mergedValues);

		return $args;
	}

	private function checkReplacements(&$array){
		$result = filter_var_array($array, FILTER_SANITIZE_STRING);
		return ($array === $result) ? $result : false;
	}

	private function replaceValues(&$query, $values){
		foreach($values as $value){
			$query = preg_replace('/\%/', $value, $query, 1);
		}
	}

	public function setCastMethod($number){
		$this->castMethod = intval($number, 10);
	}

	private function castResult($obj){
		$result = $obj->fetchAll($this->castMethod);
		$this->castMethod = 2;
		return $result;
	}

	public function startQueue(){

	}

	public function endQueue(){

	}

	public function create(){

	}

	public function select(){

	}

	public function insert(){

	}

	public function update(){

	}

	public function delete(){

	}

	public function reCast($array, $specialMethod){
		//Different Methods than PDO
		return $array;
	}

	private function showDebug($hint = 'no Hint', $hide = 0){
		if(!$this->debug){
			return false;
		}

		$index = $this->debugIndex;
		if(isset($hide)){
			$index += $hide;
		}
		$debugInfo = debug_backtrace(NULL, 3)[$index];

		$file = $debugInfo['file'] . "\n";
		$line = $debugInfo['line'] . "\n";
		$function = $debugInfo['function'];

		echo '<pre>';
		echo 'File: ' . $file;
		echo 'LineNumber: ' . $line . "\n";
		echo 'DebugHint: ' . $hint . "\n\n";

		echo 'Function: ' . $function . "()\n";
		echo 'Argument List:' . "\n";
		var_export($debugInfo['args']);
		echo '</pre>';

		die();
	}

	static function toArray($var){
		return (is_array($var)) ? $var : [$var];
	}

}

$sql = new SqlBoost('localhost', 'test', 'root', '', 1);
$var1 = 'myvar1';
$var2 = ['test', 'test2'];
//$sql->getDatabaseNames($var1, $var2);
// make test table for testing
//var_dump($sql);
echo '<pre>';
//var_dump($sql->execute('Select * From sqltable;', NULL, NULL));
$sql->setCastMethod(2);
var_dump($sql->execute('Select %, %, % From %', ['testtable'], ['bla', 'alb', 'id']));
//var_dump($sql->execute('Select * FROM %t', 'sqltable'));

var_dump($sql->getTableNames());
//var_dump(SqlBoost::toArray($var1));
?>
