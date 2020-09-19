<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Session_levels extends Model
{
    use HasFactory;

    protected $fillable=[
        'session_id',
        'session_level',
        'phone_number'
    ];

    public $timestamps=false;
}
