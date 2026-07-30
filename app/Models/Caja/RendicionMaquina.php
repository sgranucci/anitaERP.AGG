<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class RendicionMaquina extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use SoftDeletes;

    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_CONFIRMADA = 'confirmada';

    public const ESTADO_ANULADA = 'anulada';

    /** @var array<int, array{valor: string, nombre: string}> */
    public static array $enumEstado = [
        ['valor' => self::ESTADO_BORRADOR, 'nombre' => 'Borrador'],
        ['valor' => self::ESTADO_CONFIRMADA, 'nombre' => 'Confirmada'],
        ['valor' => self::ESTADO_ANULADA, 'nombre' => 'Anulada'],
    ];

    protected $table = 'rendicion_maquina';

    protected $fillable = [
        'codigo',
        'nro_oper_anita',
        'empresa_id',
        'fecha',
        'turno',
        'estado',
        'supervisor_usuario_id',
        'auxiliar_usuario_id',
        'cajero_usuario_id',
        'creousuario_id',
        'observacion',
        'inputs_json',
        'wigos_json',
        'calc_json',
        'total_ingreso',
        'total_salida',
        'resultado_turno',
        'transferencia',
        'fondo_cierre',
        'fondo_inicial',
        'dif_caja',
        'anita_sincronizado_en',
    ];

    protected $casts = [
        'fecha' => 'date',
        'nro_oper_anita' => 'integer',
        'inputs_json' => 'array',
        'wigos_json' => 'array',
        'calc_json' => 'array',
        'total_ingreso' => 'decimal:2',
        'total_salida' => 'decimal:2',
        'resultado_turno' => 'decimal:2',
        'transferencia' => 'decimal:2',
        'fondo_cierre' => 'decimal:2',
        'fondo_inicial' => 'decimal:2',
        'dif_caja' => 'decimal:2',
        'anita_sincronizado_en' => 'datetime',
    ];

    protected $attributes = [
        'estado' => self::ESTADO_CONFIRMADA,
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function supervisorUsuario()
    {
        return $this->belongsTo(Usuario::class, 'supervisor_usuario_id');
    }

    public function auxiliarUsuario()
    {
        return $this->belongsTo(Usuario::class, 'auxiliar_usuario_id');
    }

    public function cajeroUsuario()
    {
        return $this->belongsTo(Usuario::class, 'cajero_usuario_id');
    }

    public function creoUsuario()
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }

    public function valores(): HasMany
    {
        return $this->hasMany(RendicionMaquinaValor::class)->orderBy('orden')->orderBy('id');
    }

    public function gastos(): HasMany
    {
        return $this->hasMany(RendicionMaquinaGasto::class)->orderBy('orden')->orderBy('id');
    }

    public function ajustesWigos(): HasMany
    {
        return $this->hasMany(RendicionMaquinaAjusteWigos::class);
    }

    public function getEstadoLabelAttribute(): string
    {
        foreach (self::$enumEstado as $row) {
            if ($row['valor'] === $this->estado) {
                return $row['nombre'];
            }
        }

        return (string) ($this->estado ?? '');
    }

    public function getTurnoLabelAttribute(): string
    {
        return match ($this->turno) {
            'M' => 'Mañana',
            'T' => 'Tarde',
            'N' => 'Noche',
            'C' => 'Cierre jornada',
            default => (string) $this->turno,
        };
    }
}
