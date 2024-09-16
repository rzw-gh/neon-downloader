<?php


class ORM
{
    private $connection;
    private $connectionType;
    private $debug = false;
    private $log = false;
    private $logPath;
    private $charset;
    private $throwException = false;
    private $transactionThrowException = false;
    private $sql;
    private $sqlList;
    private $host;
    private $user;
    private $password;
    private $database;
    private $table = false;
    private $operation = false;
    private $raw_operation = false;
    private $error = false;
    private $autocommit = true;
    private $aggregateName = false;
    private $telegram_chat_id;

    const RAW_OPERATION = 0;
    const INSERT_OPERATION = 1;
    const UPDATE_OPERATION = 2;
    const SELECT_OPERATION = 3;
    const DELETE_OPERATION = 4;
    const EXISTS_OPERATION = 5;
    const AGGREGATE_OPERATION = 6;

    const STRING = "String";
    const INTEGER = "Integer";
    const BOOLEAN = "Boolean";
    const ARR = "Array";
    const MultidimensionalARR = "Multidimensional Array";
    const AssociativeARR = "Associative Array";
    const BLANK = "Blank";

    const VALIDATE_TYPE = "validateType";
    const SET_DEBUG = "setDebug";
    const SET_LOG = "setLog";
    const SET_EXCEPTION = "setException";
    const CLEAN = "clean";
    const CLEAN_ARR = "cleanArr";
    const COUNT_METHOD = "count";
    const MAX_METHOD = "max";
    const MIN_METHOD = "min";
    const AVG_METHOD = "avg";
    const SUM_METHOD = "sum";
    const RAW_METHOD = "raw";
    const TABLE_METHOD = "table";
    const SELECT_METHOD = "select";
    const INSERT_METHOD = "insert";
    const UPDATE_METHOD = "update";
    const CONNECTION_METHOD = "connection";
    const SQL = "sql";
    const WARNING = "warning";

    const WHERE = "WHERE";
    const BETWEEN = "BETWEEN";
    const ORDER = "ORDER";
    const ASC = "ASC";
    const DESC = "DESC";
    const LIMIT = "LIMIT";
    const JOIN = "JOIN";
    const LEFT = "LEFT";
    const RIGHT = "RIGHT";
    const INNER = "INNER";
    const UNION = "UNION";
    const _CASE = "CASE";
    const WHEN = "WHEN";
    const THEN = "THEN";
    const _ELSE = "ELSE";
    const END = "END";
    const ALL = "ALL";
    const _AND_ = "AND";
    const _OR_ = "OR";
    const IN = "IN";
    const NOT = "NOT";
    const IS = "IS";
    const ON = "ON";
    const NULL = "NULL";
    const GROUP = "GROUP";
    const BY = "BY";
    const HAVING = "HAVING";
    const SPACE = " ";
    const PO = "(";
    const PC = ")";
    const SLASH = "/";

    const MYSQLI = 'mysqli';
    const PDO = 'pdo';

    function __construct($host, $user, $password, $database, $charset = "utf8mb4", $connectionType = self::MYSQLI)
    {
        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
        $this->database = $database;
        $this->connectionType = strtolower($connectionType);
        $this->logPath = $_SERVER['DOCUMENT_ROOT'] . self::SLASH . 'log' . self::SLASH;
        $this->charset = $charset;
        $this->connect();
    }

    private function handleException($error, $method, $ignore = false)
    {
        $this->error[$method][] = $error;

        if ($this->log) {
            if (isset($this->telegram_chat_id)) {
                $logContent = date("y/m/d-h:i:s") . "-" . $this->telegram_chat_id . "\n" . "$error";
            } else {
                $logContent = date("y/m/d-h:i:s") . "\n" . "$error";
            }
            
            if ($method === self::SQL) {
                $logContent .= "\n" . "$this->sql";
            }
            $logContent .= "\n" . "-------------------";
            if (!file_exists($this->logPath)) {
                mkdir($this->logPath, 0700);
            }
            file_put_contents($this->logPath . "log.txt", $logContent . PHP_EOL, FILE_APPEND | LOCK_EX);
        }

        $this->defaultSetting();

        if ($this->debug) {
            die($method . '# ' . $error);
        } else {
            if (($this->transactionThrowException || $this->throwException) && !$ignore) {
                throw new Exception($method . '# ' . $error);
            }
        }
    }

