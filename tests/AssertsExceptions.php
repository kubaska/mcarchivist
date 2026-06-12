<?php

namespace Tests;

trait AssertsExceptions
{
    /**
     * Assert given callback throws a specified exception.
     *
     * @param string $exception
     * @param callable $callback
     */
    public function assertException(string $exception, callable $callback)
    {
        $caughtException = null;

        try {
            $callback();
        } catch (\Exception $e) {
            $caughtException = $e;
        }

        if ($caughtException === null) {
            $this->fail('Test did not throw an exception: '.$exception);
        }

        $result = $exception === get_class($caughtException);

        if (! $result) {
            print(PHP_EOL);
            print(get_class($caughtException).': '.$caughtException->getMessage().PHP_EOL);
            print('Stacktrace: '.PHP_EOL.$caughtException->getTraceAsString().PHP_EOL);

            $this->fail(
                'Test threw different exception than expected.'.PHP_EOL.
                'Expected : '.$exception.PHP_EOL.
                'Actual   : '.get_class($caughtException)
            );
        }

        $this->assertTrue($result);
    }
}
