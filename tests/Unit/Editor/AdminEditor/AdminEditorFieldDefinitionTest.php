<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Editor\AdminEditor;

use Closure;
use InvalidArgumentException;
use Lemonade\Admin\Editor\AdminEditor\Field\ActionSelectField;
use Lemonade\Admin\Editor\AdminEditor\Field\AdminEditorFieldDefinition;
use Lemonade\Admin\Editor\AdminEditor\Field\AdminEditorInputFieldDefinition;
use Lemonade\Admin\Editor\AdminEditor\Field\AdminEditorStatusVariant;
use Lemonade\Admin\Editor\AdminEditor\Field\BooleanField;
use Lemonade\Admin\Editor\AdminEditor\Field\Field;
use Lemonade\Admin\Editor\AdminEditor\Field\HiddenField;
use Lemonade\Admin\Editor\AdminEditor\Field\NativeInputType;
use Lemonade\Admin\Editor\AdminEditor\Field\ReadonlyDisplayField;
use Lemonade\Admin\Editor\AdminEditor\Field\ReadonlyEditableField;
use Lemonade\Admin\Editor\AdminEditor\Field\SelectField;
use Lemonade\Admin\Editor\AdminEditor\Field\StatusDisplayField;
use Lemonade\Admin\Editor\AdminEditor\Field\TextareaField;
use Lemonade\Admin\Editor\AdminEditor\Field\TextInputField;
use Lemonade\Admin\Editor\AdminEditor\Field\UrlSegmentField;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Overuje typove bezpecny a immutable verejny Field API
 */
final class AdminEditorFieldDefinitionTest extends TestCase
{
    /**
     * Overi vsechny factory a jejich konkretni navratove typy
     *
     * @param Closure(): AdminEditorFieldDefinition $factory
     * @param class-string<AdminEditorFieldDefinition> $expectedClass
     */
    #[DataProvider('fieldFactoryProvider')]
    public function testFactoriesReturnConcreteFieldTypes(Closure $factory, string $expectedClass): void
    {
        $field = $factory();

        self::assertInstanceOf($expectedClass, $field);
        self::assertSame('field_name', $field->name());
    }

    /**
     * Poskytne vsechny verejne Field factory
     *
     * @return iterable<string, array{Closure(): AdminEditorFieldDefinition, class-string<AdminEditorFieldDefinition>}>
     */
    public static function fieldFactoryProvider(): iterable
    {
        yield 'text' => [static fn(): TextInputField => Field::text('field_name'), TextInputField::class];
        yield 'email' => [static fn(): TextInputField => Field::email('field_name'), TextInputField::class];
        yield 'tel' => [static fn(): TextInputField => Field::tel('field_name'), TextInputField::class];
        yield 'number' => [static fn(): TextInputField => Field::number('field_name'), TextInputField::class];
        yield 'datetime local' => [static fn(): TextInputField => Field::datetimeLocal('field_name'), TextInputField::class];
        yield 'textarea' => [static fn(): TextareaField => Field::textarea('field_name'), TextareaField::class];
        yield 'select' => [static fn(): SelectField => Field::select('field_name'), SelectField::class];
        yield 'action select' => [static fn(): ActionSelectField => Field::actionSelect('field_name', 'assign-owner', actionLabel: 'Assign'), ActionSelectField::class];
        yield 'checkbox' => [static fn(): BooleanField => Field::checkbox('field_name'), BooleanField::class];
        yield 'toggle' => [static fn(): BooleanField => Field::toggle('field_name'), BooleanField::class];
        yield 'hidden' => [static fn(): HiddenField => Field::hidden('field_name'), HiddenField::class];
        yield 'readonly display' => [static fn(): ReadonlyDisplayField => Field::readonlyDisplay('field_name'), ReadonlyDisplayField::class];
        yield 'status display' => [static fn(): StatusDisplayField => Field::statusDisplay('field_name'), StatusDisplayField::class];
        yield 'readonly editable' => [static fn(): ReadonlyEditableField => Field::readonlyEditable('field_name'), ReadonlyEditableField::class];
        yield 'URL segment' => [static fn(): UrlSegmentField => Field::urlSegment('field_name'), UrlSegmentField::class];
    }

