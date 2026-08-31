<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchLog extends Model
{
    protected $table = 'match_logs';
    protected $primaryKey = 'match_id';

    protected $fillable = [
        'room_code',
        'winning_faction',
        'players_json',
        'duration_seconds',
    ];

    protected $casts = [
        'players_json' => 'array',
        'duration_seconds' => 'integer',
    ];
}