    private function validateType($type, $target, $method, $not = false)
    {
        $typeCond = false;
        switch ($type) {
            case self::STRING:
                $typeCond = is_string($target);
                break;
            case self::INTEGER:
                $typeCond = is_integer($target);
                break;
            case self::BOOLEAN:
                $typeCond = is_bool($target);
                break;
            case self::ARR:
                $typeCond = is_array($target);
                break;
            case self::AssociativeARR:
                $typeCond = false;
                if ($this->validateType(self::ARR, $target, self::VALIDATE_TYPE) && array() !== $target) {
                    $typeCond = array_keys($target) !== range(0, count($target) - 1);
                }
                break;
            case self::MultidimensionalARR:
                $typeCond = false;
                if ($this->validateType(self::ARR, $target, self::VALIDATE_TYPE)) {
                    rsort($target);
                    $typeCond = isset($target[0]) && is_array($target[0]);
                }
                break;
            case self::NULL:
                $typeCond = is_null($target);
                break;
            case self::BLANK:
                $typeCond = trim($target) === '';
        }

        if (!$not) {
            if (!$typeCond) {
                $this->handleException($this->table . '# Data type exception: expected ' . $type . '. ' . gettype($target) . ' given', $method);
                return false;
            }
        } else {
            if ($typeCond) {
                $this->handleException($this->table . '# Data type exception: unexpected ' . $type . ' provided', $method);
                return false;
            }
        }

        return true;
    }

    public function setDebug($toggle)
    {
        if ($this->validateType(self::BOOLEAN, $toggle, self::SET_DEBUG)) {
            $this->debug = $toggle;
            $this->throwException = !$this->debug;
        }
    }

    public function setException($toggle)
    {
        if ($this->validateType(self::BOOLEAN, $toggle, self::SET_EXCEPTION)) {
            $this->throwException = $toggle;
            $this->debug = !$this->throwException;
        }
    }

    public function setLog($toggle, $path = false, $chat_id)
    {
        if (isset($chat_id)) {
            $this->telegram_chat_id = $chat_id;
        }
        
        if ($this->validateType(self::BOOLEAN, $toggle, self::SET_LOG)) {
            $this->log = $toggle;
        }
        if ($path) {
            if ($this->validateType(self::STRING, $path, self::SET_DEBUG)) {
                $this->logPath = $path . '/'; // doesn't matter if there is redundant slashes
            }
        }
    }

    private function connect()
    {
        $this->connection = mysqli_connect($this->host, $this->user, $this->password, $this->database);
        mysqli_set_charset($this->connection, $this->charset);
        if ($this->connection->connect_error) {
            $this->handleException("Connection failed: " . $this->connection->connect_error, self::CONNECTION_METHOD);
        }
    }

    public function disconnect()
    {
        if (isset($this->connection)) {
            if (!$this->connection->close()) {
                $this->handleException('an error occurred closing connection', self::CONNECTION_METHOD, true);
            }
        }
    }

    public function transaction()
    {
        mysqli_begin_transaction($this->connection);
        $this->autocommit = false;
        $this->transactionThrowException = true;
    }

    public function commit()
    {
        if (!$this->autocommit) {
            mysqli_commit($this->connection);
            $this->autocommit = true;
            $this->transactionThrowException = false;
        } else {
            $this->handleException('commit method called before transaction method declaration', self::WARNING, true);
        }
    }

    public function rollback()
    {
        if (!$this->autocommit) {
            mysqli_rollback($this->connection);
            $this->autocommit = true;
            $this->transactionThrowException = false;
        } else {
            $this->handleException('rollback method called before transaction method declaration', self::WARNING, true);
        }
    }

