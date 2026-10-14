<?php

namespace App\Filament\Resources\Media\Pages;

use App\Filament\Resources\Media\MediaResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateMedia extends CreateRecord
{
    protected static string $resource = MediaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $disk = config('press.media.disk');
        $storage = Storage::disk($disk);
        $path = $data['path'];

        $data['disk'] = $disk;
        $data['user_id'] = auth()->id();
        $data['name'] = basename($path);
        $data['mime_type'] = $storage->mimeType($path) ?: null;
        $data['size'] = $storage->size($path);

        if (str_starts_with((string) $data['mime_type'], 'image/') && $data['mime_type'] !== 'image/svg+xml') {
            $info = @getimagesize($storage->path($path));
            if ($info) {
                [$data['width'], $data['height']] = $info;
            }
        }

        return $data;
    }
}
