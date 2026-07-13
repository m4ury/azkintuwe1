<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Establecimiento extends Model
{
    protected $fillable = [
        'nombre',
        'codigo',
        'direccion',
        'comuna_id',
    ];

    public function comuna()
    {
        return $this->belongsTo(Comuna::class);
    }
}
