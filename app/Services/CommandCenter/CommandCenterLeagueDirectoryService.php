<?php

namespace App\Services\CommandCenter;

use App\Models\League;

class CommandCenterLeagueDirectoryService
{
    /**
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     is_active: bool,
     *     admins: array<int, array{id: int|null, name: string|null}>,
     *     members_count: int,
     *     created_at: string|null
     * }>
     */
    public function leagues(): array
    {
        return League::query()
            ->with([
                'adminMemberships.user:id,name',
                'memberMemberships',
            ])
            ->withCount('memberMemberships')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (League $league): array => $this->toArray($league))
            ->all();
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     is_active: bool,
     *     admins: array<int, array{id: int|null, name: string|null}>,
     *     members_count: int,
     *     created_at: string|null
     * }
     */
    public function toggle(League $league): array
    {
        $league->update([
            'is_active' => ! $league->is_active,
        ]);

        return $this->toArray(
            $league->fresh([
                'adminMemberships.user:id,name',
                'memberMemberships',
            ])->loadCount('memberMemberships')
        );
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     is_active: bool,
     *     admins: array<int, array{id: int|null, name: string|null}>,
     *     members_count: int,
     *     created_at: string|null
     * }
     */
    private function toArray(League $league): array
    {
        return [
            'id' => $league->id,
            'name' => $league->name,
            'slug' => $league->slug,
            'is_active' => $league->is_active,
            'admins' => $league->adminMemberships
                ->map(fn ($membership): array => [
                    'id' => $membership->user?->id,
                    'name' => $membership->user?->name,
                ])
                ->filter(fn (array $admin): bool => filled($admin['name']))
                ->values()
                ->all(),
            'members_count' => (int) $league->member_memberships_count,
            'created_at' => $league->created_at?->toDateTimeString(),
        ];
    }
}
