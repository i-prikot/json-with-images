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
     * Set the storage disk for images
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
     * Set the directory for image storage
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
     * Set the primary key for the model
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
     * Set fields that should be saved
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
     * Save the data
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
     * Process images
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
     * Save new images
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
            $imageData[$this->imageFieldName] = $this->storeUploadedImage($image[$this->imageFieldName]);
            $item->$imageRelationName()->create($imageData);
        }
    }

    /**
     * Process image update
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
     * Update existing images
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
     * Prepare image data
     * Keep only fields that need to be saved and are present in model fields
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
     * Delete removed images
     * 
     * @param Model $item
     * @param array $data
     */
    protected function deleteRemovedImages(Model $item, array $data): void
    {
        $existingIds = $item->images->pluck($this->primaryKey)->toArray();
        $updatedIds = array_filter(array_column($data, $this->primaryKey));
        $idsToDelete = array_diff($existingIds, $updatedIds);
 
        if (!empty($idsToDelete)) {
            // Связь с таблицей изображений
            $relation = $this->column;
            $item->$relation->whereIn($this->primaryKey, $idsToDelete)->each(fn($image) => $image->delete());
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
}