    /** Overi presne nativni input typy sdilene jednou rodinou */
    public function testNativeInputFactoriesKeepTheirHtmlTypes(): void
    {
        self::assertSame(NativeInputType::Text, Field::text('field')->inputType());
        self::assertSame(NativeInputType::Email, Field::email('field')->inputType());
        self::assertSame(NativeInputType::Tel, Field::tel('field')->inputType());
        self::assertSame(NativeInputType::Number, Field::number('field')->inputType());
        self::assertSame(NativeInputType::DatetimeLocal, Field::datetimeLocal('field')->inputType());
    }

    /** Overi immutable chaining spolecne a vstupni konfigurace */
    public function testCommonAndInputFluentMethodsReturnConfiguredCopies(): void
    {
        $original = Field::text('title');
        $configured = $original
            ->label('Title')
            ->labelKey('fields.title')
            ->help('Help', 'fields.help')
            ->defaultValue(null)
            ->placeholder('Placeholder', 'fields.placeholder')
            ->autocomplete('off')
            ->required()
            ->readonly()
            ->disabled()
            ->attributes(['maxlength' => 180]);

        self::assertNotSame($original, $configured);
        self::assertSame('', $original->labelText());
        self::assertFalse($original->isRequired());
        self::assertSame('Title', $configured->labelText());
        self::assertSame('fields.title', $configured->labelTranslationKey());
        self::assertSame('Help', $configured->helpText());
        self::assertSame('fields.help', $configured->helpTranslationKey());
        self::assertTrue($configured->hasDefaultValue());
        self::assertNull($configured->structuralDefaultValue());
        self::assertSame('Placeholder', $configured->placeholderText());
        self::assertSame('fields.placeholder', $configured->placeholderKey());
        self::assertSame('off', $configured->autocompleteValue());
        self::assertTrue($configured->isRequired());
        self::assertTrue($configured->isReadonly());
        self::assertTrue($configured->isDisabled());
        self::assertSame(['maxlength' => 180], $configured->inputAttributes());
    }

    /** Overi capability pouze na rodinach kterym patri */
    public function testConcreteFamiliesExposeOnlyRelevantCapabilities(): void
    {
        $text = new ReflectionClass(TextInputField::class);
        $select = new ReflectionClass(SelectField::class);
        $textarea = new ReflectionClass(TextareaField::class);
        $status = new ReflectionClass(StatusDisplayField::class);
        $url = new ReflectionClass(UrlSegmentField::class);

        self::assertFalse($text->hasMethod('multiple'));
        self::assertFalse($text->hasMethod('richText'));
        self::assertFalse($select->hasMethod('richText'));
        self::assertFalse($status->hasMethod('required'));
        self::assertTrue($select->hasMethod('options'));
        self::assertTrue($select->hasMethod('multiple'));
        self::assertTrue($textarea->hasMethod('richText'));
        self::assertTrue($url->hasMethod('source'));
        self::assertTrue($url->hasMethod('action'));
        self::assertFalse($text->hasMethod('value'));
        self::assertFalse($text->hasMethod('errors'));
        self::assertFalse($text->hasMethod('span'));
    }

    /** Overi type-specific immutable konfiguraci selectu, rich-textu a URL */
    public function testTypeSpecificCapabilitiesRemainImmutableAndTyped(): void
    {
        $select = Field::select('tags')->options(['a' => 'A'])->optionLabelKeys(['a' => 'tags.a'])->multiple();
        $textarea = Field::textarea('content')->richText(imageUpload: true, fileUpload: true);
        $url = Field::urlSegment('slug')->prefix('/pages/')->source('title', autoGenerate: true)->action('change-url', 'Save URL');

        self::assertSame(['a' => 'A'], $select->selectOptions());
        self::assertSame(['a' => 'tags.a'], $select->selectOptionLabelKeys());
        self::assertTrue($select->isMultiple());
        self::assertTrue($textarea->isRichText());
        self::assertTrue($textarea->hasImageUpload());
        self::assertTrue($textarea->hasFileUpload());
        self::assertSame('/pages/', $url->pathPrefix());
        self::assertSame('title', $url->sourceField());
        self::assertTrue($url->autoGenerates());
        self::assertSame('change-url', $url->actionKey());
        self::assertSame('Save URL', $url->actionText());
        self::assertSame('admin.url_segment.save', $url->actionTranslationKey());
    }

