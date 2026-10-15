<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuspiciousActivity extends Model
{
    protected $table = 'suspicious_activities';

    protected $fillable = [
        'room_code',
        'player_a_id',
        'player_b_id',
        'reason',
        'severity',
        'details',
        'ip_address',
        'status',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function playerA()
    {
        return $this->belongsTo(PlayerProfile::class, 'player_a_id', 'player_id');
    }

    public function playerB()
    {
        return $this->belongsTo(PlayerProfile::class, 'player_b_id', 'player_id');
    }
}

