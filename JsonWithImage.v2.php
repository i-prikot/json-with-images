<?php

declare(strict_types=1);

namespace App\MoonShine\Fields;

use Closure;
use Illuminate\Support\Str;
use Nyholm\Psr7\UploadedFile;
use MoonShine\UI\Fields\Field;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Hidden;
use Illuminate\Support\Collection;
use MoonShine\UI\Traits\Removable;
use MoonShine\UI\Traits\WithFields;
use MoonShine\UI\Collections\Fields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use MoonShine\UI\Components\FieldsGroup;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\UI\Components\ActionButton;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\UI\Contracts\RemovableContract;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Traits\Fields\HasVerticalMode;
use MoonShine\Contracts\UI\TableBuilderContract;
use MoonShine\Contracts\Core\DependencyInjection\FieldsContract;

class JsonWithImage extends Field implements FieldContract, RemovableContract
{
    use Removable, WithFields, HasVerticalMode;

    protected string $primaryKey = 'id';

    protected string $imageFieldName = 'url';

    protected array $buttons = [];

    protected ?Closure $modifyRemoveButton = null;

    protected string $view = 'admin.fields.json-with-image';

    protected bool $isCreatable = true;

    protected ?int $creatableLimit = null;

    protected bool $multiple = true;

    protected array $saveOnlyFields = [];

    protected string $disk = 'public';

    protected string $directory = 'images';

    /**
     * Set the storage disk for images
     * 
     * @param string $disk
     * @return $this
     */
    public function disk(string $disk): self
    {
        $this->disk = $disk;
        return $this;
    }

    /**
     * Set the directory for image storage
     * 
     * @param string $directory
     * @return $this
     */
    public function directory(string $directory): self
    {
        $this->directory = $directory;
        return $this;
    }

    /**
     * Set the primary key for the model
     * 
     * @param string $primaryKey
     * @return $this
     */
    public function primaryKey(string $primaryKey): self
    {
        $this->primaryKey = $primaryKey;
        return $this;
    }

