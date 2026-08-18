<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReporteSueldosDefinibleWebhook extends Model
{
    public const EVENTO_OK = 'ejecucion.ok';

    public const EVENTO_ERROR = 'ejecucion.error';

    protected $table = 'reporte_sueldos_definible_webhook';

    protected $fillable = [
        'reporte_sueldos_definible_id',
        'url',
        'secret',
        'eventos',
        'activo',
    ];

    protected $casts = [
        'reporte_sueldos_definible_id' => 'integer',
        'eventos' => 'array',
        'activo' => 'boolean',
    ];

    protected $hidden = [
        'secret',
    ];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ReporteSueldosDefinible::class, 'reporte_sueldos_definible_id');
    }

    /**
     * @return list<string>
     */
    public function eventosEfectivos(): array
    {
        $eventos = array_values(array_filter(array_map('strval', (array) ($this->eventos ?? []))));

        return $eventos !== [] ? $eventos : [self::EVENTO_OK, self::EVENTO_ERROR];
    }

    public function escucha(string $evento): bool
    {
        return in_array($evento, $this->eventosEfectivos(), true);
    }

    /**
     * @return list<string>
     */
    public static function eventosCatalogo(): array
    {
        return [self::EVENTO_OK, self::EVENTO_ERROR];
    }
}
