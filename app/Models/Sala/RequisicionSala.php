<?php

namespace App\Models\Sala;

use App\Models\Configuracion\Empresa;
use App\Models\Contable\Centrocosto;
use App\Models\Seguridad\Usuario;
use App\Models\Stock\Depmae;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class RequisicionSala extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'requisicion_sala';

    protected $fillable = [
        'fecha', 'fecha_entrega', 'empresa_id', 'numerorequisicion', 'deposito_id',
        'centrocosto_id', 'zona_sala_id', 'prioridad_sala_id', 'usuario_id', 'estado',
        'comentario', 'detalle', 'creousuario_id',
    ];

    public function requisicion_sala_estados()
    {
        return $this->hasMany(RequisicionSalaEstado::class, 'requisicion_sala_id');
    }

    public function requisicion_sala_articulos()
    {
        return $this->hasMany(RequisicionSalaArticulo::class, 'requisicion_sala_id');
    }

    public function requisicion_sala_archivos()
    {
        return $this->hasMany(RequisicionSalaArchivo::class, 'requisicion_sala_id');
    }

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function centrocostos()
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }

    public function depositos()
    {
        return $this->belongsTo(Depmae::class, 'deposito_id');
    }

    public function zona_salas()
    {
        return $this->belongsTo(ZonaSala::class, 'zona_sala_id');
    }

    public function prioridad_salas()
    {
        return $this->belongsTo(PrioridadSala::class, 'prioridad_sala_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }

    public function solicitante()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
