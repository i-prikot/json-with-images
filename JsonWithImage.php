<?php

declare(strict_types=1);

namespace App\MoonShine\Fields;

use Illuminate\Database\Eloquent\Model;

/**
 * JsonWithImage class - extends JsonWithImages to work with JSON data containing data image
 * in object mode (rather than array) with additional image processing.
 */
class JsonWithImage extends JsonWithImages
{
    protected bool $objectMode = true;

    protected bool $removable = false;

    protected bool $isVertical = false;

     /**
     * Delete image that were removed from the form.
     *
     * @param Model $item - the model being worked with
     * @param array $data - form data
     */
    protected function deleteRemovedImages(Model $item, array $data): void
    {
        $relationName = $this->column;
        
        if ((isset($data[$this->imageFieldName]) || !isset($data['hidden_' . $this->imageFieldName])) && $item->$relationName) {
            $item->$relationName->delete();
        }
    }

    /**
     * Process new image uploaded via the form.
     *
     * @param Model $item - the model being worked with
     * @param array $data - form data
     */
    protected function processNewImages(Model $item, array $data): void
    {
        if (!isset($data[$this->imageFieldName]) || empty($data[$this->imageFieldName])) return;

        $relationName = $this->column;
        $prepareData = $this->prepareImageData($data);
        $prepareData[$this->imageFieldName] = $this->storeUploadedImage($data[$this->imageFieldName]);
        $item->$relationName()->create($prepareData);
    }

    /**
     * Process existing image (update data without changing the image).
     *
     * @param Model $item - the model being worked with
     * @param array $data - form data
     */
    protected function processExistingImages(Model $item, array $data): void
    {
        if (!isset($data[$this->primaryKey]) || empty($data[$this->primaryKey]) || isset($data[$this->imageFieldName])) return;
        
        $relationName = $this->column;
        $prepareData = $this->handleImageUpdate($data);
        $item->$relationName()->update($prepareData);
    }
}
