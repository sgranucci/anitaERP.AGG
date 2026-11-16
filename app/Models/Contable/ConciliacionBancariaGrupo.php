<?php

namespace App\Models\Contable;

use App\Models\Caja\Cuentacaja;
use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class ConciliacionBancariaGrupo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'conciliacion_bancaria_grupo';

    protected $fillable = [
        'empresa_id',
        'cuentacaja_id',
        'importe_contable',
        'importe_banco',
        'modo',
        'conciliado_por_usuario_id',
        'conciliado_at',
    ];

    protected function casts(): array
    {
        return [
            'importe_contable' => 'decimal:2',
            'importe_banco' => 'decimal:2',
            'conciliado_at' => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cuentacaja(): BelongsTo
    {
        return $this->belongsTo(Cuentacaja::class, 'cuentacaja_id');
    }

    public function conciliadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'conciliado_por_usuario_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(ConciliacionBancariaGrupoLinea::class, 'grupo_id');
    }
}
