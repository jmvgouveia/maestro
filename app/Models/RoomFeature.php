<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomFeature extends Model
{
    protected $fillable = ['name', 'symbol', 'icon', 'icon_path'];

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'room_feature');
    }
}
