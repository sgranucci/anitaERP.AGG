<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Contable\Centrocosto;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ViandaUsuario extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'vianda_usuario';

    protected $fillable = [
        'codigo_usuario',
        'empresa_id',
        'nombre',
        'password',
        'centrocosto_id',
        'tipo_usuario',
        'vianda_tipo_menu_id',
        'estado',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function centrocosto()
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }

    public function tipoMenu()
    {
        return $this->belongsTo(ViandaTipoMenu::class, 'vianda_tipo_menu_id');
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