    private function clean($string, $type = 'key', $joinOperation = false)
    {
        if ($type == 'key') {
            if (
                $this->validateType(self::NULL, $string, self::CLEAN, true)
                ||
                $this->validateType(self::BLANK, $string, self::CLEAN, true)
            ) {
                if (!$joinOperation) {
                    $string = str_replace('`', '', $string); // Removes back ticks
                }
                $string = preg_replace('/\s/', '', $string); // Removes white spaces
                $string = str_replace("'", '', $string); // Removes single quotation marks
                $string = str_replace('"', '', $string); // Removes double quotation marks
                if (!$joinOperation) {
                    $string = '`' . $string . '`'; // Adds back ticks
                }
            }
        } elseif ($type == 'value') {
            $string = str_replace('`', '', $string); // Removes back ticks
            $string = $this->connectionType === self::MYSQLI ? mysqli_real_escape_string($this->connection, $string) : $string;
            $string = "'" . $string . "'";// Adds single quotation marks
        }
        return $string;
    }

    private function cleanArr($array, $type = 'key')
    {
        if ($this->validateType(self::ARR, $array, self::CLEAN_ARR)) {
            foreach ($array as &$value) {
                $value = $this->clean($value, $type == 'value' ? 'value' : 'key');
            }
        }

        return $array;
    }

    public function raw($sql)
    {
        $this->operation = self::RAW_OPERATION;
        $withoutWhitSpace = preg_replace('/\s/', '', $sql);
        if (strcasecmp(substr($withoutWhitSpace, 0, 6), "select") === 0) {
            $this->raw_operation = self::SELECT_OPERATION;
        } elseif (strcasecmp(substr($withoutWhitSpace, 0, 6), "insert") === 0) {
            $this->raw_operation = self::INSERT_OPERATION;
        } elseif (strcasecmp(substr($withoutWhitSpace, 0, 6), "update") === 0) {
            $this->raw_operation = self::UPDATE_OPERATION;
        } elseif (strcasecmp(substr($withoutWhitSpace, 0, 6), "delete") === 0) {
            $this->raw_operation = self::DELETE_OPERATION;
        }
        if ($this->validateType(self::STRING, $sql, self::RAW_METHOD)) {
            $this->sql .= $sql;
        }
        return $this;
    }

    public function table($table)
    {
        if (is_array($table)) {
            if (
                $this->validateType(self::MultidimensionalARR, $table, self::TABLE_METHOD, true)
                &&
                $this->validateType(self::AssociativeARR, $table, self::TABLE_METHOD)
            ) {
                $this->table = array_keys($table)[0] . self::SPACE . 'AS' . self::SPACE . array_values($table)[0];
            }
        } else if ($this->validateType(self::STRING, $table, self::TABLE_METHOD)) {
            $this->table = $this->clean($table);
        }
        return $this;
    }

    public function select(...$columns)
    {
        $this->operation = self::SELECT_OPERATION;
        if ($this->validateType(self::MultidimensionalARR, $columns, self::SELECT_METHOD, true)) {
            $columnsString = '*';
            if (count($columns) > 0) {
                $valueSets = array();
                foreach ($columns as $value) {
                    if (is_callable($value)) {
                        $callbackString = call_user_func($value, $this);
                        $valueSets[] = $callbackString->toSql();
                    } else if (is_object($value) && !is_callable($value)) {
                        $valueSets[] = $value->toSql();
                    } else {
                        $joinOperation = strpos($value, "`") !== false;
                        $valueSets[] = $this->clean($value, "key", $joinOperation);
                    }
                }
                $columnsString = implode(', ', $valueSets);
            }
            $this->sql = "SELECT $columnsString FROM $this->table";
        }

        return $this;
    }

    // multiple Where functions //
    private function addWhereKeyword()
    {
        if (!strpos($this->sql, self::WHERE)) {
            $this->sql .= self::SPACE . self::WHERE . self::SPACE;
        }
    }

    private function quoted($param)
    {
        return is_int($param) ? $param : "'" . $param . "'";
    }

    private function implodeCondition($condition)
    {
        $condition[2] = $this->quoted($condition[2]);
        return implode(self::SPACE, $condition);
    }

    private function pushConditions($conditions, $operator)
    {
        $count = 0;
        while ($count < count($conditions)) {
            $this->sql .= self::PO . $this->implodeCondition($conditions[$count]) . self::PC;
            $count++;
            if ($count < count($conditions)) {
                $this->sql .= self::SPACE . $operator . self::SPACE;
            }
        }
    }

