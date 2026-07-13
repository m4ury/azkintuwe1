<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comuna extends Model
{
    protected $fillable = [
        'nombre',
        'codigo',
    ];

    public function establecimientos()
    {
        return $this->hasMany(Establecimiento::class);
    }
}
