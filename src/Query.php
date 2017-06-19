<?php

namespace zobe\AmphpMysqliQuery2;

require __DIR__ . '/QueryInfo.php';
require __DIR__ . '/Result.php';
require __DIR__ . '/Exceptions.php';
require __DIR__ . '/QueryType.php';

use Amp;

class Query
{
    const TYPE_NORMAL = 0;
    const TYPE_EXEC_ONLY = 1;
    const TYPE_FIRST_ROW_ONLY = 2;
    const TYPE_FIRST_VALUE_ONLY = 3;

    // singleton...?
    protected static $singletons = [];

    protected $currentSql = '';

    /**
     * You can get Query instance
     *
     * @return mixed
     */
    public static function getSingleton( Amp\Loop\Driver $driver = null )
    {
        if( is_null($driver) )
        {
            $driver = Amp\Loop::get();
        }

        $id = spl_object_hash($driver);
        if( !array_key_exists( $id, self::$singletons ) )
        {
            self::$singletons[$id] = new Query($driver);
        }
        return self::$singletons[$id];
    }


    /**
     * @var QueryInfo[]
     */
    protected $queries;

    /**
     * @var \Amp\Loop\Driver
     */
    protected $driver;

    /**
     * @var string WatcherID of queryTick()
     */
    protected $loopWatcherId;

    /**
     * Constructor.
     *
     * Usually you should not use it but Query::getSingleton().
     *
     * If no driver has been set, automatically chosen default driver.
     *
     * @param \Amp\Loop\Driver|null $driver
     */
    function __construct(Amp\Loop\Driver $driver = null )
    {
        $this->driver = $driver;
        if( is_null($driver) )
            $this->driver = Amp\Loop::get();

        $this->queries = [];
        $this->loopWatcherId = null;
    }

    /**
     * Yield me to execute sql asynchronously.
     *
     * promise success or yield return value is always Result class
     *
     * @see QueryType
     * @param \mysqli $mysqli Target mysqli object. Pay attention to use 1 query for this at once.
     * @param string $sql
     * @param QueryType|null $queryType QueryType. Default value(null) will be treated as: QueryType::NORMAL
     * @return Amp\Promise
     */
    public function query(\mysqli $mysqli, string $sql, QueryType $queryType = null )
    {
        $id = spl_object_hash( $mysqli );
        if( array_key_exists( $id, $this->queries ) )
        {
            throw new \InvalidArgumentException( 'This mysqli object is already used for a query. You can use only one query at a time. If you want to execute 2 sqls at a time, 2 connections required.' );
        }
        if( is_null($queryType) )
            $queryType = QueryType::typeNormal();

        try {
            set_error_handler( [$this,'errorHandlerOnMysqliQuery'] );
            $this->currentSql = $sql;
            $mysqli->query($sql, MYSQLI_ASYNC);
        }
        finally
        {
            restore_error_handler();
        }

        $query = new QueryInfo();
        $query->setSql( $sql );
        $this->queries[$id] = $query;

        $query->setConnection( $mysqli );
        $query->setSql( $sql );
        $query->setQueryType( $queryType );

        if( is_null($this->loopWatcherId) )
        {
            $this->loopWatcherId = $this->driver->repeat( 10, [$this, 'tick'] );
        }

        return $query->getDefer()->promise();
    }

    /**
     * Yield me to execute sql asynchronously. same as query(,,QueryType::typeExecOnly())
     * The result will be discarded.
     *
     * unlike in the case of query(,,false), you are free from necessity to dispose mysqli_result.
     * So you can use it for insert, update, delete, create table... and so no.
     *
     * @param \mysqli $mysqli
     * @param string $sql
     * @return Amp\Promise
     */
    public function execOnly( \mysqli $mysqli, string $sql )
    {
        return $this->query( $mysqli, $sql, QueryType::typeExecOnly() );
    }

    /**
     * Yield me to execute sql asynchronously. same as query(,,QueryType::typeFirstRowOnly())
     * The result will be discarded without the first row.
     *
     * Promise success value or yield return value: array|null An array of the first row values, or null if no row has been returned.
     *
     * unlike in the case of query(,,false), you are free from necessity to dispose mysqli_result.
     *
     * @param \mysqli $mysqli
     * @param string $sql
     * @return Amp\Promise
     */
    public function getFirstRowOnly( \mysqli $mysqli, string $sql )
    {
        return $this->query( $mysqli, $sql, QueryType::typeFirstRowOnly() );
    }

