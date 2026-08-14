<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use App\Models\Contable\Cuentacontable;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ImputacionPerdida extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'imputacion_perdida';

    protected $fillable = [
        'codigo',
        'nombre',
    ];

    protected $casts = [
        'codigo' => 'integer',
    ];

    public function empresas()
    {
        return $this->hasMany(ImputacionPerdidaEmpresa::class, 'imputacion_perdida_id');
    }

    public function perdidas()
    {
        return $this->hasMany(PerdidaPersonal::class, 'imputacion_perdida_id');
    }

    public function getEmpresasResumenAttribute(): string
    {
        if (! $this->relationLoaded('empresas')) {
            return '';
        }

        return $this->empresas
            ->sortBy(fn ($e) => $e->empresa->nombre ?? '')
            ->map(fn ($e) => $e->empresa->nombre ?? ('#'.$e->empresa_id))
            ->filter()
            ->implode(', ');
    }
}
