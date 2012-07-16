<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Db
 */

namespace Zend\Db\Adapter\Driver\Pgsql;

use Zend\Db\Adapter\Driver\ConnectionInterface;
use Zend\Db\Adapter\Exception;

/**
 * @category   Zend
 * @package    Zend_Db
 * @subpackage Adapter
 */
class Connection implements ConnectionInterface
{
    /**
     * @var Pgsql
     */
    protected $driver = null;

    /**
     * Connection parameters
     *
     * @var array
     */
    protected $connectionParameters = array();

    /**
     * @var resource
     */
    protected $resource = null;

    /**
     * In transaction
     *
     * @var boolean
     */
    protected $inTransaction = false;

    /**
     * Constructor
     *
     * @param mysqli $connectionInfo
     */
    public function __construct($connectionInfo = null)
    {
        if (is_array($connectionInfo)) {
            $this->setConnectionParameters($connectionInfo);
        } elseif ($connectionInfo instanceof \mysqli) {
            $this->setResource($connectionInfo);
        }
    }

    /**
     * @param  array $connectionParameters
     * @return Connection
     */
    public function setConnectionParameters(array $connectionParameters)
    {
        $this->connectionParameters = $connectionParameters;
        return $this;
    }

    /**
     * @param  Pgsql $driver
     * @return Connection
     */
    public function setDriver(Pgsql $driver)
    {
        $this->driver = $driver;
        return $this;
    }

    /**
     * @param  resource $resource
     * @return Connection
     */
    public function setResource($resource)
    {
        $this->resource = $resource;
        return;
    }

    /**
     * @return null
     */
    public function getCurrentSchema()
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $result = pg_query($this->resource, 'SELECT CURRENT_SCHEMA AS "currentschema"');
        if ($result == false) {
            return null;
        }
        return pg_fetch_result($result, 0, 'currentschema');
    }

    /**
     * @return resource
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * Connect to the database
     *
     * @return void
     * @throws Exception\RuntimeException on failure
     */
    public function connect()
    {
        if (is_resource($this->resource)) {
            return;
        }

        $connection = array();
        $options = array();

        foreach ($this->connectionParameters as $key => $value) {
            switch (strtolower($key)) {
                case 'host':
                case 'hostname':
                    $connection['host'] = $value;
                    break;
                case 'username':
                case 'user':
                    $connection['user'] = $value;
                    break;
                case 'password':
                case 'passwd':
                case 'pw':
                    $connection['password'] = $value;
                    break;
                case 'database':
                case 'dbname':
                case 'db':
                case 'schema':
                    $connection['dbname'] = $value;
                    break;
                case 'port':
                    $connection['port'] = (int)$value;
                    break;
                case 'charset':
                    $options['client_encoding'] = $value;
                    break;
                case 'driver':
                    break;
                case 'driver_options':
                    if (is_array($value)) {
                        $options = array_merge($options, $value);
                    } else {
                        $options[] = $value; // Pass through, as-is
                    }
                    break;
                case 'socket':
                default:
                    throw new Exception\InvalidConnectionParametersException(
                        'Invalid connection parameter name: ' . $key . ' => ' . $value,
                        $this->connectionParameters
                    );
            }
        }

        $q = function ($value, $symbol) {
            $value = str_replace($symbol, '\\' . $symbol, $value);
            $value = str_replace('\\', '\\\\', $value);
            return $symbol . $value . $symbol;
        };

        if (!empty($options)) {
            $connection['options'] = array();
            foreach ($options as $optionName => $optionValue) {
                if (is_int($optionName)) {
                    $connection['options'][] = $optionValue;
                    continue;
                }

                if ('-' == $optionName[0]) {
                    $option = $optionName;
                    $sep = ('-' == $optionName[1] ? '=' : ' ');
                } elseif (1 == strlen($optionName)) {
                    $sep = ' ';
                    $option = '-' . $optionName;
                } else {
                    $sep = '=';
                    $option = '--' . $optionName;
                }
                if (null !== $optionValue) {
                    $option .= $sep . $q($optionValue, '"');
                }
                $connection['options'][] = $option;
            }
            $connection['options'] = implode(' ', $connection['options']);
        }

        $connectionString = array();
        foreach ($connection as $key => $value) {
            $connectionString[] = $key . '=' . $q($value, "'");
        }

        $connectionString = implode(' ', $connectionString);

        // hide warnings thrown on failed connection attempts
        $resource = @pg_connect($connectionString);
        
        if ($resource === false) {
            // pg_last_error() does not work without a connection :(
            throw new Exception\RuntimeException('Connection error');
        }

        $this->resource = $resource;
    }

    /**
     * @return bool
     */
    public function isConnected()
    {
        return (is_resource($this->resource));
    }

    /**
     * @return void
     */
    public function disconnect()
    {
        pg_close($this->resource);
    }

    /**
     * @return void
     */
    public function beginTransaction()
    {
        // TODO: Implement beginTransaction() method.
    }

    /**
     * @return void
     */
    public function commit()
    {
        // TODO: Implement commit() method.
    }

    /**
     * @return void
     */
    public function rollback()
    {
        // TODO: Implement rollback() method.
    }

    /**
     * @param  string $sql
     * @return resource|\Zend\Db\ResultSet\ResultSetInterface
     */
    public function execute($sql)
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $resultResource = pg_query($this->resource, $sql);

        //var_dump(pg_result_status($resultResource));

        // if the returnValue is something other than a mysqli_result, bypass wrapping it
        if ($resultResource === false) {
            throw new Exception\InvalidQueryException(pg_errormessage());
        }

        $resultPrototype = $this->driver->createResult(($resultResource === true) ? $this->resource : $resultResource);
        return $resultPrototype;
    }

    /**
     * @param  null $name Ignored
     * @return string
     */
    public function getLastGeneratedValue($name = null)
    {
        if ($name == null) {
            return null;
        }
        $result = pg_query($this->resource, 'SELECT CURRVAL(\'' . str_replace('\'', '\\\'', $name) . '\') as "currval"');
        return pg_fetch_result($result, 0, 'currval');
    }

}
