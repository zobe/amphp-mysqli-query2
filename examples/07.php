<?php
/**
 * getting results sample 2
 *
 * $result = yield $query->getFirstValueOnly( $link, $sql );
 * You can get a value or null by $result->getResult()
 *
 * $result = yield $query->getFirstRowOnly( $link, $sql );
 * You can get an array of the first row or null by $result->getResult()
 *
 * Unlike in the case of $query->query(,,default=QueryType::typeNormal()),
 * \mysqli_result object is automatically disposed and there is no need to take care them.
 *
 */

require '../vendor/autoload.php';
require_once '../src/Query.php';
require_once './config.php';


$query = new \zobe\AmphpMysqliQuery2\Query();


$promises = [];
$promises[] = Amp\call(
    function() use ($query)
    {
        $link = mysqli_connect( DB_HOST, DB_USER, DB_PASS, DB_NAME );
        $sql = 'select 1+2+3+4+5+6+7+8+9+10';
        echo $sql . ' start' . PHP_EOL;
        $ret = null;

        try {
            $result = yield $query->getFirstValueOnly($link, $sql);

            assert( $result instanceof \zobe\AmphpMysqliQuery2\Result );

            if (!is_null($result->getResult())) {
                $ret = $result->getResult();
            } else {
                $ret = null;
            }
        }
        catch( \Throwable $e )
        {
            throw $e;
        }

        echo $sql . ' end' . PHP_EOL;
        if( is_null($ret) )
            throw new Exception( 'No result?' );
        return $ret;
    }
);
$promises[] = Amp\call(
    function() use ($query)
    {
        $link = mysqli_connect( DB_HOST, DB_USER, DB_PASS, DB_NAME );
        $sql = 'select 1,2,3,4,5,6,7,8,9,10';
        echo $sql . ' start' . PHP_EOL;
        $ret = null;

        try {
            $result = yield $query->getFirstRowOnly($link, $sql);
            if( $result instanceof \zobe\AmphpMysqliQuery2\Result ) {
                if (!is_null($result->getResult())) {
                    $ret = $result->getResult();
                } else {
                    $ret = null;
                }
            }
        }
        catch( \Throwable $e )
        {
            throw $e;
        }

        echo $sql . ' end' . PHP_EOL;
        if( is_null($ret) )
            throw new Exception( 'No result?' );
        return $ret;
    }
);



Amp\Loop::run(
    function() use ($promises)
    {
        $startTime = microtime(true);

        // how to wait for each promise
        foreach( $promises as $p )
        {
            if( $p instanceof \Amp\Promise )
                $p->onResolve(
                    function( $e, $r )
                    {
                        if( is_null($e) )
                        {
                            echo 'resolved'.PHP_EOL;
                            echo 'result: ';
                            var_dump( $r );
                        }
                        else
                        {
                            echo 'failed'.PHP_EOL;
                            if( $e instanceof \Throwable ) {
                                echo get_class($e) . ', ';
                                echo $e->getMessage() . PHP_EOL;
                            }
                        }
                    }
                );
        }

        // how to wait all promises
        Amp\Promise\any($promises)->onResolve(
            function() use ($startTime)
            {
                $endTime = microtime(true);
                echo 'all queries completed'.PHP_EOL;
                echo 'elapsed time: ' . ($endTime - $startTime) . PHP_EOL;
            } );
    }
);