    /**
     * Yield me to execute sql asynchronously. same as query(,,QueryType::typeFirstValueOnly())
     * The result will be discarded without the first value of the first row.
     *
     * Promise success value or yield return value: mixed|null The first value of the first row, or null if no row has been returned.
     *
     * unlike in the case of query(,,false), you are free from necessity to dispose mysqli_result.
     *
     * @param \mysqli $mysqli
     * @param string $sql
     * @return Amp\Promise
     */
    public function getFirstValueOnly( \mysqli $mysqli, string $sql )
    {
        return $this->query( $mysqli, $sql, QueryType::typeFirstValueOnly() );
    }

    public function errorHandlerOnMysqliPoll( $errno, $errstr, $errfile, $errline )
    {
//        if( $errno === 2 &&
//            strpos( $errstr, 'mysqli_poll' ) !== false &&
//            strpos( $errstr, "Couldn't fetch mysqli" ) !== false
//        )
//            throw new CouldntFetchMysqliException(
//                $errstr . ', file: ' . $errfile . ', line: ' . $errline . ', SQL: ' . $this->currentSql,
//                $errno);

        $currentSql = $this->currentSql;
        $this->currentSql = '';
        $e = new MysqliException(
            'Error at mysqli_poll(): ' . $errstr . ', file: ' . $errfile . ', line: ' . $errline . ', SQL: ' . $currentSql,
            $errno);
        $e->setSql( $currentSql );
        $e->setClassName('mysqli');
        $e->setMethodName('mysqli_poll');
        $e->setMysqliExceptionType( MysqliExceptionClassifier::createMysqliExceptionType('mysqli_poll', $errno ) );
        throw $e;
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

    public function errorHandlerOnMysqliReapAsyncQuery( $errno, $errstr, $errfile, $errline )
    {
        $currentSql = $this->currentSql;
        $this->currentSql = '';
        $e = new MysqliException(
            'Error at mysqli_reap_async_query(): ' . $errstr . ', file: ' . $errfile . ', line: ' . $errline . ', SQL: ' . $currentSql,
            $errno);
        $e->setSql( $currentSql );
        $e->setClassName('mysqli');
        $e->setMethodName('mysqli_reap_async_query');
        $e->setMysqliExceptionType( MysqliExceptionClassifier::createMysqliExceptionType('mysqli_reap_async_query', $errno ) );
        throw $e;
    }

    protected function destroyZombieConnections()
    {
        foreach( $this->queries as $id => $queryInfo )
        {
            $links = $errors = $rejects = [];
            $conn = $queryInfo->getConnection();
            $links[] = $errors[] = $rejects[] = $conn;

            try {
                set_error_handler( [$this,'errorHandlerOnMysqliPoll'] );
                $this->currentSql = $queryInfo->getSql();
                $poll_result = mysqli_poll($links, $errors, $rejects, 0);
            }
            catch( \Throwable $e )
            {
                unset( $this->queries[$id] );
                $err = new MysqliException(
                    'The query got error (@mysqli_poll() @destroyZomieConnections()): errno: ' . $e->getCode() .
                    ', error: '.  $e->getMessage() .
                    ', SQL: ' . $queryInfo->getSql(),
                    $e->getCode(),
                    $e
                );
                $err->setSql( $queryInfo->getSql() );
                $queryInfo->getDefer()->fail( $err );
            }
            finally
            {
                restore_error_handler();
            }
        }
    }

    /**
     * Do not call me. Automatically called if one or more queryinfo object(s) exist(s).
     */
    function tick()
    {
        $links = $errors = $rejects = [];
        $poll_result = false;

        foreach( $this->queries as $q )
        {
            $links[] = $errors[] = $rejects[] = $q->getConnection();
        }

        try {
            set_error_handler( [$this,'errorHandlerOnMysqliPoll'] );
            $poll_result = mysqli_poll($links, $errors, $rejects, 0);
        }
        catch( \Throwable $e )
        {
            $this->destroyZombieConnections();
            $this->terminateLoopIfNoQueryExists();
        }
        finally
        {
            restore_error_handler();
        }
        if( !$poll_result ) {
            // mysqli_poll() returns false therefore I suspend myself until next tick
            return;
        }

        if( count($errors) > 0 )
        {
            foreach( $errors as $error )
            {
                $id = spl_object_hash($error);
                $query = $this->queries[$id];
                unset( $this->queries[$id] );
                if( $error instanceof \mysqli && $error->error )
                {
                    $err = new MysqliException(
                        'The query got error: errno: ' . $error->error .
                        ', error: ' . $error->error .
                        ', SQL: ' . $query->getSql(),
                        $error->errno );
                    $err->setSql($query->getSql());
                    $query->getDefer()->fail($err);
                }
                else {
                    $err = new MysqliException('The query got error (@mysqli_poll()). SQL: ' . $query->getSql(), 50001);
                    $err->setSql($query->getSql());
                    $query->getDefer()->fail($err);
                }
            }
        }
        if( count($rejects) > 0 )
        {
            foreach( $rejects as $rej )
            {
                $id = spl_object_hash($rej);
                $query = $this->queries[$id];
                unset( $this->queries[$id] );
                if( $rej instanceof \mysqli && $rej->error )
                {
                    $err = new MysqliException(
                        'The query has been rejected (@mysqli_poll()): errno: ' . $rej->error .
                        ', error: ' . $rej->error .
                        ', SQL: ' . $query->getSql(),
                        $rej->errno );
                    $err->setSql($query->getSql());
                    $query->getDefer()->fail($err);
                }
                else {
                    $err = new MysqliException('The query has been rejected (@mysqli_poll()). SQL: ' . $query->getSql(), 50002);
                    $err->setSql($query->getSql());
                    $query->getDefer()->fail($err);
                }
            }
        }
        foreach( $links as $link )
        {
            $id = spl_object_hash($link);
            if( array_key_exists( $id, $this->queries ) ) {
                $query = $this->queries[$id];
                unset($this->queries[$id]);
            }
            else
                continue;

            $err = null;

            $result = null;
            try {
                $this->currentSql = $query->getSql();
                set_error_handler( [$this,'errorHandlerOnMysqliReapAsyncQuery'] );
                $result = mysqli_reap_async_query($link);
            }
            catch( \Throwable $e )
            {
                $err = $e;
            }
            finally
            {
                restore_error_handler();
            }

            if( !is_null($err) )
            {
                if( $err instanceof MysqliException ) {
                    $err->setSql( $query->getSql() );
                    $query->getDefer()->fail($err);
                }
                else
                {
                    $err = new MysqliException( 'NotCategorizedException at mysqli_reap_async_query() call in Query::tick(), errcode: ' . $err->getCode(), $err->getCode(), $err );
                    $err->setSql( $query->getSql() );
                    $query->getDefer()->fail($err);
                }
            }
            else if( $result )
            {
                if( $query->getQueryType()->isExecOnly() )
                {
                    $queryResult = new Result();
                    if( $result instanceof \mysqli_result )
                        mysqli_free_result( $result );
                    $queryResult->setSql($query->getSql());
                    $queryResult->setResultRaw($result);
                    $queryResult->setResult(null);
                    $query->getDefer()->resolve($queryResult);
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
                    $query->getDefer()->resolve($queryResult);
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
                    $query->getDefer()->resolve($queryResult);
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

                    $query->getDefer()->resolve($queryResult);
                }
            }
            else
            {
                $e = new MysqliException(mysqli_error($link),mysqli_errno($link));
                $e->setSql( $query->getSql() );
                $e->setClassName('mysqli');
                $e->setMethodName('mysqli_reap_async_query');
                $e->setMysqliExceptionType(
                    MysqliExceptionClassifier::createMysqliExceptionType('mysqli_reap_async_query',mysqli_errno($link) )
                );
                $query->getDefer()->fail($e);
            }
        }

//        foreach( $processedIds as $id )
//        {
//            unset( $this->queries[$id] );
//        }

        // terminate loop if no query exists
        $this->terminateLoopIfNoQueryExists();
    }

    protected function terminateLoopIfNoQueryExists()
    {
        if( count($this->queries) <= 0 ) {
            $this->driver->cancel( $this->loopWatcherId );
            $this->loopWatcherId = null;
        }
    }
}


