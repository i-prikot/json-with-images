<?php

declare(strict_types=1);

namespace App\MoonShine\Fields;

use Closure;
use Illuminate\Support\Str;
use MoonShine\UI\Fields\Json;
use Nyholm\Psr7\UploadedFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class JsonWithImages extends Json
{
    protected bool $removable = true;

    protected bool $isReorderable = false;

    protected bool $isVertical = true;

    protected string $primaryKey = 'id';

    protected string $imageFieldName = 'url';

    protected array $saveOnlyFields = [];

    protected string $disk = 'public';

    protected string $directory = 'images';

    /**
     * Установить диск для хранения изображений
     * 
     * @param string $disk
     * @return $this
     */
    public function setDisk(string $disk): self
    {
        $this->disk = $disk;
        return $this;
    }

    /**
     * Установить директорию для хранения изображений
     * 
     * @param string $directory
     * @return $this
     */
    public function setDirectory(string $directory): self
    {
        $this->directory = $directory;
        return $this;
    }

    /**
     * Установить первичный ключ для модели
     * 
     * @param string $primaryKey
     * @return $this
     */
    public function setPrimaryKey(string $primaryKey): self
    {
        $this->primaryKey = $primaryKey;
        return $this;
    }

    public function setImageFieldName(string $imageFieldName): self
    {
        $this->imageFieldName = $imageFieldName;
        return $this;
    }
    
    /**
     * Установить поля, которые будут сохраняться
     * 
     * @param array $fields
     * @return $this
     */
    public function saveOnlyFields(array $fields): self
    {
        $this->saveOnlyFields = $fields;
        return $this;
    }

    /**
     * @return Closure|null
     */
    protected function resolveOnApply(): ?Closure
    {
        return function(Model $item) {
            unset($item->{$this->column});
            return $item;
        };
    }

    /**
     * Сохраняем данные
     * 
     * @param mixed|Model $data
     * @return mixed
     */
    protected function resolveAfterApply(mixed $data): mixed
    {
        $value = $this->getRequestValue();
        $this->handleImagesUpdate($data, is_array($value) ? $value: []);
        return $data;
    }

    /**
     * Обрабатываем изображения
     * 
     * @param Model $item
     * @param array $images
     */
    protected function handleImagesUpdate(Model $item, array $images): void
    {
        $this->deleteRemovedImages($item, $images);
        $this->processNewImages($item, $images);
        $this->processExistingImages($item, $images);
    }

    /**
     * Сохранение новых изображений
     * 
     * @param Model $item
     * @param array $images
     */
    protected function processNewImages(Model $item, array $images): void
    {
        $newImages = array_filter($images, fn($image) => isset($image[$this->imageFieldName]) && !isset($image["hidden_{$this->imageFieldName}"]));
        $imageRelationName = $this->column;

        foreach ($newImages as $image) {
            $imageData = $this->prepareImageData($image);
            // Сохранить новое изображение
            $imageData[$this->imageFieldName] = $this->storeUploadedImage($image[$this->imageFieldName]);
            $item->$imageRelationName()->create($imageData);
        }
    }

    /**
     * Обработка изображения
     * 
     * @param array $image
     */
    protected function handleImageUpdate(array $image): array
    {
        $imageData = $this->prepareImageData($image);

        if (!isset($imageData[$this->imageFieldName]) || !isset($image["hidden_{$this->imageFieldName}"])) {
            return $imageData;
        }

        $this->deleteImage($image["hidden_{$this->imageFieldName}"]);
        $imageData[$this->imageFieldName] = $this->storeUploadedImage($image[$this->imageFieldName]);
        
        return $imageData;
    }

    /**
     * Обновление существующих изображений
     * 
     * @param Model $item
     * @param array $images
     */
    protected function processExistingImages(Model $item, array $images): void
    {
        $existingImages = array_filter($images, fn($image) => isset($image[$this->primaryKey]));
        $imageRelationName = $this->column;

        foreach ($existingImages as $image) {
            $imageData = $this->handleImageUpdate($image);
            
            $item->$imageRelationName()
                ->where($this->primaryKey, $image[$this->primaryKey])
                ->update($imageData);
        }
    }

    /**
     * Подготовить данные изображения
     * Оставляем только те поля, которые нужно сохранить, которые присутствуют в полях модели
     * 
     * @param array $image
     * @return array
     */
    protected function prepareImageData(array $image): array
    {
        $keys = $this->saveOnlyFields;

        if (empty($keys)) {
            $keys = $this->getFields()
                ->map(fn($field) => $field->getColumn())->filter(fn ($value) => $value !== $this->primaryKey)
                ->toArray();
        }

        return array_intersect_key($image, array_flip($keys));
    }

    /**
     * Удалить удаленные изображения
     * 
     * @param Model $item
     * @param array $images
     */
    protected function deleteRemovedImages(Model $item, array $images): void
    {
        $existingIds = $item->images->pluck($this->primaryKey)->toArray();
        $updatedIds = array_filter(array_column($images, $this->primaryKey));
        $idsToDelete = array_diff($existingIds, $updatedIds);
 
        if (!empty($idsToDelete)) {
            // Связь с таблицей изображений
            $relation = $this->column;
            $item->$relation->whereIn($this->primaryKey, $idsToDelete)->each(fn($image) => $image->delete());
        }
    }

    /**
     * Генерируем уникальное имя файла
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
     * Сохранить загруженное изображение
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
     * Удалить изображение
     * 
     * @param string $filename
     */
    protected function deleteImage(string $filename): void
    {
        Storage::disk($this->disk)->delete($filename);
    }
}
