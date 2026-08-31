<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerProfile extends Model
{
    protected $table = 'player_profiles';
    protected $primaryKey = 'player_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'player_id',
        'nickname',
        'coins',
        'owned_roles_json',
        'stats_wins',
        'stats_losses',
        'stats_matches',
        'last_seen_at',
    ];

    protected $casts = [
        'coins' => 'integer',
        'stats_wins' => 'integer',
        'stats_losses' => 'integer',
        'stats_matches' => 'integer',
        'last_seen_at' => 'datetime',
    ];

    public function getOwnedRolesAttribute(): array
    {
        if (empty($this->owned_roles_json)) return [];
        $decoded = json_decode($this->owned_roles_json, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function setOwnedRolesAttribute(array $roles): void
    {
        $this->attributes['owned_roles_json'] = json_encode(array_values(array_unique($roles)));
    }
}
