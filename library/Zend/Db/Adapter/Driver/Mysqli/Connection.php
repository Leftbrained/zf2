<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Db
 */

namespace Zend\Db\Adapter\Driver\Mysqli;

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
     * @var Mysqli
     */
    protected $driver = null;

    /**
     * Connection parameters
     *
     * @var array
     */
    protected $connectionParameters = array();

    /**
     * @var \mysqli
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
     * @param array|mysqli|null $connectionInfo
     * @throws \Zend\Db\Adapter\Exception\InvalidArgumentException
     */
    public function __construct($connectionInfo = null)
    {
        if (is_array($connectionInfo)) {
            $this->setConnectionParameters($connectionInfo);
        } elseif ($connectionInfo instanceof \mysqli) {
            $this->setResource($connectionInfo);
        } elseif (null !== $connectionInfo) {
            throw new Exception\InvalidArgumentException('$connection must be an array of parameters, a mysqli object or null');
        }
    }

    /**
     * @param Mysqli $driver
     * @return Connection
     */
    public function setDriver(Mysqli $driver)
    {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Set connection parameters
     *
     * @param  array $connectionParameters
     * @return Connection
     */
    public function setConnectionParameters(array $connectionParameters)
    {
        $this->connectionParameters = $connectionParameters;
        return $this;
    }

    /**
     * Get connection parameters
     *
     * @return array
     */
    public function getConnectionParameters()
    {
        return $this->connectionParameters;
    }

    /**
     * Get current schema
     *
     * @return string
     */
    public function getCurrentSchema()
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        /** @var $result \mysqli_result */
        $result = $this->resource->query('SELECT DATABASE()');
        $r = $result->fetch_row();
        return $r[0];
    }

    /**
     * Set resource
     *
     * @param  mysqli $resource
     * @return Connection
     */
    public function setResource(mysqli $resource)
    {
        $this->resource = $resource;
        return $this;
    }

    /**
     * Get resource
     *
     * @return \mysqli
     */
    public function getResource()
    {
        $this->connect();
        return $this->resource;
    }

    /**
     * Connect
     *
     * @return null
     */
    public function connect()
    {
        if ($this->resource instanceof \mysqli) {
            return;
        }

        // Parse connectionParameters into local variables
        $hostname = $username = $passwd = $dbname = $port = $socket = $charset = null;

        foreach ($this->connectionParameters as $key => $value) {
            switch (strtolower($key)) {
                case 'host':
                case 'hostname':
                    $hostname = $value;
                    break;
                case 'username':
                case 'user':
                    $username = $value;
                    break;
                case 'password':
                case 'passwd':
                case 'pw':
                    $passwd = $value;
                    break;
                case 'database':
                case 'dbname':
                case 'db':
                case 'schema':
                    $dbname = $value;
                    break;
                case 'port':
                    $port = (int)$value;
                    break;
                case 'socket':
                    $socket = $value;
                    break;
                case 'charset':
                    $charset = $value;
                    break;
                case 'driver':
                    break;
                case 'driver_options':
                default:
                    throw new Exception\InvalidConnectionParametersException(
                        'Invalid connection parameter name: ' . $key . ' => ' . $value,
                        $this->connectionParameters
                    );
            }
        }

        $resource = \mysqli_init();

        // prevent hanging until maximum execution time limit with non-responsive host:port 
        $resource->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);

        // hide warnings thrown on failed connection attempts
        @$resource->real_connect($hostname, $username, $passwd, $dbname, $port, $socket);

        if ($resource->connect_error) {
            throw new Exception\RuntimeException(
                'Connection error',
                null,
                new Exception\ErrorException($resource->connect_error, $resource->connect_errno)
            );
        }

        if (isset($charset) && !$resource->set_charset($charset)) {
            throw new Exception\InvalidConnectionParametersException(
                'Unable to set charset: "' . $charset . '"',
                $this->connectionParameters
            );
        }

        $this->resource = $resource;
    }

    /**
     * Is connected
     *
     * @return boolean
     */
    public function isConnected()
    {
        return ($this->resource instanceof \Mysqli);
    }

    /**
     * Disconnect
     */
    public function disconnect()
    {
        if ($this->resource instanceof \Mysqli) {
            $this->resource->close();
        }
        unset($this->resource);
    }

    /**
     * Begin transaction
     */
    public function beginTransaction()
    {
        $this->resource->autocommit(false);
        $this->inTransaction = true;
    }

    /**
     * Commit
     */
    public function commit()
    {
        if (!$this->resource) {
            $this->connect();
        }

        $this->resource->commit();

        $this->inTransaction = false;
    }

    /**
     * Rollback
     *
     * @return Connection
     */
    public function rollback()
    {
        if (!$this->resource) {
            throw new Exception\RuntimeException('Must be connected before you can rollback.');
        }

        if (!$this->inTransaction) {
            throw new Exception\RuntimeException('Must call commit() before you can rollback.');
        }

        $this->resource->rollback();
        return $this;
    }

    /**
     * Execute
     *
     * @param  string $sql
     * @return Result
     */
    public function execute($sql)
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $resultResource = $this->resource->query($sql);

        // if the returnValue is something other than a mysqli_result, bypass wrapping it
        if ($resultResource === false) {
            throw new Exception\InvalidQueryException($this->resource->error);
        }

        $resultPrototype = $this->driver->createResult(($resultResource === true) ? $this->resource : $resultResource);
        return $resultPrototype;
    }

    /**
     * Get last generated id
     *
     * @param  null $name Ignored
     * @return integer
     */
    public function getLastGeneratedValue($name = null)
    {
        return $this->resource->insert_id;
    }
}