    private function handleWhereIn($condition, $not)
    {
        $this->addWhereKeyword();
        $column = $condition[0];
        $values = $condition[1];
        for ($i = 0; $i < count($values); $i++) {
            $values[$i] = $this->quoted($values[$i]);
        }
        $values = self::PO . implode(",", $values) . self::PC;
        $this->sql .= self::PO . $column . self::SPACE;
        if ($not) {
            $this->sql .= self::NOT . self::SPACE;
        }
        $this->sql .= self::IN . self::SPACE . $values . self::PC;
    }

    private function handleWhere($conditions, $and)
    {
        $this->addWhereKeyword();
        if ($and) {
            $this->sql .= self::SPACE . self::_AND_ . self::SPACE;
        }
        $this->sql .= self::PO;
        if (is_callable($conditions)) {
            call_user_func($conditions, $this);
        } else {
            $this->pushConditions($conditions, self::_AND_);
        }
        $this->sql .= self::PC;
        return $this;
    }

    public function where($conditions)
    {
        $this->handleWhere($conditions, false);
        return $this;
    }

    public function andWhere($conditions)
    {
        $this->handleWhere($conditions, true);
        return $this;
    }

    public function orWhere($conditions)
    {
        $this->sql .= self::SPACE . self::_OR_ . self::SPACE . self::PO;
        if (is_callable($conditions)) {
            call_user_func($conditions, $this);
        } else {
            $this->pushConditions($conditions, self::_OR_);
        }
        $this->sql .= self::PC;
        return $this;
    }

    public function whereIn($condition)
    {
        $this->handleWhereIn($condition, false);
        return $this;
    }

    public function whereNotIn($condition)
    {
        $this->handleWhereIn($condition, true);
        return $this;
    }

    public function handleWhereNull($column, $not)
    {
        $this->addWhereKeyword();
        $this->sql .= self::PO;
        $this->sql .= $column . self::SPACE . self::IS . self::SPACE;
        if ($not) {
            $this->sql .= self::NOT . self::SPACE;
        }
        $this->sql .= self::NULL;
        $this->sql .= self::PC;
    }

    public function whereNull($column)
    {
        $this->handleWhereNull($column, false);
        return $this;
    }

    public function whereNotNull($column)
    {
        $this->handleWhereNull($column, true);
        return $this;
    }

    public function handleBetween($column, $condition, $not)
    {
        $firstCond = $this->quoted($condition[0]);
        $secondCond = $this->quoted($condition[1]);
        $this->addWhereKeyword();
        $this->sql .= self::PO;
        $this->sql .= $column . self::SPACE;
        if ($not) {
            $this->sql .= self::NOT . self::SPACE;
        }
        $this->sql .= self::BETWEEN . self::SPACE
            . $firstCond . self::SPACE . self::_AND_ .
            self::SPACE . $secondCond;
        $this->sql .= self::PC;
    }

    public function whereBetween($column, $conditions)
    {
        $this->handleBetween($column, $conditions, false);
        return $this;
    }

    public function whereNotBetween($column, $conditions)
    {
        $this->handleBetween($column, $conditions, true);
        return $this;
    }

    public function toSql()
    {
        return $this->sql . "\n";
    }

    // Join functions
    private function handleJoin($table, $firstKey, $operator, $secondKey, $type)
    {
        $this->sql .= self::SPACE . $type . self::SPACE . self::JOIN
            . self::SPACE . $table . self::SPACE . self::ON . self::SPACE .
            $firstKey . $operator . $secondKey;
    }

    public function join($table, $firstKey, $operator, $secondKey)
    {
        $this->handleJoin($table, $firstKey, $operator, $secondKey, self::INNER);
        return $this;
    }

    public function leftJoin($table, $firstKey, $operator, $secondKey)
    {
        $this->handleJoin($table, $firstKey, $operator, $secondKey, self::LEFT);
        return $this;
    }

    public function rightJoin($table, $firstKey, $operator, $secondKey)
    {
        $this->handleJoin($table, $firstKey, $operator, $secondKey, self::RIGHT);
        return $this;
    }

    //Group functions
    public function groupBy($columns)
    {
        $this->sql .= self::SPACE . self::GROUP . self::SPACE . self::BY . self::SPACE
            . implode(",", $columns);
        return $this;
    }

