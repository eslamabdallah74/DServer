<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppIssue extends Model
{
    protected $table = 'app_issues';
    protected $primaryKey = 'issue_id';

    protected $fillable = [
        'severity',
        'message',
        'stack_trace',
        'page',
        'device_info',
    ];

    protected $casts = [
        'device_info' => 'array',
    ];
}
