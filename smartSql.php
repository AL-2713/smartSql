<?php

// Build SQL queries from an object
// Version: 1.5
// Added support for INSERT queries / support for building queries with values seperated in an array
// Support for multible ORDER conditions
// Added > and < for WHERE conditions
// Added OR condition
// Fixed NULL variable handling in buildConditionParams

class smartSql {

    private $WHERE;
    public $TABLE;
    private $STATEMENT;
    private $LIMIT;
    private $OFFSET;
    public $ORDER;
    private $SET;
    private $SELECT_VALUES;
    private $INSERTS;

    const SUPPORTED_STATEMENTS = ["SELECT", "UPDATE", "INSERT"];
    const SUPPORTED_CONDITION_TYPES = ["WHERE", "LIKE", "GREATER", "LESSER", ">", "<", "="];
    const VALID_ORDERS = ['ASC', 'DESC'];


    function __construct($statement, $table) {
        $mainStatement = strtoupper($statement);

        if (!in_array($mainStatement, self::SUPPORTED_STATEMENTS)) {
            $this->smartSqlError("Not a valid query statement");
        }

        $this->STATEMENT = $mainStatement;
        $this->TABLE = $table;
        $this->WHERE = array();
        $this->SET = array();
        $this->ORDER = array();
        $this->INSERTS = array();
        $this->SELECT_VALUES = "*";
		$this->conditionOR = false;
        $this->VALUE_ARRAY = array();

    }
	
	function clearVar($var) {
		switch(strtoupper($var)) {
			case "ORDER":
				$this->ORDER = array();
				break;
			
			case "LIMIT":
				unset($this->LIMIT);
				unset($this->OFFSET);
				break;
		}
	}


    // Add a WHERE condition to the object
    /*
        @ column: the SQL column to preform the filter
        @ value: value of the filter condition
        @ condType: a supported condition clause
        @ trueOp: if False, will invert the filter to not include results of the condition
    */
    public function addWhere($column, $value, $condType = "WHERE", $trueOp = true) {
        if (!in_array(strtoupper($condType), self::SUPPORTED_CONDITION_TYPES)) {
            $this->smartSqlError("condition type '$condType' not recognized, defaulting to WHERE clause.");
        }

        $i = count($this->WHERE);
        $this->WHERE[$i]['column'] = $column;
        $this->WHERE[$i]['value'] = $value;
        $this->WHERE[$i]['condType'] = $condType;
        $this->WHERE[$i]['trueOp'] = $trueOp;
    }


    // Set the ORDER BY variables
    public function initOrder($keyword, $order = null) {
        $orderObj = array();
		$orderObj['keyword'] = $keyword;
        
        if (in_array($order, self::VALID_ORDERS)) {
			$order = strtoupper($order);
			$orderObj['order'] = $order;
        }
		$this->ORDER[] = $orderObj;
    }

    
    // Set the SELECT variables. Will take either a string or build the variables from an array
    public function initSelectVars($selectVars) {

        if (gettype($selectVars) == "string") {
            $this->SELECT_VALUES = $selectVars;
        
        } elseif (gettype($selectVars) == "array") {
            if (gettype($this->SELECT_VALUES) == "string") {
                $this->SELECT_VALUES = array();
            }
            
            foreach ($selectVars as $selectEntry) {
                $this->SELECT_VALUES[] = $selectEntry;
            }
        }
    }


    // Set LIMIT parameters
    public function initLimit($limit, $offset = null) {
        if (gettype($limit) == "integer") {
            $this->LIMIT = $limit;
        }
        if (gettype($offset) == "integer") {
            $this->OFFSET = $offset;
        }
    }

    // Set the SET variables. Only used for UPDATE queries
    public function initSet($column, $value) {
        if ($this->STATEMENT != "UPDATE") {
            $this->smartSqlError("SET conditions are only for UPDATE queries, not '$this->STATEMENT'");
            return;
        }

        $i = count($this->SET);
        $this->SET[$i]['column'] = $column;
        $this->SET[$i]['value'] = $value;
    }
    
    // Add an insert value param. Only used for INSERT queries
    public function initInsert($column, $value) {
        if ($this->STATEMENT != "INSERT") {
            $this->smartSqlError("INSERT conditions are only for INSERT queries, not '$this->STATEMENT'");
            return;
        }

        $i = count($this->INSERTS);
        $this->INSERTS[$i]['column'] = $column;
        $this->INSERTS[$i]['value'] = $value;
    }
	
	// Enable whether to add WHERE conditions with the OR condition
	public function initOR($switchBool) {
		$this->conditionOR = $switchBool;
	}

    // Return the query string from the configures variables
    public function build($seperateValues = false) {
        $this->SEPERATE_VALUES = $seperateValues;
        $finalQuery = "";

        switch($this->STATEMENT) {
            case "SELECT":
                $finalQuery = "$this->STATEMENT ".$this->buildSelectParams()." FROM $this->TABLE " . $this->buildConditionParams() . $this->buildOrder() . $this->buildLimitParams();
                break;
            
            case "UPDATE":
                if (count($this->SET) == 0) {
                    $this->smartSqlError("SET values are required when building an UPDATE query");
                } else {
                    $finalQuery = "$this->STATEMENT $this->TABLE SET " . $this->buildSetParams() . " " . $this->buildConditionParams();
                }
            
            case "INSERT":
                $finalQuery = "$this->STATEMENT INTO $this->TABLE " . $this->buildInsertParams();
                break;
        }

        if ($seperateValues) {
            return [$finalQuery, $this->VALUE_ARRAY];
        }

        return $finalQuery;
    }