    public function having($condition)
    {
        $cond = $condition[0];
        $operator = $condition[1];
        $value = $condition[2];
        $this->sql .= self::SPACE . self::HAVING . self::SPACE .
            $cond . self::SPACE . $operator . self::SPACE . $value;
        return $this;
    }

    //Union functions
    public function union()
    {
        $this->sql .= self::SPACE . self::UNION . self::SPACE;
        return $this;
    }

    public function unionAll()
    {
        $this->sql .= self::SPACE . self::UNION . self::SPACE . self::ALL . self::SPACE;
        return $this;
    }

    // case functions
    public function _case($conditions, $finalResult)
    {
        $this->sql .= self::SPACE;
        $this->sql .= self::PO;
        $this->sql .= self::_CASE;
        $this->sql .= self::SPACE;
        foreach ($conditions as $condition) {
            $this->sql .= self::WHEN . self::SPACE . $condition[0] . $condition[1] . $this->quoted($condition[2]) .
                self::SPACE . self::THEN . self::SPACE . $this->quoted($condition[3]);
            $this->sql .= self::SPACE;
        }
        $this->sql .= self::_ELSE . self::SPACE . $this->quoted($finalResult) . self::SPACE . self::END;
        $this->sql .= self::PC  . ' `' . $conditions[0][0] . '`';
        return $this;
    }

    // Order By functions
    public function orderBy($columns)
    {
        $this->sql .= self::SPACE . self::ORDER .
            self::SPACE . self::BY;
        foreach ($columns as $key => $val) {
            $this->sql .= self::SPACE . $key . self::SPACE . $val;
            if (next($columns)) {
                $this->sql .= ",";
            }
        }
        return $this;
    }

    // first record function
    public function first()
    {
        $this->sql .= self::SPACE . self::LIMIT . self::SPACE . 1;
        return $this;
    }

    //limit function
    public function limit($count)
    {
        $this->sql .= self::SPACE . self::LIMIT . self::SPACE . $count;
        return $this;
    }

    public function exists()
    {
        $this->operation = self::EXISTS_OPERATION;
        return $this;
    }

    public function insert($columns)
    {
        $this->operation = self::INSERT_OPERATION;
        if ($this->validateType(self::AssociativeARR, $columns, self::INSERT_METHOD)) {
            $keys = implode(', ', $this->cleanArr(array_keys($columns)));
            $values = array();
            foreach ($columns as $value) {
                $values[] = is_null($value) ? 'null' : $this->clean($value, 'value');
            }
            $values = implode(', ', array_values($values));
            $this->sql = "INSERT INTO $this->table ($keys) VALUES ($values)";
        }
        return $this;
    }

    public function update($columns)
    {
        $this->operation = self::UPDATE_OPERATION;
        if ($this->validateType(self::AssociativeARR, $columns, self::UPDATE_METHOD)) {
            $valueSets = array();
            foreach ($columns as $key => $value) {
                $value = is_null($value) ? 'null' : $this->clean($value, 'value');
                $valueSets[] = $this->table . '.' . $this->clean($key) . " = " . $value;
            }
            $query = implode(', ', $valueSets);
            $this->sql = "UPDATE $this->table SET $query";
        }

        return $this;
    }

    public function delete()
    {
        $this->operation = self::DELETE_OPERATION;
        $this->sql = "DELETE FROM $this->table";
        return $this;
    }

    public function count($id = 'id')
    {
        $this->aggregateName = 'count';
        $this->operation = self::AGGREGATE_OPERATION;
        if ($this->validateType(self::STRING, $id, self::COUNT_METHOD)) {
            $this->clean($id);
            $this->sql = "SELECT COUNT($id) as $this->aggregateName FROM $this->table";
        }
        return $this;
    }

    public function max($column = 'id')
    {
        $this->aggregateName = 'max';
        $this->operation = self::AGGREGATE_OPERATION;
        if ($this->validateType(self::STRING, $column, self::MAX_METHOD)) {
            $this->clean($column);
            $this->sql = "SELECT MAX($column) as $this->aggregateName FROM $this->table";
        }
        return $this;
    }

