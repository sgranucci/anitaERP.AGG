<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Salida extends Model
{
    protected $fillable = ['nombre', 'ubicacion_impresora_id', 'comando'];

    protected $table = 'salida';

    protected $keyField = 'id';

    /** @var list<string> */
    protected $appends = ['ubicacion', 'usos_etiqueta'];

    public function ubicacionImpresora(): BelongsTo
    {
        return $this->belongsTo(UbicacionImpresora::class, 'ubicacion_impresora_id');
    }

    public function usoSalidaImpresoras(): BelongsToMany
    {
        return $this->belongsToMany(
            UsoSalidaImpresora::class,
            'salida_uso_salida_impresora',
            'salida_id',
            'uso_salida_impresora_id'
        );
    }

    public function esUsoGenerico(): bool
    {
        if ($this->relationLoaded('usoSalidaImpresoras')) {
            return $this->usoSalidaImpresoras->isEmpty();
        }

        return ! $this->usoSalidaImpresoras()->exists();
    }

    public function getUbicacionAttribute(): string
    {
        if (array_key_exists('ubicacion', $this->attributes)) {
            return trim((string) $this->attributes['ubicacion']);
        }

        return trim((string) ($this->ubicacionImpresora?->nombre ?? ''));
    }

    public function getUsosEtiquetaAttribute(): string
    {
        if ($this->esUsoGenerico()) {
            return 'Uso genérico';
        }

        $usos = $this->relationLoaded('usoSalidaImpresoras')
            ? $this->usoSalidaImpresoras
            : $this->usoSalidaImpresoras()->orderBy('nombre')->get();

        return $usos->pluck('nombre')->implode(', ');
    }
}
