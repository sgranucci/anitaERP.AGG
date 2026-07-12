<?php

namespace App\Models\Caja\Bingo;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class BingoCarton extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ESTADO_ACTIVO = 'activo';

    public const ESTADO_SUSPENDIDO = 'suspendido';

    public const ESTADO_ANULADO = 'anulado';

    /** @var array<int, array{valor: string, nombre: string}> */
    public static array $enumEstado = [
        ['valor' => self::ESTADO_ACTIVO, 'nombre' => 'Activo'],
        ['valor' => self::ESTADO_SUSPENDIDO, 'nombre' => 'Suspendido'],
        ['valor' => self::ESTADO_ANULADO, 'nombre' => 'Anulado'],
    ];

    protected $table = 'bingo_carton';

    protected $fillable = [
        'empresa_id',
        'codigo',
        'codigo_anita',
        'nombre',
        'precio_unitario',
        'lineas',
        'es_azar',
        'orden',
        'estado',
    ];

    protected $casts = [
        'codigo_anita' => 'integer',
        'precio_unitario' => 'decimal:2',
        'lineas' => 'integer',
        'es_azar' => 'boolean',
        'orden' => 'integer',
    ];

    protected $attributes = [
        'estado' => self::ESTADO_ACTIVO,
        'lineas' => 4,
        'es_azar' => false,
        'orden' => 0,
        'precio_unitario' => 0,
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
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

    public function getEtiquetaCompletaAttribute(): string
    {
        $codigo = trim((string) ($this->codigo ?? ''));
        $nombre = trim((string) ($this->nombre ?? ''));

        if ($codigo === '') {
            return $nombre;
        }

        if ($nombre === '' || strcasecmp($codigo, $nombre) === 0) {
            return $codigo;
        }

        return $codigo.' - '.$nombre;
    }
}
