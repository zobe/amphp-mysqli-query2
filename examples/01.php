<?php
/**
 * parallel execution test
 */

require '../vendor/autoload.php';
require_once '../src/Query.php';
require_once './config.php';


use \Amp\Loop;

$promises = [];

$promises[] = Amp\call(
    function()
    {
        $query = \zobe\AmphpMysqliQuery2\Query::getSingleton();
        $link = mysqli_connect( DB_HOST, DB_USER, DB_PASS, DB_NAME );
        $sql = 'select sleep(1)';
        echo $sql . ' start' . PHP_EOL;
        yield $query->execOnly( $link, $sql );
        echo $sql . ' end' . PHP_EOL;
        return '[sleep(1) executed]';
    }
);
$promises[] = Amp\call(
    function()
    {
        $query = \zobe\AmphpMysqliQuery2\Query::getSingleton();
        $link = mysqli_connect( DB_HOST, DB_USER, DB_PASS, DB_NAME );
        $sql = 'select sleep(2)';
        echo $sql . ' start' . PHP_EOL;
        yield $query->execOnly( $link, $sql );
        echo $sql . ' end' . PHP_EOL;
        return '[sleep(2) executed]';
    }
);
$promises[] = Amp\call(
    function()
    {
        $query = \zobe\AmphpMysqliQuery2\Query::getSingleton();
        $link = mysqli_connect( DB_HOST, DB_USER, DB_PASS, DB_NAME );
        $sql = 'select sleep(3)';
        echo $sql . ' start' . PHP_EOL;
        yield $query->execOnly( $link, $sql );
        echo $sql . ' end' . PHP_EOL;
        return '[sleep(3) executed]';
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
                    function( $reason, $value )
                    {
                        if( is_null($reason) )
                        {
                            echo 'resolved'.PHP_EOL;
                            echo 'result: ';
                            var_dump( $value );
                        }
                        else
                        {
                            echo 'failed'.PHP_EOL;
                            if( $reason instanceof \Throwable ) {
                                echo get_class($reason) . ', ';
                                echo $reason->getMessage() . PHP_EOL;
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

