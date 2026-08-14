<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuración global del circuito de aprobación de asientos contables.
 * Una sola fila operativa (id = 1).
 */
class Configuracion_AsientoContable extends Model
{
    public const FORMATO_IMPRESION_PDF = 'pdf';
    public const FORMATO_IMPRESION_EXCEL = 'excel';
    public const FORMATO_IMPRESION_NINGUNO = 'ninguno';

    protected $table = 'configuracion_asiento_contable';

    protected $fillable = [
        'enviar_mail_aprobacion',
        'mail_aprobador',
        'mail_copia_a',
        'horas_validez_token',
        'mail_asunto_aprobacion',
        'mail_texto_aprobacion',
        'formato_impresion_alta',
        'mail_asunto_aprobado_solicitante',
        'mail_asunto_rechazado_solicitante',
    ];

    protected $casts = [
        'enviar_mail_aprobacion' => 'boolean',
        'horas_validez_token' => 'integer',
    ];

    /** @return list<string> */
    public static function formatosImpresionAlta(): array
    {
        return [
            self::FORMATO_IMPRESION_PDF,
            self::FORMATO_IMPRESION_EXCEL,
            self::FORMATO_IMPRESION_NINGUNO,
        ];
    }

    public static function vigente(): self
    {
        $config = self::query()->orderBy('id')->first();
        if ($config) {
            return $config;
        }

        return self::create([]);
    }

    public function formatoImpresionAltaNormalizado(): string
    {
        $formato = strtolower(trim((string) ($this->formato_impresion_alta ?? self::FORMATO_IMPRESION_EXCEL)));

        return in_array($formato, self::formatosImpresionAlta(), true)
            ? $formato
            : self::FORMATO_IMPRESION_EXCEL;
    }

    public function urlImpresionAlta(int $asientoId): ?string
    {
        if ($asientoId <= 0) {
            return null;
        }

        return match ($this->formatoImpresionAltaNormalizado()) {
            self::FORMATO_IMPRESION_PDF => route('imprimir_pdf_asiento', [
                'id' => $asientoId,
                'inline' => 1,
            ]),
            self::FORMATO_IMPRESION_EXCEL => route('imprimir_excel_asiento', [
                'id' => $asientoId,
            ]),
            default => null,
        };
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
