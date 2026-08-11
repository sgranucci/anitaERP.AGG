<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo stkumd de Anita Surmar (/usr2/surmar). No mezclar con unidadmedida (El Bierzo).
 */
class UnidadmedidaSurmar extends Model
{
    protected $table = 'unidadmedida_surmar';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = ['id', 'abreviatura', 'nombre'];
}
