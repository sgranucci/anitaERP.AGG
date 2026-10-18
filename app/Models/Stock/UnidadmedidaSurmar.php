<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Catálogo stkumd de Anita Surmar (/usr2/surmar). No mezclar con unidadmedida (El Bierzo).
 */
class UnidadmedidaSurmar extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'unidadmedida_surmar';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = ['id', 'abreviatura', 'nombre'];
}
