<?php

namespace App\Support;

use Illuminate\Support\Str;

class UserName
{
    /**
     * @return array{first_name: string, last_name: string|null, name: string}
     */
    public static function fromFullName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $firstName = array_shift($parts) ?: 'Usuario';
        $lastName = $parts !== [] ? implode(' ', $parts) : null;

        return [
            'first_name' => Str::title($firstName),
            'last_name' => $lastName !== null ? Str::title($lastName) : null,
            'name' => self::displayName($firstName, $lastName),
        ];
    }

    public static function displayName(?string $firstName, ?string $lastName): string
    {
        $name = collect([$firstName, $lastName])
            ->filter(fn (?string $value): bool => filled($value))
            ->map(fn (string $value): string => trim($value))
            ->implode(' ');

        return $name !== '' ? $name : 'Usuario';
    }
}
