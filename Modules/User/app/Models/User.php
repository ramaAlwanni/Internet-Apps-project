<?php

namespace Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Traits\HasRoles;

// use Modules\User\Database\Factories\UserFactory;

class User extends Model
{
    use HasFactory, HasRoles;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    // protected static function newFactory(): UserFactory
    // {
    //     // return UserFactory::new();
    // }
}
