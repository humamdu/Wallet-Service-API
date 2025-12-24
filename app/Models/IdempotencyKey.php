<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IdempotencyKey extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'method', 'path', 'group_id', 'response'];
    protected $casts = ['response' => 'array']; // cached response data
}
