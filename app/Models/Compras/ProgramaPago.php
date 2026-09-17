<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramaPago extends Model
{
    public const ESTADO_BORRADOR = 'BORRADOR';

    public const ESTADO_CERRADO = 'CERRADO';

    /** @var list<array{valor: string, nombre: string}> */
    public static array $enumEstado = [
        ['valor' => self::ESTADO_BORRADOR, 'nombre' => 'Borrador'],
        ['valor' => self::ESTADO_CERRADO, 'nombre' => 'Cerrado'],
    ];

    protected $table = 'programa_pago';

    protected $fillable = [
        'empresa_id',
        'titulo',
        'fecha_base',
        'anio_mes_inicio',
        'cantidad_meses',
        'incluye_transf',
        'estado',
        'detalle',
        'usuario_id',
    ];

    protected $casts = [
        'fecha_base' => 'date',
        'cantidad_meses' => 'integer',
        'incluye_transf' => 'boolean',
    ];

    public function esEditable(): bool
    {
        return $this->estado === self::ESTADO_BORRADOR;
    }

    public function empresas(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function usuarios(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(ProgramaPagoLinea::class, 'programa_pago_id')
            ->orderBy('orden')
            ->orderBy('id');
    }
}
