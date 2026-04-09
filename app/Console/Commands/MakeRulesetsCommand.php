<?php

namespace App\Console\Commands;

use App\Enums\DependencyQualifier;
use App\Models\ArchiveRule;
use App\Models\Ruleset;
use DB;
use Illuminate\Console\Command;

class MakeRulesetsCommand extends Command
{
    protected $signature = 'mca:make-rulesets';

    protected $description = 'Makes example rulesets';

    protected array $defaultRulesets = [
        [
            'name' => 'All',
            'rules' => [
                [
                    'loader_id' => null,
                    'game_version_from' => '*',
                    'game_version_to' => null,
                    'with_snapshots' => true,
                    'release_type' => null,
                    'release_type_priority' => false,
                    'count' => 999,
                    'sorting' => 0,
                    'dependencies' => DependencyQualifier::ALL,
                    'all_files' => false
                ]
            ]
        ],
        [
            'name' => 'All (no snapshots)',
            'rules' => [
                [
                    'loader_id' => null,
                    'game_version_from' => '*',
                    'game_version_to' => null,
                    'with_snapshots' => false,
                    'release_type' => null,
                    'release_type_priority' => false,
                    'count' => 999,
                    'sorting' => 0,
                    'dependencies' => DependencyQualifier::ALL,
                    'all_files' => false
                ]
            ]
        ],
        [
            'name' => 'Latest version for every MC version',
            'rules' => [
                [
                    'loader_id' => null,
                    'game_version_from' => '*',
                    'game_version_to' => null,
                    'with_snapshots' => false,
                    'release_type' => null,
                    'release_type_priority' => false,
                    'count' => 1,
                    'sorting' => 0,
                    'dependencies' => DependencyQualifier::REQUIRED_ONLY,
                    'all_files' => false
                ]
            ]
        ]
    ];

    public function handle()
    {
        DB::transaction(function () {
            foreach ($this->defaultRulesets as $defaultRuleset) {
                /** @var Ruleset $ruleset */
                $ruleset = Ruleset::query()->firstOrCreate(['name' => $defaultRuleset['name'], 'custom' => false]);

                if ($ruleset->wasRecentlyCreated) {
                    $rules = [];

                    foreach ($defaultRuleset['rules'] as $defaultRule) {
                        $rule = new ArchiveRule;
                        foreach ($defaultRule as $k => $v) {
                            $rule->$k = $v;
                        }
                        $rules[] = $rule;
                    }

                    $ruleset->archive_rules()->saveMany($rules);
                }
            }
        });

        $this->info('Rulesets created successfully.');

        return 0;
    }
}
