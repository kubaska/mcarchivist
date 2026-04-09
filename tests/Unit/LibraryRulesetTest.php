<?php

namespace Tests\Unit;

use App\API\DTO\Library\LibraryRule;
use App\API\DTO\Library\LibraryRuleset;
use Tests\TestCase;

class LibraryRulesetTest extends TestCase
{
    /** @test */
    public function it_correctly_reflects_os_allowance()
    {
        $allowEverythingButOsx = $this->makeRuleset([
            ['action' => 'allow'],
            ['action' => 'disallow', 'os' => ['name' => 'osx']]
        ]);
        $this->assertOsesAllowance($allowEverythingButOsx, ['windows', 'linux'], ['osx']);

        $allowsOnlyOsx = $this->makeRuleset([
            ['action' => 'allow', 'os' => ['name' => 'osx']],
        ]);
        $this->assertOsesAllowance($allowsOnlyOsx, ['osx'], ['windows', 'linux']);

        $noRules = $this->makeRuleset([]);
        $this->assertOsesAllowance($noRules, ['windows', 'linux', 'osx'], []);
    }

    private function makeRuleset(array $rules): LibraryRuleset
    {
        return new LibraryRuleset(collect(array_map(fn(array $rule) => new LibraryRule(
            $rule['action'],
            data_get($rule, 'os.name'),
            data_get($rule, 'os.version')
        ), $rules)));
    }

    private function assertOsesAllowance(LibraryRuleset $ruleset, array $allowed, array $disallowed)
    {
        foreach ($allowed as $os) {
            $this->assertTrue($ruleset->allowsOS($os), sprintf('Expected OS %s to be allowed, but is disallowed.', $os));
        }

        foreach ($disallowed as $os) {
            $this->assertFalse($ruleset->allowsOS($os), sprintf('Expected OS %s to be disallowed, but is allowed.', $os));
        }
    }
}
