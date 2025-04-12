# JsonWithImages - Поле для работы с JSON и изображениями в MoonShine

## Описание

Класс `JsonWithImages` расширяет стандартное поле `Json` в MoonShine, добавляя функциональность для работы с изображениями в JSON-структуре. Поле предназначено для управления коллекцией изображений с возможностью загрузки, обновления и удаления.

## Особенности

- Работа с изображениями в JSON-структуре
- Интеграция с отношениями Laravel (MorphMany)
- Автоматическое управление файлами изображений (загрузка, обновление, удаление)
- Гибкая настройка параметров хранения
- Поддержка только определенных полей для сохранения

## Установка

1. Поместите файл класса в директорию `app/MoonShine/Fields/`
2. Используйте в своих ресурсах MoonShine
3. Обязательно должно присутствовать поле primaryKey ID::make('id')

## Использование

### Базовое использование

```php
use App\MoonShine\Fields\JsonWithImages;

JsonWithImages::make('Изображения', 'images')
    ->fields([
        ID::make('id'), // Поле ID должно обязательно присутствовать
        // Указываем поля, которые есть в таблице images
    ]);
```

### Расширенные настройки

```php
JsonWithImages::make('Галерея', 'images')
    ->setDisk('public') // Установка диска для хранения
    ->setDirectory('gallery') // Директория для изображений
    ->setPrimaryKey('id') // Поле первичного ключа
    ->setImageFieldName('url') // Само изображение
    ->saveOnlyFields(['alt', 'url', 'order']) // Сохранять только указанные поля
    ->fields([
        ID::make('id'),
        Image::make('Изображение', 'url'),
        Text::make('Альтернативный текст', 'alt'),
        Number::make('Порядок', 'order')->default(10),
    ]);
```

## Настройки

| Метод | Описание | По умолчанию |
|-------|----------|--------------|
| `setDisk(string $disk)` | Устанавливает диск для хранения | 'public' |
| `setDirectory(string $directory)` | Устанавливает директорию для хранения | 'images' |
| `setPrimaryKey(string $primaryKey)` | Устанавливает название поля первичного ключа | 'id' |
| `setImageFieldName(string $imageFieldName)` | Устанавливает название поля для изображения | 'url' |
| `saveOnlyFields(array $fields)` | Устанавливает поля для сохранения | [] (все поля) |

## Методы

### Основные методы

- `setDisk(string $disk)` - Установить диск для хранения изображений
- `setDirectory(string $directory)` - Установить директорию для хранения
- `setPrimaryKey(string $primaryKey)` - Установить поле первичного ключа
- `setImageFieldName(string $imageFieldName)` - Установить поле для хранения пути к изображению
- `saveOnlyFields(array $fields)` - Установить поля, которые нужно сохранять

### Внутренние методы

- `handleImagesUpdate(Model $item, array $images)` - Обработка обновления изображений
- `processNewImages(Model $item, array $images)` - Обработка новых изображений
- `processExistingImages(Model $item, array $images)` - Обработка существующих изображений
- `deleteRemovedImages(Model $item, array $images)` - Удаление удаленных изображений
- `storeUploadedImage(UploadedFile $uploadedFile)` - Сохранение загруженного изображения
- `deleteImage(string $filename)` - Удаление изображения

## Интеграция с MorphMany

Поле полностью поддерживает работу с отношениями MorphMany в Laravel. Для работы необходимо:

1. Убедиться, что модель имеет соответствующее отношение morphMany
2. Использовать поле в ресурсе MoonShine как обычно

Пример отношения в модели:

```php
public function images(): MorphMany
{
    return $this->morphMany(Image::class, 'imageable');
}
```

## Требования

- MoonShine 3+
