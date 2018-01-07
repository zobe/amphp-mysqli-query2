<?php

namespace zobe\AmphpMysqliQuery2;

require __DIR__ . '/QueryInfo.php';
require __DIR__ . '/Result.php';
require __DIR__ . '/Exceptions.php';
require __DIR__ . '/QueryType.php';


/**
 * Synchronous version of Query class for synchronous-asynchronous compatibility programming.
 *
 * @package zobe\AmphpMysqliQuery2
 */
class SynchronousQuery
{
    const TYPE_NORMAL = 0;
    const TYPE_EXEC_ONLY = 1;
    const TYPE_FIRST_ROW_ONLY = 2;
    const TYPE_FIRST_VALUE_ONLY = 3;

    protected $currentSql = '';

    /**
     * @var \mysqli|null
     */
    protected $mysqli = null;

    /**
     * Constructor.
     *
     * Usually you should not use it but Query::getSingleton().
     *
     * If no driver has been set, automatically chosen default driver.
     *
     * @param \mysqli $mysqli
     */
    function __construct( \mysqli $mysqli )
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Executes sql and returns Result class
     *
     * @see QueryType
     * @param string $sql
     * @param QueryType|null $queryType QueryType. Default value(null) will be treated as: QueryType::NORMAL
     */
    public function query(string $sql, QueryType $queryType = null )
    {
        $result = null;

        if( is_null($queryType) )
            $queryType = QueryType::typeNormal();

        try {
            set_error_handler( [$this,'errorHandlerOnMysqliQuery'] );
            $this->currentSql = $sql;
            $result = $this->mysqli->query($sql);
        }
        finally
        {
            restore_error_handler();
        }

        $query = new QueryInfo();
        $query->setSql( $sql );

        $query->setConnection( $this->mysqli );
        $query->setSql( $sql );
        $query->setQueryType( $queryType );

        if( $query->getQueryType()->isExecOnly() )
        {
            $queryResult = new Result();
            if( $result instanceof \mysqli_result )
                mysqli_free_result( $result );
            $queryResult->setSql($query->getSql());
            $queryResult->setResultRaw($result);
            $queryResult->setResult(null);
            return $queryResult;
        }
        else if( $query->getQueryType()->isFirstRowOnly() )
        {
            $queryResult = new Result();
            $queryResult->setSql($query->getSql());
            $queryResult->setResultRaw($result);

            $queryResult->setResult( null );
            if( $result instanceof \mysqli_result ) {
                try {
                    $row = mysqli_fetch_row($result);
                    if (!is_null($row) && is_array($row) && isset($row[0]))
                        $queryResult->setResult($row);
                }
                finally {
                    mysqli_free_result($result);
                }
            }
            return $queryResult;
        }
        else if( $query->getQueryType()->isFirstValueOnly() )
        {
            $queryResult = new Result();
            $queryResult->setSql($query->getSql());
            $queryResult->setResultRaw($result);

            $queryResult->setResult( null );
            if( $result instanceof \mysqli_result ) {
                try {
                    $row = mysqli_fetch_row($result);
                    if (!is_null($row) && is_array($row) && isset($row[0]))
                        $queryResult->setResult($row[0]);
                }
                finally {
                    mysqli_free_result($result);
                }
            }
            return $queryResult;
        }
        else // if( $query->$queryType()->isNormal() )
        {
            $queryResult = new Result();
            $queryResult->setSql($query->getSql());

            $queryResult->setResultRaw($result);
            if ($result instanceof \mysqli_result)
                $queryResult->setResult($result);
            else
                $queryResult->setResult(null);

            return $queryResult;
        }
    }

    /**
     * Yield me to execute sql asynchronously. same as query(,,QueryType::typeExecOnly())
     * The result will be discarded.
     *
     * unlike in the case of query(,,false), you are free from necessity to dispose mysqli_result.
     * So you can use it for insert, update, delete, create table... and so no.
     *
     * @param string $sql
     * @return Result
     */
    public function execOnly( string $sql )
    {
        return $this->query( $sql, QueryType::typeExecOnly() );
    }

    /**
     * Yield me to execute sql asynchronously. same as query(,,QueryType::typeFirstRowOnly())
     * The result will be discarded without the first row.
     *
     * Promise success value or yield return value: array|null An array of the first row values, or null if no row has been returned.
     *
     * unlike in the case of query(,,false), you are free from necessity to dispose mysqli_result.
     *
     * @param string $sql
     * @return Result
     */
    public function getFirstRowOnly( string $sql )
    {
        return $this->query( $sql, QueryType::typeFirstRowOnly() );
    }

    /**
     * Yield me to execute sql asynchronously. same as query(,,QueryType::typeFirstValueOnly())
     * The result will be discarded without the first value of the first row.
     *
     * Promise success value or yield return value: mixed|null The first value of the first row, or null if no row has been returned.
     *
     * unlike in the case of query(,,false), you are free from necessity to dispose mysqli_result.
     *
     * @param string $sql
     * @return Result
     */
    public function getFirstValueOnly( string $sql )
    {
        return $this->query( $sql, QueryType::typeFirstValueOnly() );
    }

    public function errorHandlerOnMysqliQuery( $errno, $errstr, $errfile, $errline )
    {
        $currentSql = $this->currentSql;
        $this->currentSql = '';
        $e = new MysqliException(
            'Error at mysqli_query(): ' . $errstr . ', file: ' . $errfile . ', line: ' . $errline . ', SQL: ' . $currentSql,
            $errno);
        $e->setSql( $currentSql );
        $e->setClassName('mysqli');
        $e->setMethodName('mysqli_query');
        $e->setMysqliExceptionType( MysqliExceptionClassifier::createMysqliExceptionType('mysqli_query', $errno ) );
        throw $e;
    }
}