    /** Overi defaults specialnich prezentacnich fieldu */
    public function testPresentationFieldDefaultsRemainStable(): void
    {
        $status = Field::statusDisplay('status');
        $editable = Field::readonlyEditable('code');
        $url = Field::urlSegment('slug');

        self::assertSame(AdminEditorStatusVariant::Muted, $status->variant());
        self::assertTrue($status->displaysIndicator());
        self::assertTrue($editable->isReadonly());
        self::assertSame('admin.url_segment.label', $url->labelTranslationKey());
        self::assertSame('', $url->helpText());
        self::assertSame('admin.url_segment.help', $url->helpTranslationKey());
    }

    /** Overi final readonly konkretni objekty a abstraktni readonly baze */
    public function testFieldHierarchyKeepsItsReadonlyContract(): void
    {
        foreach ([
            TextInputField::class,
            BooleanField::class,
            HiddenField::class,
            TextareaField::class,
            SelectField::class,
            ActionSelectField::class,
            ReadonlyDisplayField::class,
            StatusDisplayField::class,
            ReadonlyEditableField::class,
            UrlSegmentField::class,
        ] as $class) {
            $reflection = new ReflectionClass($class);
            self::assertTrue($reflection->isFinal(), $class);
            self::assertTrue($reflection->isReadOnly(), $class);
        }

        self::assertTrue((new ReflectionClass(AdminEditorFieldDefinition::class))->isAbstract());
        self::assertTrue((new ReflectionClass(AdminEditorFieldDefinition::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(AdminEditorInputFieldDefinition::class))->isAbstract());
        self::assertTrue((new ReflectionClass(AdminEditorInputFieldDefinition::class))->isReadOnly());
    }

    /** Overi invariant prazdneho transportniho nazvu */
    public function testFieldNameMustNotBeEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field name must not be empty.');

        Field::text('  ');
    }

    /** Overi invariant neprazdneho autocomplete */
    public function testAutocompleteMustNotBeEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Autocomplete value must not be empty.');

        Field::text('title')->autocomplete('  ');
    }

    /**
     * Overi upload capability az po aktivaci rich-textu
     *
     * @param Closure(): void $enableUpload
     */
    #[DataProvider('richTextUploadProvider')]
    public function testOnlyRichTextMayEnableUploads(Closure $enableUpload, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $enableUpload();
    }

    /**
     * Poskytne neplatne aktivace rich-text upload capabilities
     *
     * @return iterable<string, array{Closure(): void, string}>
     */
    public static function richTextUploadProvider(): iterable
    {
        yield 'image' => [static function (): void {
            Field::textarea('content')->imageUpload();
        }, 'Only rich text textarea fields may enable image upload.'];
        yield 'file' => [static function (): void {
            Field::textarea('content')->fileUpload();
        }, 'Only rich text textarea fields may enable file upload.'];
    }

    /** Overi neprazdne URL source pole */
    public function testUrlSourceFieldMustNotBeEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('URL segment source field must not be empty.');

        Field::urlSegment('slug')->source('  ');
    }

    /** Overi neprazdny klic URL action */
    public function testUrlActionKeyMustNotBeEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('URL change action key must not be empty.');

        Field::urlSegment('slug')->action('  ');
    }

    /** Overi dostupnost labelu URL action */
    public function testUrlActionRequiresLabelOrKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('URL change action label or label key must not be empty.');

        Field::urlSegment('slug')->action('change-url', '  ', null);
    }
}