    public function min($column = 'id')
    {
        $this->aggregateName = 'min';
        $this->operation = self::AGGREGATE_OPERATION;
        if ($this->validateType(self::STRING, $column, self::MIN_METHOD)) {
            $this->clean($column);
            $this->sql = "SELECT MIN($column) as $this->aggregateName FROM $this->table";
        }
        return $this;
    }

    public function avg($column = 'id')
    {
        $this->aggregateName = 'avg';
        $this->operation = self::AGGREGATE_OPERATION;
        if ($this->validateType(self::STRING, $column, self::AVG_METHOD)) {
            $this->clean($column);
            $this->sql = "SELECT AVG($column) as $this->aggregateName FROM $this->table";
        }
        return $this;
    }

    public function sum($column = 'id')
    {
        $this->aggregateName = 'sum';
        $this->operation = self::AGGREGATE_OPERATION;
        if ($this->validateType(self::STRING, $column, self::SUM_METHOD)) {
            $this->clean($column);
            $this->sql = "SELECT SUM($column) as $this->aggregateName FROM $this->table";
        }
        return $this;
    }

    public function order_by(...$columns)
    {
        $this->sql .= " ORDER BY";
        foreach ($columns as $index => $column) {
            $columnName = $column;
            if ($column[0] === '-') {
                $columnName = substr("$column", strpos($column, "-") + 1);
            }
            $this->sql .= " $columnName";
            if ($column[0] === '-') {
                $this->sql .= " DESC";
            } else {
                $this->sql .= " ASC";
            }
            if (count($columns) != $index + 1) {
                $this->sql .= ",";
            }
        }
        return $this;
    }

    public function offset($count, $offset = NULL)
    {
        $this->sql .= " LIMIT $count";
        if (isset($offset)) {
            $this->sql .= ", $offset";
        }
        return $this;
    }

    public function getSql()
    {
        return $this->sqlList;
    }

    public function hasError()
    {
        return is_array($this->error);
    }

    public function getError()
    {
        if (is_array($this->error)) {
            $showCaseError = $this->error;
            $showCaseError['sql_statement'][] = $this->sqlList;
            return $showCaseError;
        } else {
            return is_array($this->error);
        }
    }

    private function defaultSetting()
    {
        $this->operation = false;
        $this->raw_operation = false;
        $this->table = false;
        $this->aggregateName = false;
        $this->sql = false;
    }

    public function execute()
    {
        return $this->get();
    }

    public function get()
    {
        $return = array();
        $this->sql .= ";";

        // append current sql to sqlList
        $this->sqlList[] = $this->table . '# ' . $this->sql;

        $results = $this->connection->query($this->sql);

        if (!$results) {
            $this->handleException($this->table . '# ' . mysqli_error($this->connection), self::SQL);
        } else {
            $return['status'] = true;
            if (self::UPDATE_OPERATION || ($this->operation == self::RAW_OPERATION && $this->raw_operation == self::UPDATE_OPERATION)) {
                $return['status'] = mysqli_affected_rows($this->connection);
            }
            if ($this->operation == self::INSERT_OPERATION || ($this->operation == self::RAW_OPERATION && $this->raw_operation == self::INSERT_OPERATION)) {
                $return['result'] = mysqli_insert_id($this->connection);
            }
            if ($this->operation == self::EXISTS_OPERATION) {
                $return['status'] = $results->num_rows > 0;
            }
            if ($this->operation == self::DELETE_OPERATION || ($this->operation == self::RAW_OPERATION && $this->raw_operation == self::DELETE_OPERATION)) {
                $return['status'] = mysqli_affected_rows($this->connection);
            }
            if ($this->operation == self::AGGREGATE_OPERATION) {
                $return['result'] = floatval($results->fetch_assoc()[$this->aggregateName]);
            }
            if ($this->operation == self::SELECT_OPERATION || ($this->operation == self::RAW_OPERATION && $this->raw_operation == self::SELECT_OPERATION)) {
                $return = array();
                while ($row = $results->fetch_assoc()) {
                    $return[] = $row;
                }
            }
        }
        $this->defaultSetting();

        return $return;
    }

    function __destruct()
    {
        $this->disconnect();
    }

}
