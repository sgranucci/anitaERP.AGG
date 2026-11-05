<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Sala;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownSlot extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const CONTADORA = '1';

    public const MANUAL = '2';

    public const CORRECCION = '3';

    public const DROP = '4';

    public const BORRADOR = 'B';

    public const RECIBIDO = 'R';

    /** @var array<string, string> */
    public const TIPOS = [
        self::CONTADORA => 'Contadora',
        self::MANUAL => 'Manual',
        self::CORRECCION => 'Corrección',
        self::DROP => 'Drop',
    ];

    /** @var array<string, string> */
    public const ESTADOS = [
        self::BORRADOR => 'Borrador',
        self::RECIBIDO => 'Recibido',
    ];

    protected $table = 'tesoreria_crown_slot';

    protected $fillable = [
        'sala_id',
        'empresa_id',
        'jornada_id',
        'numero',
        'maquina',
        'maquina_nombre',
        'tipo',
        'estado',
        'total',
        'hora',
    ];

    protected $casts = [
        'numero' => 'integer',
        'total' => 'decimal:2',
    ];

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function jornada(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownJornada::class, 'jornada_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(TesoreriaCrownSlotLinea::class, 'slot_id');
    }

    public static function tipo(string $tipo): string
    {
        return self::TIPOS[$tipo] ?? $tipo;
    }

    public static function estado(string $estado): string
    {
        return self::ESTADOS[$estado] ?? $estado;
    }

    public function esBorrador(): bool
    {
        return $this->estado === self::BORRADOR;
    }

    public function esDrop(): bool
    {
        return $this->tipo === self::DROP;
    }

    public function esRecuento(): bool
    {
        return ! $this->esDrop();
    }

    /** Tipos que se cargan a mano (a-recslot). El drop entra por importación. */
    public static function tiposCarga(): array
    {
        return [
            self::CONTADORA => self::TIPOS[self::CONTADORA],
            self::MANUAL => self::TIPOS[self::MANUAL],
            self::CORRECCION => self::TIPOS[self::CORRECCION],
        ];
    }
}
