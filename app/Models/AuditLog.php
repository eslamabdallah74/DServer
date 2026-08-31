<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';
    protected $primaryKey = 'log_id';

    protected $fillable = [
        'user_id',
        'action',
        'details',
        'ip_address',
    ];
}
