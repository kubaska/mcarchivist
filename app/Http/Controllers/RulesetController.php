<?php

namespace App\Http\Controllers;

use App\Models\Ruleset;
use App\Resources\RulesetResource;
use App\Services\RulesetService;
use Illuminate\Http\Request;

class RulesetController extends Controller
{
    public function __construct(private RulesetService $rulesetService)
    {
    }

    public function index()
    {
        $rulesets = Ruleset::query()->with('archive_rules')->get();

        return RulesetResource::collection($rulesets);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => ['required', 'string', 'min:3', 'max:200']
        ]);

        $ruleset = new Ruleset;
        $ruleset->name = $request->get('name');
        $ruleset->custom = true;
        $ruleset->save();

        $ruleset->setRelation('archive_rules', collect());

        return new RulesetResource($ruleset);
    }

    public function update($id, Request $request)
    {
        $this->validate($request, [
            'name' => ['required', 'string', 'min:3', 'max:200'],
            ...RulesetService::getRuleValidationRules()
        ]);

        $ruleset = Ruleset::query()->with('archive_rules')->findOrFail($id);

        $ruleset->name = $request->get('name');
        $ruleset->save();

        $this->rulesetService->saveRules($ruleset, collect($request->get('rules')), $ruleset->archive_rules);

        $ruleset->load('archive_rules');

        return new RulesetResource($ruleset);
    }

    public function destroy($id)
    {
        $ruleset = Ruleset::query()->findOrFail($id);

        $ruleset->archive_rules()->delete();
        $ruleset->delete();

        return response(null, 204);
    }
}
