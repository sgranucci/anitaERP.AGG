<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ViandaTipoMenu extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'vianda_tipo_menu';

    protected $fillable = [
        'empresa_id',
        'codigo_anita',
        'nombre',
        'estado',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function articulos()
    {
        return $this->hasMany(ViandaTipoMenuArticulo::class, 'vianda_tipo_menu_id')
            ->orderBy('dia_semana')
            ->orderBy('orden');
    }

    public function etiquetaEstado(): string
    {
        return match ($this->estado) {
            'A' => 'Activo',
            'I' => 'Inactivo',
            default => (string) $this->estado,
        };
    }
}
