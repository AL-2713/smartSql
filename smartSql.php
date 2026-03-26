<?php

// Build SQL queries from an object
// Version: 1

class smartSql {

    private $WHERE;
    private $TABLE;
    private $STATEMENT;
    private $LIMIT;
    private $OFFSET;
    private $ORDER;
    private $SET;
    private $SELECT_VALUES;

    const SUPPORTED_STATEMENTS = ["SELECT", "UPDATE"];
    const SUPPORTED_CONDITION_TYPES = ["WHERE", "LIKE"];
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
        $this->SELECT_VALUES = "*";

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
        $order = strtoupper($order);
        $this->ORDER['keyword'] = $keyword;
        
        if (in_array($order, self::VALID_ORDERS)) {
            $this->ORDER['order'] = $order;
        }
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

    // Return the query string from the configures variables
    public function build() {

        switch($this->STATEMENT) {
            case "SELECT":
                return "$this->STATEMENT ".$this->buildSelectParams()." FROM $this->TABLE " . $this->buildConditionParams() . $this->buildOrder() . $this->buildLimitParams();
                break;
            
            case "UPDATE":
                if (count($this->SET) == 0) {
                    $this->smartSqlError("SET values are required when building an UPDATE query");
                } else {
                    return "$this->STATEMENT $this->TABLE SET " . $this->buildSetParams() . " " . $this->buildConditionParams();
                }
        }
    }



    // Basic warning message if display_errors is enabled
    private function smartSqlError($errorMsg) {
        trigger_error("[smartSql]: " . $errorMsg);
    }

    

    // Return SET params as string. Only used for UPDATE queries
    private function buildSetParams() {
        $setData = "";
        $i = 1;
        foreach ($this->SET as $setEntry) {
            $comma = $i < count($this->SET) ? ", " : "";
            $setData .= $setEntry['column'] . ' = "' . str_replace('"', '""', $setEntry['value']) . '"' . $comma;
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
        if ($this->ORDER['keyword']) {
            $orderData .= " ORDER BY " . $this->ORDER['keyword'];

            if (in_array($this->ORDER['order'], self::VALID_ORDERS)) {
                $orderData .= " ". $this->ORDER['order'];
            }
        }

        return $orderData;
    }

    // Return the WHERE conditions as a string, if configured
    private function buildConditionParams() {

        $invertStr = array("WHERE"=>" !", "LIKE"=>" NOT");

        $whereData = "";
        $whereLen = count($this->WHERE);

        if ($whereLen > 0) {
            $whereData = "WHERE ";
            
            $i = 0;
            while ($i < $whereLen) {
                
                foreach ($this->WHERE as $clause) {
                    $whereVal = str_replace('"', '""', $clause['value']);
                    $whereCond = strtoupper($clause['condType']);
                    $whereData .= $clause['column'];
                    
                    // If false, invert the search to where the condition is false
                    $invertStr = $clause['trueOp'] ? "" : $invertStr[$whereCond];
                    $whereData .= $invertStr;

                    // Set condition depending on condType
                    switch ($whereCond) {
                        case "LIKE":
                            $whereData .= " LIKE \"%$whereVal%\"";
                            break;
                        
                        case "WHERE":
                        default:
                            if ($clause['value'] == null) {
                                $whereData .= " IS NULL";
                            
                            } else {
                                $whereData .= "=\"$whereVal\"";
                            }
                            
                            break;
                    }

                    $i++;

                    if ($i < $whereLen) {
                        $whereData .= " AND ";
                    }
                }
            }
        }

        return $whereData;
    }

}