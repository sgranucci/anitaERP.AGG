<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuración global del circuito de aprobación de asientos contables.
 * Una sola fila operativa (id = 1).
 */
class Configuracion_AsientoContable extends Model
{
    protected $table = 'configuracion_asiento_contable';

    protected $fillable = [
        'enviar_mail_aprobacion',
        'mail_aprobador',
        'mail_copia_a',
        'horas_validez_token',
        'mail_asunto_aprobacion',
        'mail_texto_aprobacion',
        'mail_asunto_aprobado_solicitante',
        'mail_asunto_rechazado_solicitante',
    ];

    protected $casts = [
        'enviar_mail_aprobacion' => 'boolean',
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

    /** @return list<string> */
    public function copiasComoArray(): array
    {
        if (empty($this->mail_copia_a)) {
            return [];
        }

        $items = preg_split('/[\s,;]+/', $this->mail_copia_a) ?: [];

        return array_values(array_filter(array_map('trim', $items), fn ($v) => $v !== ''));
    }

    public function emailAprobadorValido(): ?string
    {
        $email = trim((string) ($this->mail_aprobador ?? ''));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
