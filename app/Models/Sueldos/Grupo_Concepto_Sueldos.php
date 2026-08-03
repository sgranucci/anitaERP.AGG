<?php

namespace App\Models\Sueldos;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class Grupo_Concepto_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'grupo_concepto_sueldos';

    protected $fillable = [
        'empresa_id', 'codigo', 'descripcion', 'activo', 'origen',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'codigo' => 'integer',
        'activo' => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Grupo_Concepto_Item_Sueldos::class, 'grupo_concepto_id');
    }

    public function conceptos(): BelongsToMany
    {
        return $this->belongsToMany(
            Concepto_Sueldos::class,
            'grupo_concepto_item_sueldos',
            'grupo_concepto_id',
            'concepto_id'
        )->withPivot(['orden', 'activo'])->withTimestamps()
            ->orderBy('grupo_concepto_item_sueldos.orden')
            ->orderBy('concepto_sueldos.codigo');
    }

    public function empleados(): BelongsToMany
    {
        return $this->belongsToMany(
            Empleado_Sueldos::class,
            'empleado_grupo_concepto_sueldos',
            'grupo_concepto_id',
            'empleado_id'
        )->withPivot(['id', 'orden', 'origen'])->withTimestamps();
    }
}