    // Basic warning message if display_errors is enabled
    private function smartSqlError($errorMsg) {
        trigger_error("[smartSql]: " . $errorMsg);
    }

    private function returnQueryValue($value) {
        if ($this->SEPERATE_VALUES) {
            $this->VALUE_ARRAY[] = $value;
            return "?";
        }
        
        return '"' . str_replace('"', '""', $value) . '"';
    }
    
    // Return the INSERT params as a full string. Only for INSERT queries
    private function buildInsertParams() {
        $insertColumns = "";
        $insertValues = "";
        $i = 1;
        foreach ($this->INSERTS as $insertObj) {
            $comma = $i < count($this->INSERTS) ? ", " : "";

            $insertColumns .= $insertObj['column'] . $comma;
            $insertValues .= $this->returnQueryValue($insertObj['value']) . $comma;

            $i++;
        }

        return "($insertColumns) VALUES ($insertValues)";
    }

    

    // Return SET params as string. Only used for UPDATE queries
    private function buildSetParams() {
        $setData = "";
        $i = 1;
        foreach ($this->SET as $setEntry) {
            $comma = $i < count($this->SET) ? ", " : "";
            $setData .= $setEntry['column'] . ' = ' . $this->returnQueryValue($setEntry['value']) . $comma;
            $i++;
        }

        return $setData;
    }

    // Return SELECT params as string
    private function buildSelectParams() {
        $selectData = "";
        switch(gettype($this->SELECT_VALUES)) {
            case "string":
                $selectData = $this->SELECT_VALUES;
                break;
            
                case "array":
                    $i = 1;
                    foreach ($this->SELECT_VALUES as $entry) {
                        $comma = $i < count($this->SELECT_VALUES) ? "," : "";
                        $selectData .= $entry . $comma;
                        $i++;
                    }
                break;
        }

        return $selectData;
    }
    

    // Return the LIMIT and OFFSET parameters as a string if configured in the object
    private function buildLimitParams() {
        $limitData = "";

        if (gettype($this->OFFSET) == "integer" && gettype($this->LIMIT) != "integer") {
            $this->smartSqlError("LIMIT must be set when OFFSET is set");
        
        } else {
            
            if (gettype($this->LIMIT) == "integer") {
                $limitData .= " LIMIT $this->LIMIT";
            }
            if (gettype($this->OFFSET) == "integer") {
                $limitData .= " OFFSET $this->OFFSET";
            }
        }

        return $limitData;
    }

    // Return the ORDER condition as a string, if configured
    private function buildOrder() {
        $orderData = "";
		
		if (count($this->ORDER) > 0) {
			$orderData .= " ORDER BY ";
			$i = 1;
			foreach ($this->ORDER as $orderObj) {
				$orderData .= $orderObj['keyword'];
				
				if (in_array($orderObj['order'], self::VALID_ORDERS)) {
					$orderData .= " ". $orderObj['order'];
				}

				if ($i < count($this->ORDER)) {
					$orderData .= ", ";
				}
				
				$i++;
			}
		}

        return $orderData;
    }

    // Return the WHERE conditions as a string, if configured
    private function buildConditionParams() {

        $invertStrTypes = array("WHERE"=>" !", "LIKE"=>" NOT");

        $whereData = "";
        $whereLen = count($this->WHERE);

        if ($whereLen > 0) {
            $whereData = "WHERE ";
            
            $i = 0;
            while ($i < $whereLen) {
                
                foreach ($this->WHERE as $clause) {
                    $whereCond = strtoupper($clause['condType']);
                    $whereData .= $clause['column'];
                    
                    // If false, invert the search to where the condition is false
                    $invertStr = $clause['trueOp'] ? "" : $invertStrTypes[$whereCond];
                    $whereData .= $invertStr;

                    // Set condition depending on condType
                    switch ($whereCond) {
                        case "LIKE":
                            $whereVal = str_replace('"', '""', $clause['value']);
                            $whereData .= " LIKE \"%$whereVal%\"";
                            break;
						
						case ">":
						case "GREATER":
							$whereData .= " > " . $clause['value'];
							break;
						
						case "<":
						case "LESSER":
							$whereData .= " < " . $clause['value'];
							break;
                        
						case "=":
                        case "WHERE":
                        default:
                            if (gettype($clause['value']) == "NULL") {
                                $whereData .= " IS NULL";
                            
                            } else {
                                $whereData .= "=" . $this->returnQueryValue($clause['value']);
                            }
                            
                            break;
                    }

                    $i++;

                    if ($i < $whereLen) {
						$sepCondition = $this->conditionOR ? " OR " : " AND ";
                        $whereData .= $sepCondition;
                    }
                }
            }
        }

        return $whereData;
    }

}
