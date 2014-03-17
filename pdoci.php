<?php
/**
 * PDOCI
 *
 * PHP version 5.3
 *
 * @category PDOOCI
 * @package  PDOOCI
 * @author   Eustáquio Rangel <eustaquiorangel@gmail.com>
 * @license  http://www.gnu.org/licenses/gpl-2.0.html GPLv2
 * @link     http://github.com/taq/pdoci
 */
namespace PDOOCI;
require_once "statement.php";

/**
 * Main class of PDOCI
 *
 * PHP version 5.3
 *
 * @category Connection
 * @package  PDOOCI
 * @author   Eustáquio Rangel <eustaquiorangel@gmail.com>
 * @license  http://www.gnu.org/licenses/gpl-2.0.html GPLv2
 * @link     http://github.com/taq/pdoci
 */
class PDO
{
    private $_con = null;
    private $_autocommit = true;
    private $_last_error = null;

    /** 
     * Class constructor
     *
     * @param string $data     the connection string
     * @param string $username user name
     * @param string $password password
     * @param string $options  options to send to the connection
     *
     * @return PDO object
     */
    public function __construct($data, $username, $password, $options=null)
    {
        if (!function_exists("\oci_parse")) {
            throw new \PDOException("No support for Oracle, please install the OCI driver");
        }

        try {
            if (!is_null($options) && array_key_exists(\PDO::ATTR_PERSISTENT, $options)) {
                $this->_con = \oci_pconnect($username, $password, $data);
                $this->setError();
            } else {
                $this->_con = \oci_connect($username, $password, $data);
                $this->setError();
            }
        } catch (\Exception $exception) {
            throw new \PDOException($exception->getMessage());
        } 
        return $this;
    }

    /**
     * Return the connection
     *
     * @return connection handle
     */
    public function getConnection() 
    {
        return $this->_con;
    }

    /**
     * Execute a query
     *
     * @param string $statement sql query
     * @param int    $mode      PDO query() mode
     * @param int    $p1        PDO query() first parameter
     * @param int    $p2        PDO query() second parameter
     *
     * @return PDOOCIStatement
     */
    public function query($statement, $mode=null, $p1=null, $p2=null)
    {
        // TODO: use mode and parameters
        $stmt = null;
        try {
            $stmt = new PDOOCIStatement($this, $statement);
            $stmt->execute();
            $this->setError();
            return $stmt;
        } catch (Exception $e) {
            throw new \PDOException($exception->getMessage());
        }
        return $stmt;
    }

    /**
     * Execute query
     *
     * @param string $sql query
     *
     * @return number of affected rows
     */
    public function exec($sql)
    {
        try {
            $stmt = $this->query($sql);
            $rows = $stmt->rowCount();
            $stmt->closeCursor();
            return $rows;
        } catch (Exception $e) {
            throw new \PDOException($e->getMessage());
        }
        return $this;
    }

    /**
     * Set an attribute
     *
     * @param int   $attr  attribute
     * @param mixed $value value
     *
     * @return boolean if set was ok
     */
    public function setAttribute($attr, $value)
    {
        switch($attr)
        {
        case \PDO::ATTR_AUTOCOMMIT:
            $this->_autocommit = (is_bool($value) && $value) || in_array(strtolower($value), array("on", "true"));
            return;
        }
    }

    /**
     * Return an attribute
     *
     * @param int $attr attribute
     *
     * @return mixed attribute value
     */
    public function getAttribute($attr)
    {
        switch($attr)
        {
        case \PDO::ATTR_AUTOCOMMIT:
            return $this->_autocommit;
        }
        return null;
    }

    /**
     * Return the auto commit flag
     *
     * @return boolean auto commit flag
     */
    public function getAutoCommit()
    {
        return $this->_autocommit;
    }

    /**
     * Commit connection
     *
     * @return boolean if commit was executed
     */
    public function commit()
    {
        \oci_commit($this->_con);
        $this->setError();
    }

    /**
     * Rollback connection
     *
     * @return boolean if rollback was executed
     */
    public function rollBack()
    {
        \oci_rollback($this->_con);
        $this->setError();
    }

    /**
     * Start a transaction, setting auto commit to off
     *
     * @return null
     */
    public function beginTransaction()
    {
        $this->setAttribute(\PDO::ATTR_AUTOCOMMIT, false);
    }

    /**
     * Prepare a statement
     *
     * @param string $query   for statement
     * @param mixed  $options for driver
     *
     * @return PDOOCIStatement
     */
    public function prepare($query, $options=null)
    {
        $stmt = null;
        try {
            $stmt = new PDOOCIStatement($this, $query);
        } catch (Exception $e) {
            throw new \PDOException($e->getMessage());
        }
        return $stmt;
    }

    /**
     * Sets the last error found
     *
     * @param mixed $obj optional object to extract error from
     *
     * @return null
     */
    public function setError($obj=null)
    {
        $obj = $obj ? $obj : $this->_con;
        if (!is_resource($obj)) {
            return;
        }
        $error = \oci_error($obj);
        if (!$error) {
            return null;
        }
        $this->_last_error = $error;
    }

    /**
     * Returns the last error found
     *
     * @return int error code
     */
    public function errorCode()
    {
        if (!$this->_last_error) {
            return null;
        }
        return intval($this->_last_error["code"]);
    }

    /**
     * Returns the last error info
     *
     * @return array error info
     */
    public function errorInfo()
    {
        if (!$this->_last_error) {
            return null;
        }
        return array($this->_last_error["code"],
                     $this->_last_error["code"],
                     $this->_last_error["message"]);
    }

    /**
     * Close connection
     *
     * @return null
     */
    public function close()
    {
        if (is_null($this->_con)) {
            return;
        }
        \oci_close($this->_con);
        $this->_con = null;
    }

    /**
     * Trigger stupid errors who should be exceptions
     *
     * @param int    $errno   error number
     * @param string $errstr  error message
     * @param mixed  $errfile error file
     * @param int    $errline error line
     *
     * @return null
     */
    public function errorHandler($errno, $errstr, $errfile, $errline)
    {
        preg_match('/(ORA-)(\d+)/', $errstr, $ora_error);
        if ($ora_error) {
            $this->_last_error = intval($ora_error[2]);
        } else {
            $this->setError($this->_con);
        }
    }

    /** 
     * Return available drivers
     * Will insert the OCI driver on the list, if not exist
     *
     * @return array with drivers
     */
    public function getAvailableDrivers()
    {
        $drivers = \PDO::getAvailableDrivers();
        if (!in_array("oci", $drivers)) {
            array_push($drivers, "oci");
        }
        return $drivers;
    }

    /**
     * Return if is on a transaction
     *
     * @return boolean on a transaction
     */
    public function inTransaction()
    {
        return !$this->_autocommit;
    }

    /**
     * Quote a string
     *
     * @param string $string to be quoted
     * @param int    $type   parameter type
     *
     * @return string quoted
     */
    public function quote($string, $type=null)
    {
        $string = preg_replace('/\'/', "''", $string);
        $string = "'$string'";
        return $string;
    }
}
?>
