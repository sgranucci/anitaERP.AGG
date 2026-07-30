<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Ajuste manual sobre un dato importado de WIGOS en una rendición de máquinas.
 * Consultable on-line desde la pantalla de la rendición.
 */
class RendicionMaquinaAjusteWigos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'rendicion_maquina_ajuste_wigos';

    protected $fillable = [
        'rendicion_maquina_id',
        'empresa_id',
        'fecha',
        'turno',
        'nro_oper',
        'campo',
        'etiqueta',
        'valor_wigos',
        'valor_ajustado',
        'delta',
        'motivo',
        'usuario_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'nro_oper' => 'integer',
        'valor_wigos' => 'decimal:2',
        'valor_ajustado' => 'decimal:2',
        'delta' => 'decimal:2',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function rendicionMaquina()
    {
        return $this->belongsTo(RendicionMaquina::class, 'rendicion_maquina_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
