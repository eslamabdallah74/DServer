<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserFeedback extends Model
{
    protected $table = 'user_feedbacks';
    protected $primaryKey = 'feedback_id';

    protected $fillable = [
        'player_id',
        'content',
        'rating',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];
}
