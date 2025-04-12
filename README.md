# JsonWithImages - Field for Working with JSON and Images in MoonShine

## Description

The `JsonWithImages` class extends the standard `Json` field in MoonShine, adding functionality for working with images in a JSON structure. This field is designed to manage a collection of images with the ability to upload, update, and delete them.

This is the English version. Other languages:
- [Русский](README.ru.md)

## Features

- Working with images in a JSON structure
- Integration with Laravel relationships (MorphMany)
- Automatic management of image files (upload, update, deletion)
- Flexible storage parameter configuration
- Support for saving only specific fields

## Installation

1. Place the class file in the `app/MoonShine/Fields/` directory
2. Use it in your MoonShine resources
3. The primaryKey field ID::make('id') must be present

## Usage

### Basic Usage

```php
use App\MoonShine\Fields\JsonWithImages;

JsonWithImages::make('Images', 'images')
    ->fields([
        ID::make('id'), // The ID field must be present
        // Specify the fields that exist in the images table
    ]);
```

### Advanced Configuration

```php
JsonWithImages::make('Gallery', 'images')
    ->setDisk('public') // Set the storage disk
    ->setDirectory('gallery') // Directory for images
    ->setPrimaryKey('id') // Primary key field
    ->setImageFieldName('url') // The image field itself
    ->saveOnlyFields(['alt', 'url', 'order']) // Save only specified fields
    ->fields([
        ID::make('id'),
        Image::make('Image', 'url'),
        Text::make('Alternative text', 'alt'),
        Number::make('Order', 'order')->default(10),
    ]);
```

## Settings

| Method | Description | Default |
|-------|----------|--------------|
| `setDisk(string $disk)` | Sets the storage disk | 'public' |
| `setDirectory(string $directory)` | Sets the storage directory | 'images' |
| `setPrimaryKey(string $primaryKey)` | Sets the primary key field name | 'id' |
| `setImageFieldName(string $imageFieldName)` | Sets the image field name | 'url' |
| `saveOnlyFields(array $fields)` | Sets fields to save | [] (all fields) |

## Methods

### Main Methods

- `setDisk(string $disk)` - Set the storage disk for images
- `setDirectory(string $directory)` - Set the storage directory
- `setPrimaryKey(string $primaryKey)` - Set the primary key field
- `setImageFieldName(string $imageFieldName)` - Set the field for storing the image path
- `saveOnlyFields(array $fields)` - Set fields to be saved

### Internal Methods

- `handleImagesUpdate(Model $item, array $images)` - Handle image updates
- `processNewImages(Model $item, array $images)` - Process new images
- `processExistingImages(Model $item, array $images)` - Process existing images
- `deleteRemovedImages(Model $item, array $images)` - Delete removed images
- `storeUploadedImage(UploadedFile $uploadedFile)` - Save an uploaded image
- `deleteImage(string $filename)` - Delete an image

## Integration with MorphMany

The field fully supports working with MorphMany relationships in Laravel. To use it:

1. Ensure the model has the corresponding morphMany relationship
2. Use the field in the MoonShine resource as usual

Example relationship in a model:

```php
public function images(): MorphMany
{
    return $this->morphMany(Image::class, 'imageable');
}
```

## Requirements

- MoonShine 3+