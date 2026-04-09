<?php

namespace Tests\Unit;

use App\Support\Utils;
use Tests\TestCase;

class UtilsTest extends TestCase
{
    public function test_common_hash()
    {
        $this->assertSame('sha1', Utils::findCommonHashAlgo([['sha1']]));
        $this->assertSame('sha1', Utils::findCommonHashAlgo([['sha1', 'sha256']]));
        $this->assertSame('sha256', Utils::findCommonHashAlgo([['sha1', 'sha256'], ['sha256', 'sha512']]));
        $this->assertSame(null, Utils::findCommonHashAlgo([['sha1', 'sha256'], ['md5', 'sha512']]));
        $this->assertSame(null, Utils::findCommonHashAlgo([['foo', 'bar']]));
    }
}
