<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alias aprendido: cómo se llama en el resumen de tarjeta un proveedor o una suscripción.
 */
class Suscripcion_ComercioAlias extends Model
{
    protected $table = 'suscripcion_comercio_alias';

    protected $fillable = [
        'empresa_id',
        'alias',
        'proveedor_id',
        'ordencompra_id',
        'veces_usado',
        'ultimo_uso_at',
        'creousuario_id',
    ];

    protected $casts = [
        'veces_usado' => 'integer',
        'ultimo_uso_at' => 'datetime',
    ];

    public function empresas(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function proveedores(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function ordencompras(): BelongsTo
    {
        return $this->belongsTo(Ordencompra::class, 'ordencompra_id');
    }

    public function creadores(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }
}