    public function imageFieldName(string $imageFieldName): self
    {
        $this->imageFieldName = $imageFieldName;
        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    public function multiple(bool $multiple): static
    {
        $this->multiple = $multiple;
        return $this;
    }

    public function creatable(?int $limit = null): static
    {
        $this->isCreatable = true;
        $this->creatableLimit = $limit;

        return $this;
    }

    public function isCreatable(): bool
    {
        return $this->isCreatable;
    }

    public function getCreateLimit(): ?int
    {
        return $this->creatableLimit;
    }

    public function getButtons(): array
    {
        if (array_filter($this->buttons) !== []) {
            return $this->buttons;
        }

        $buttons = [];

        if ($this->isRemovable()) {
            $button = ActionButton::make('', '#')
                ->icon('trash')
                ->onClick(static fn($action): string => 'remove', 'prevent')
                ->customAttributes($this->removableAttributes ?: ['class' => 'btn-error'])
                ->showInLine();

            if (! \is_null($this->modifyRemoveButton)) {
                $button = \call_user_func($this->modifyRemoveButton, $button, $this);
            }

            $buttons[] = $button;
        }

        return $buttons;
    }

    protected function prepareFields(): FieldsContract
    {
        $fields = $this->getFields()->prepareAttributes();

        $fields->prepend(
            Image::make(column: $this->imageFieldName)
        );

        $fields->prepend(
            Hidden::make(column: $this->primaryKey),
        );

        if (!$this->isMultiple()) {
            $fields = $fields
                ->map(
                    fn($field) => $field
                        ->customAttributes($this->getReactiveAttributes("{$this->getColumn()}.{$field->getColumn()}"))
                        ->customAttributes(['data-object-mode' => true])
                );
        }

        return $fields
            ->prepareReindexNames(parent: $this, before: static function (self $parent, FieldContract $field): void {
                if ($parent->isMultiple()) {
                    $field->withoutWrapper();
                } else {
                    $parent->customWrapperAttributes([
                        'class' => 'inner-json-object-mode',
                        'data-object-mode' => true,
                    ]);
                }

                $field->setRequestKeyPrefix($parent->getRequestKeyPrefix());
            }, except: fn(FieldContract $parent): bool => $parent instanceof self && !$parent->isMultiple());
    }

    /**
     * @throws Throwable
     */
    public function prepareOnApply(iterable $collection): array
    {
        $collection = collect($collection);
        return $collection->filter(fn($value, $key): bool => $this->filterEmpty($value))->toArray();
    }

    private function filterEmpty(mixed $value): bool
    {
        if (is_iterable($value) && filled($value)) {
            return collect($value)
                ->filter(fn($v): bool => $this->filterEmpty($v))
                ->isNotEmpty();
        }

        return ! blank($value);
    }

    protected function resolveOnApply(): ?Closure
    {
        return function ($item) {
            data_forget($item, $this->getColumn());
            return $item;
        };
    }

    protected function getPrepareDataFromRequest()
    {
        $requestValues = array_filter($this->getRequestValue() ?: []);
        $applyValues = [];

        if (!$this->isMultiple()) {
            $requestValues = [$requestValues];
        }

        foreach ($requestValues as $index => $values) {
            foreach ($this->resetPreparedFields()->getPreparedFields() as $field) {
                if (! $field->isCanApply()) {
                    continue;
                }

                if ($this->isMultiple()) {
                    $field->setNameIndex($index);
                }

                data_set(
                    $applyValues[$index],
                    $field->getColumn(),
                    data_get($values, $field->getColumn()),
                );
            }

            if (!$this->isMultiple()) {
                $applyValues = $applyValues[$index] ?? [];
            }
        }

        $preparedValues = $this->prepareOnApply($applyValues);

        return !$this->isMultiple() ? $preparedValues : array_values($preparedValues);
    }

    protected function resolveAfterApply(mixed $item): mixed
    {
        $data = $this->getPrepareDataFromRequest();

        if ($this->isMultiple()) {
            $this->handleImagesUpdate($item, $data);
        } else {
            $this->handleImageUpdate($item, $data);
        }

        return $item;
    }

    protected function handleImagesUpdate(mixed $item, array $data): void
    {
        $this->deleteRemovedImages($item, $data);
        $this->processNewImages($item, $data);
        $this->processExistingImages($item, $data);
    }

    protected function handleImageUpdate(mixed $item, array $data): void
    {
        $this->deleteRemovedImage($item, $data);
        $this->processExistingImage($item, $data);
        $this->processExistingImage($item, $data);
    }

    /**
     * Save new images
     * 
     * @param Model $item
     * @param array $images
     */
    protected function processNewImages(Model $item, array $images): void
    {
        $newImages = array_filter($images, fn($image) => isset($image[$this->imageFieldName]) && !isset($image["hidden_{$this->imageFieldName}"]));
        $imageRelationName = $this->getColumn();

        foreach ($newImages as $image) {
            $imageData = $this->prepareImageData($image);
            $item->$imageRelationName()->create($imageData);
        }
    }

    /**
     * Process new image uploaded via the form.
     *
     * @param Model $item - the model being worked with
     * @param array $data - form data
     */
    protected function processNewImage(Model $item, array $data): void
    {
        if (!isset($data[$this->imageFieldName]) || empty($data[$this->imageFieldName])) return;

        $relationName = $this->getColumn();
        $prepareData = $this->prepareImageData($data);
        $item->$relationName()->create($prepareData);
    }

    /**
     * Process image update
     * 
     * @param array $image
     */
    protected function processImageUpdate(array $image): array
    {
        $imageData = $this->prepareImageData($image);

        if (!isset($imageData[$this->imageFieldName]) || !isset($image["hidden_{$this->imageFieldName}"])) {
            return $imageData;
        }

        $this->deleteImage($image["hidden_{$this->imageFieldName}"]);

        return $imageData;
    }

    /**
     * Update existing images
     * 
     * @param Model $item
     * @param array $images
     */
    protected function processExistingImages(Model $item, array $images): void
    {
        $existingImages = array_filter($images, fn($image) => isset($image[$this->primaryKey]) && empty($image[$this->primaryKey]) === false);
        $imageRelationName = $this->getColumn();

        foreach ($existingImages as $image) {
            $imageData = $this->processImageUpdate($image);
            if ($imageData) {
                $item->$imageRelationName()
                    ->where($this->primaryKey, $image[$this->primaryKey])
                    ->update($imageData);
            }
        }
    }

    /**
     * Process existing image (update data without changing the image).
     *
     * @param Model $item - the model being worked with
     * @param array $data - form data
     */
    protected function processExistingImage(Model $item, array $data): void
    {
        if (!isset($data[$this->primaryKey]) || empty($data[$this->primaryKey]) || isset($data[$this->imageFieldName])) return;
        
        $relationName = $this->getColumn();
        $prepareData = $this->processImageUpdate($data);
        $item->$relationName()->update($prepareData);
    }

    /**
     * Prepare image data
     * Keep only fields that need to be saved and are present in model fields
     * 
     * @param array $data
     * @return array
     */
    protected function prepareImageData(array $data): array
    {
        if (isset($data[$this->imageFieldName]) && $data[$this->imageFieldName] instanceof UploadedFile) {
            $data[$this->imageFieldName] = $this->storeUploadedImage($data[$this->imageFieldName]);
        }

        $keys = $this->saveOnlyFields;

        if (empty($keys)) {
            $keys = $this->getPreparedFields()
                ->map(fn($field) => $field->getColumn())
                ->filter(fn($columnName) => $columnName !== $this->primaryKey)
                ->toArray();
        }

        $values = array_intersect_key($data, array_flip($keys));

        return $this->prepareOnApply($values);
    }

    /**
     * Delete removed images
     * 
     * @param Model $item
     * @param array $data
     */
    protected function deleteRemovedImages(Model $item, array $data): void
    {
        $images = data_get($item, $this->getColumn());
        $existingIds = $images->pluck($this->primaryKey)->toArray();
        $updatedIds = array_filter(array_column($data, $this->primaryKey));
        $idsToDelete = array_diff($existingIds, $updatedIds);

        if (!empty($idsToDelete)) {
            $images->whereIn($this->primaryKey, $idsToDelete)->each(fn($image) => $image->delete());
        }
    }

    protected function deleteRemovedImage(Model $item, array $data): void
    {
        $relationName = $this->getColumn();

        if ((isset($data[$this->imageFieldName]) || !isset($data['hidden_' . $this->imageFieldName])) && $item->$relationName) {
            $item->$relationName->delete();
        }
    }

    /**
     * Generate unique filename
     * 
     * @param string $name
     * @return string
     */
    protected function generateFileName(string $name, string $dir)
    {
        $blocks = explode('.', $name);
        $extension = array_pop($blocks);
        $index     = 1;
        $filename  = Str::slug(implode('', $blocks));
        $new       = sprintf('%s_%s.%s', $filename, $index, $extension);

        while (Storage::exists("$dir/$new")) {
            $index++;
            $new = sprintf('%s_%s.%s', $filename, $index, $extension);
        }

        return "$dir/$new";
    }

    /**
     * Save uploaded image
     * 
     * @param UploadedFile $uploadedFile
     * @return string
     */
    protected function storeUploadedImage(UploadedFile $uploadedFile): string
    {
        $filename = $this->generateFileName($uploadedFile->getClientFilename(), $this->directory);
        Storage::disk($this->disk)->put($filename, $uploadedFile->getStream()->getContents());

        return $filename;
    }

    /**
     * Delete image
     * 
     * @param string $filename
     */
    protected function deleteImage(string $filename): void
    {
        Storage::disk($this->disk)->delete($filename);
    }

    /**
     * 
     */
    protected function getComponent(): ComponentContract
    {
        $fields = $this->getPreparedFields();
        $value = $this->toValue() ?? [];
        $values = Collection::make(is_iterable($value) ? $value : []);

        if (!$this->isMultiple()) {
            return FieldsGroup::make(
                Fields::make($fields)->fillCloned($values->toArray())
            )->mapFields(
                fn(FieldContract $field): FieldContract => $field
                    ->formName($this->getFormName())
                    ->setParent($this),
            );
        }

        $component = TableBuilder::make($fields, $values)
            ->name('json_with_images_' . $this->getColumn())
            ->editable()
            ->reindex(prepared: true)
            ->when(
                $this->isCreatable(),
                fn(TableBuilder $table): TableBuilder => $table->creatable(
                    limit: $this->getCreateLimit(),
                    label: __('moonshine::ui.create'),
                )
            )
            ->buttons($this->getButtons());

        $component->when(
            $this->isVertical(),
            fn(TableBuilderContract $table): TableBuilderContract => $table->vertical()
        );

        $component->simple();

        return $component;
    }

    /**
     * @return array<string, mixed>
     * @throws Throwable
     */
    protected function viewData(): array
    {
        return [
            'component' => $this->getComponent(),
        ];
    }
}
