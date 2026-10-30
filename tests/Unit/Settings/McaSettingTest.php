<?php

namespace Tests\Unit\Settings;

use App\Rules\ArrayStrictRule;
use App\Settings\McaSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;
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

    public function test_it_fails_when_trying_to_set_options_from_not_an_enum()
    {
        $this->expectException(\TypeError::class);

        $this->makeSetting()->setOptionsFromEnum(TestCase::class);
    }

    public function test_it_fails_when_trying_to_set_options_from_basic_enum()
    {
        $this->expectException(\TypeError::class);

        $this->makeSetting()->setOptionsFromEnum(TestBasicEnum::class);
    }

    public function test_set_options_from_backed_enum()
    {
        $setting = $this->makeSetting()->setOptionsFromEnum(TestBackedEnum::class);

        $this->assertEquals(
            [['id' => TestBackedEnum::TEST->value, 'name' => TestBackedEnum::TEST->name]],
            $setting->getOptions()
        );

        $this->assertEquals(
            ['test' => ['required', new Enum(TestBackedEnum::class)]],
            $setting->getValidationRules()
        );

        $this->assertSettingValidationPasses($setting, TestBackedEnum::TEST->value);

        $this->assertSettingValidationFails($setting, null);
        $this->assertSettingValidationFails($setting, '');
        $this->assertSettingValidationFails($setting, '123');
    }

    public function test_set_options_from_int_backed_enum()
    {
        $setting = $this->makeSetting()->setOptionsFromEnum(TestIntBackedEnum::class);

        $this->assertEquals(
            [['id' => TestIntBackedEnum::TEST->value, 'name' => TestIntBackedEnum::TEST->name]],
            $setting->getOptions()
        );

        $this->assertSettingValidationPasses($setting, TestIntBackedEnum::TEST->value);

        $this->assertSettingValidationFails($setting, null);
        $this->assertSettingValidationFails($setting, '');
        $this->assertSettingValidationFails($setting, '123');
    }

    public function test_set_options_from_int_backed_enum_uses_setting_name_method()
    {
        $setting = $this->makeSetting()->setOptionsFromEnum(TestEnumWithSettingNameMethod::class);

        $this->assertEquals(
            [['id' => TestEnumWithSettingNameMethod::TEST->value, 'name' => TestEnumWithSettingNameMethod::TEST->settingName()]],
            $setting->getOptions()
        );

        $this->assertSettingValidationPasses($setting, TestEnumWithSettingNameMethod::TEST->value);

        $this->assertSettingValidationFails($setting, null);
        $this->assertSettingValidationFails($setting, '');
        $this->assertSettingValidationFails($setting, '123');
    }

    public function test_set_options_from_int_backed_enum_uses_name_method()
    {
        $setting = $this->makeSetting()->setOptionsFromEnum(TestEnumWithNameMethod::class);

        $this->assertEquals(
            [['id' => TestEnumWithNameMethod::TEST->value, 'name' => TestEnumWithNameMethod::TEST->name()]],
            $setting->getOptions()
        );

        $this->assertSettingValidationPasses($setting, TestEnumWithNameMethod::TEST->value);

        $this->assertSettingValidationFails($setting, null);
        $this->assertSettingValidationFails($setting, '');
        $this->assertSettingValidationFails($setting, '123');
    }

    public function test_set_options_from_backed_enum_multiple()
    {
        $setting = $this->makeSetting()->setOptionsFromEnum(TestIntBackedEnum::class, true);

        $this->assertEquals(
            [['id' => TestIntBackedEnum::TEST->value, 'name' => TestIntBackedEnum::TEST->name]],
            $setting->getOptions()
        );

        $this->assertEquals(
            [
                'test' => [new ArrayStrictRule, 'required'],
                'test.*' => [new Enum(TestIntBackedEnum::class)]
            ],
            $setting->getValidationRules()
        );

        $this->assertSettingValidationPasses($setting, [TestIntBackedEnum::TEST->value]);

        $this->assertSettingValidationFails($setting, null);
        $this->assertSettingValidationFails($setting, '');
        $this->assertSettingValidationFails($setting, '123');
        $this->assertSettingValidationFails($setting, []);
        $this->assertSettingValidationFails($setting, [123]);
        $this->assertSettingValidationFails($setting, ['123']);
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

enum TestBasicEnum {
    case TEST;
}

enum TestBackedEnum: string {
    case TEST = 'test';
}

enum TestIntBackedEnum: int {
    case TEST = 0;
}

enum TestEnumWithSettingNameMethod: int {
    case TEST = 0;

    public function settingName(): string
    {
        return match ($this) {
            self::TEST => 'Example setting'
        };
    }
}

enum TestEnumWithNameMethod: int {
    case TEST = 0;

    public function name(): string
    {
        return match ($this) {
            self::TEST => 'Test case'
        };
    }
}
