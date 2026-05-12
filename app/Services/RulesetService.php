<?php

namespace App\Services;

use App\Enums\DependencyQualifier;
use App\Enums\VersionType;
use App\Models\ArchiveRule;
use App\Models\GameVersion;
use App\Models\Loader;
use App\Rules\PresentWithoutRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class RulesetService
{
    public static function getRuleValidationRules(): array
    {
        return [
            'rules' => ['array'],
            'rules.*.id' => ['sometimes', 'integer'],
            'rules.*.count' => ['required', 'integer', 'min:1', 'max:99'],
            'rules.*.sorting' => ['required', 'boolean'],
            'rules.*.release_type' => ['required', Rule::in([
                '*', ...array_map(fn(VersionType $type) => (string)$type->value, VersionType::getBasic())
            ])],
            'rules.*.release_type_priority' => ['required', 'boolean'],
            'rules.*.game_version_from' => ['required', 'string'],
            'rules.*.game_version_to' => ['nullable', 'string'],
            'rules.*.with_snapshots' => ['required', 'boolean'],
            'rules.*.loader_id' => ['required'],
            'rules.*.dependencies' => ['required', new Enum(DependencyQualifier::class)],
            'rules.*.all_files' => ['required', 'boolean']
        ];
    }

    public static function getRuleWithRulesetValidationRules(): array
    {
        return [
            'ruleset_id' => ['integer', new PresentWithoutRule('rules'), 'exists:rulesets,id'],
            ...self::getRuleValidationRules(),
            'rules' => ['array', new PresentWithoutRule('ruleset_id')]
        ];
    }

    public function saveRules(Model $model, Collection $rules, Collection $existingRules): Model
    {
        if (! method_exists($model, 'archive_rules'))
            throw new \RuntimeException('Model '.get_class($model).'does not implement archive_rules relation');

        // Separate new, to be updated and to be deleted
        list($toUpdate, $new) = $rules->partition(function (array $rule) use ($existingRules) {
            if ($id = data_get($rule, 'id')) return $existingRules->firstWhere('id', $id);
            return false;
        });
        $toRemove = $existingRules->whereNotIn('id', Arr::pluck($toUpdate, 'id'));

        $new = collect($new)->map(fn(array $rule) => $this->updateRule(new ArchiveRule, $rule));
        $model->archive_rules()->saveMany($new);

        collect($toUpdate)
            ->map(fn(array $rule) => $this->updateRule($existingRules->firstWhere('id', $rule['id']), $rule))
            ->each(fn(ArchiveRule $rule) => $rule->save());

        $toRemove->each(fn(ArchiveRule $rule) => $rule->delete());

        return $model;
    }

    private function updateRule(ArchiveRule $targetRule, array $rule): ArchiveRule
    {
        $gameVersionFrom = null;
        $gameVersionTo = null;

        if ($rule['game_version_from'] === '*') $targetRule->game_version_from = '*';
        else {
            $gameVersionFrom = $this->validateGameVersion($rule['game_version_from'], !!$rule['with_snapshots']);
            $targetRule->game_version_from = $gameVersionFrom->name;
        }

        if ($rule['game_version_to']) {
            if ($rule['game_version_to'] === '*') $targetRule->game_version_to = '*';
            else {
                $gameVersionTo = $this->validateGameVersion($rule['game_version_to'], !!$rule['with_snapshots']);
                $targetRule->game_version_to = $gameVersionTo->name;
            }
        }

        // If user selects game version range, and they're not ordered by release date, swap them
        if ($gameVersionFrom && $gameVersionTo) {
            if ($gameVersionTo->released_at->isBefore($gameVersionFrom->released_at)) {
                $targetRule->game_version_from = $gameVersionTo->name;
                $targetRule->game_version_to = $gameVersionFrom->name;
            }
        }

        if ($rule['loader_id'] === '*') $targetRule->loader_id = null;
        else {
            $loader = Loader::find($rule['loader_id']);
            if ($loader === null)
                abort(422, ['error' => 'Loader was not found']);
            $targetRule->loader_id = $loader->id;
        }

        $targetRule->count = $rule['count'];
        $targetRule->sorting = $rule['sorting'];
        $targetRule->with_snapshots = $rule['with_snapshots'];
        $targetRule->release_type = $rule['release_type'] === '*' ? null : VersionType::from($rule['release_type']);
        $targetRule->release_type_priority = $rule['release_type_priority'];
        $targetRule->dependencies = DependencyQualifier::from($rule['dependencies']);
        $targetRule->all_files = $rule['all_files'];

        return $targetRule;
    }

    private function validateGameVersion(string $version, bool $withSnapshots): GameVersion
    {
        $gameVersion = GameVersion::firstWhere('name', $version);

        if (! $gameVersion) {
            abort(422, ['error' => sprintf('Version %s was not found', $version)]);
        }

        if ($withSnapshots && $gameVersion->type === VersionType::SNAPSHOT) {
            abort(422, ['error' => sprintf('Snapshot inclusion was turned off, but version %s is a snapshot', $version)]);
        }

        return $gameVersion;
    }
}
