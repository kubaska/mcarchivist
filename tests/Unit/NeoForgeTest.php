<?php

namespace Tests\Unit;

use App\API\Loader\NeoForge;
use App\Enums\VersionType;
use Tests\TestCase;

class NeoForgeTest extends TestCase
{
    /** @test */
    public function it_extracts_game_version_from_loader_version_name()
    {
        $nf = app(NeoForge::class);

        // "old"
        $this->assertSame(['1.20.2', VersionType::BETA], $nf->extractGameVersion('20.2.55-beta'));
        $this->assertSame(['1.20.2', VersionType::RELEASE], $nf->extractGameVersion('20.2.89'));
        $this->assertSame(['1.21.10', VersionType::RELEASE], $nf->extractGameVersion('21.10.64'));
        $this->assertSame(['25w14craftmine', VersionType::BETA], $nf->extractGameVersion('0.25w14craftmine.3-beta'));
        $this->assertSame(['25w14craftmine', VersionType::RELEASE], $nf->extractGameVersion('0.25w14craftmine.3'));

        // "new"
        $this->assertSame(['25.4', VersionType::RELEASE], $nf->extractGameVersion('25.4.0.123'));
        $this->assertSame(['25.4', VersionType::BETA], $nf->extractGameVersion('25.4.0.0-beta'));
        $this->assertSame(['25.4', VersionType::ALPHA], $nf->extractGameVersion('25.4.0.0-alpha.1'));
        $this->assertSame(['25.4-rc-2', VersionType::ALPHA], $nf->extractGameVersion('25.4.0.0-alpha.1+rc-2'));
        $this->assertSame(['25.4-snapshot-1', VersionType::ALPHA], $nf->extractGameVersion('25.4.0.0-alpha.1+snapshot-1'));
    }
}
