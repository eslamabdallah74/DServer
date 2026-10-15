<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameConfiguration extends Model
{
    protected $table = 'game_configurations';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
        'description',
    ];
}
