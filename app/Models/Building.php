<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Building extends Model
{
    protected $fillable = [
        'name',
        'address',
    ];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class, 'id_building');
    }

    public function userBuildingAuthorizations(): HasMany
    {
        return $this->hasMany(UserBuildingAuthorization::class, 'building_id');
    }

    public function classes()
    {
        return $this->belongsToMany(Classes::class, 'class_buildings', 'id_building', 'id_class')
            ->withTimestamps();
    }
}
