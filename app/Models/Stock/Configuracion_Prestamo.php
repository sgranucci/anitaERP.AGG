<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuración global del módulo de préstamos. Se mantiene una única
 * fila con id = 1 (creada por la migración correspondiente).
 */
class Configuracion_Prestamo extends Model
{
    protected $table = 'configuracion_prestamo';

    protected $fillable = [
        'enviar_aprobacion',
        'enviar_recordatorios',
        'dias_antes_devolucion_aviso',
        'dias_repeticion_vencido',
        'horas_validez_token',
        'mail_asunto_aprobacion',
        'mail_asunto_recordatorio',
        'mail_asunto_devolucion_vencida',
        'mail_asunto_aprobado_solicitante',
        'mail_asunto_rechazado_solicitante',
        'mail_remitente',
        'mail_copia_a',
        'mail_texto_aprobacion',
        'mail_texto_recordatorio',
        'mail_texto_devolucion_vencida',
    ];

    protected $casts = [
        'enviar_aprobacion' => 'boolean',
        'enviar_recordatorios' => 'boolean',
        'dias_antes_devolucion_aviso' => 'integer',
        'dias_repeticion_vencido' => 'integer',
        'horas_validez_token' => 'integer',
    ];

    public static function vigente(): self
    {
        $config = self::query()->orderBy('id')->first();
        if ($config) {
            return $config;
        }

        return self::create([]);
    }

    /**
     * @return list<string>
     */
    public function copiasComoArray(): array
    {
        if (empty($this->mail_copia_a)) {
            return [];
        }

        $items = preg_split('/[\s,;]+/', $this->mail_copia_a) ?: [];

        return array_values(array_filter(array_map('trim', $items), fn ($v) => $v !== ''));
    }
}
