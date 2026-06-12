<?php

namespace Tests\Unit\Settings;

use App\Settings\McaSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class McaSettingTest extends TestCase
{
    public function test_set_name()
    {
        $setting = $this->makeSetting()->setName($name = 'Example name');

        $this->assertSame($name, $setting->getName());
    }

    public function test_set_validation_rules()
    {
        $setting = $this->makeSetting()->setValidationRules(['required', 'string']);

        $this->assertSettingValidationPasses($setting, 'foobar');
        $this->assertSettingValidationFails($setting, 4);
    }

    public function test_set_validation_rules_assoc()
    {
        $setting = $this->makeSetting()->setValidationRules([
            'test' => ['required', 'array'],
            'test.*' => ['required', 'int'],
            'invalid' => ['required', 'string'] // Should get discarded
        ]);

        $this->assertSettingValidationPasses($setting, ['test' => [1, 2, 3]]);
        $this->assertSettingValidationFails($setting, 4);
    }

    public function test_options_get_normalized()
    {
        $setting = $this->makeSetting()->setOptions(['foo']);
        $this->assertSame([['id' => 'foo', 'name' => 'Foo']], $setting->getOptions());

        $setting = $this->makeSetting()->setOptions([['id' => 'foo']]);
        $this->assertSame([['id' => 'foo', 'name' => 'Foo']], $setting->getOptions());

        $this->expectException(\RuntimeException::class);
        $this->makeSetting()->setOptions([['name' => 'Test']]);
    }

    public function test_set_options_singular_strict()
    {
        $setting = $this->makeSetting()->setOptions(['foo', 'bar', 'baz']);

        $this->assertSettingValidationPasses($setting, 'foo');

        $this->assertSettingValidationFails($setting, null);
        $this->assertSettingValidationFails($setting, 'rab');
        $this->assertSettingValidationFails($setting, ['rab']);
    }

    public function test_set_options_singular_not_strict()
    {
        $setting = $this->makeSetting()->setOptions(['foo', 'bar', 'baz'], strict: false);

        $this->assertSettingValidationPasses($setting, 'foo');
        $this->assertSettingValidationPasses($setting, 'rab');

        $this->assertSettingValidationFails($setting, null);
        $this->assertSettingValidationFails($setting, []);
        $this->assertSettingValidationFails($setting, ['foo']);
    }

    public function test_set_options_allowing_multiple_strict()
    {
        $setting = $this->makeSetting()->setOptions(['foo', 'bar', 'baz'], true);

        $this->assertSettingValidationPasses($setting, []);
        $this->assertSettingValidationPasses($setting, ['foo', 'bar']);

        $this->assertSettingValidationFails($setting, null);
        $this->assertSettingValidationFails($setting, ['']);
        $this->assertSettingValidationFails($setting, ['foo', 'rab']);
    }

    public function test_set_options_allowing_multiple_not_strict()
    {
        $setting = $this->makeSetting()->setOptions(['foo', 'bar', 'baz'], true, false);

        $this->assertSettingValidationPasses($setting, []);
        $this->assertSettingValidationPasses($setting, ['foo', 'bar', 'oof', 'rab']);

        $this->assertSettingValidationFails($setting, null);
        $this->assertSettingValidationFails($setting, ['']);
    }

    protected function makeSetting(): McaSetting
    {
        return new McaSetting('test', ['foo', 'bar']);
    }

    protected function assertSettingValidationPasses(McaSetting $setting, mixed $data)
    {
        $this->assertValidationPasses(
            (is_array($data) && Arr::isAssoc($data)) ? $data : [$setting->getKey() => $data],
            $setting->getValidationRules()
        );
    }

    protected function assertSettingValidationFails(McaSetting $setting, mixed $data)
    {
        $this->assertValidationFails(
            (is_array($data) && Arr::isAssoc($data)) ? $data : [$setting->getKey() => $data],
            $setting->getValidationRules()
        );
    }

    protected function assertValidationPasses($data, $rules)
    {
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    protected function assertValidationFails($data, $rules)
    {
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), $validator->errors()->toJson());
    }
}
