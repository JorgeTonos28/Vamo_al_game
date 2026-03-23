<?php

namespace App\Services\Branding;

use App\Models\BrandingSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AppBrandingService
{
    private ?BrandingSetting $brandingSetting = null;

    public function current(): BrandingSetting
    {
        if ($this->brandingSetting instanceof BrandingSetting) {
            return $this->brandingSetting;
        }

        if (! Schema::hasTable('branding_settings')) {
            return $this->brandingSetting = new BrandingSetting([
                'logo_path' => null,
                'favicon_path' => null,
            ]);
        }

        return $this->brandingSetting = BrandingSetting::query()->firstOrCreate(
            ['id' => 1],
            [],
        );
    }

    /**
     * @return array{
     *     logo_url: string|null,
     *     favicon_url: string|null,
     *     favicon_type: string|null,
     *     has_custom_logo: bool,
     *     has_custom_favicon: bool,
     *     updated_at: string|null
     * }
     */
    public function branding(): array
    {
        $settings = $this->current();
        $version = $settings->updated_at?->timestamp;

        return [
            'logo_url' => $settings->logo_path
                ? route('branding.logo.show').($version ? '?v='.$version : '')
                : null,
            'favicon_url' => $settings->favicon_path
                ? route('branding.favicon.show').($version ? '?v='.$version : '')
                : null,
            'favicon_type' => $this->mimeTypeFor($settings->favicon_path),
            'has_custom_logo' => filled($settings->logo_path),
            'has_custom_favicon' => filled($settings->favicon_path),
            'updated_at' => $settings->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array{logo?: UploadedFile|null, favicon?: UploadedFile|null}  $files
     */
    public function update(array $files): BrandingSetting
    {
        $settings = $this->current();

        if (($files['logo'] ?? null) instanceof UploadedFile) {
            $settings->logo_path = $this->storeFile(
                $files['logo'],
                $settings->logo_path,
                'app-logo',
            );
        }

        if (($files['favicon'] ?? null) instanceof UploadedFile) {
            $settings->favicon_path = $this->storeFile(
                $files['favicon'],
                $settings->favicon_path,
                'app-favicon',
            );
        }

        $settings->save();

        return $this->brandingSetting = $settings->fresh();
    }

    public function logoResponse()
    {
        $settings = $this->current();
        abort_unless($settings->logo_path && Storage::disk('local')->exists($settings->logo_path), 404);

        return response()->file(Storage::disk('local')->path($settings->logo_path), [
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function faviconResponse()
    {
        $settings = $this->current();
        abort_unless($settings->favicon_path && Storage::disk('local')->exists($settings->favicon_path), 404);

        return response()->file(Storage::disk('local')->path($settings->favicon_path), [
            'Content-Type' => $this->mimeTypeFor($settings->favicon_path) ?? 'application/octet-stream',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function storeFile(UploadedFile $file, ?string $existingPath, string $baseName): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $path = 'branding/'.$baseName.'.'.$extension;

        Storage::disk('local')->putFileAs('branding', $file, $baseName.'.'.$extension);

        if ($existingPath && $existingPath !== $path && Storage::disk('local')->exists($existingPath)) {
            Storage::disk('local')->delete($existingPath);
        }

        return $path;
    }

    private function mimeTypeFor(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => null,
        };
    }
}
