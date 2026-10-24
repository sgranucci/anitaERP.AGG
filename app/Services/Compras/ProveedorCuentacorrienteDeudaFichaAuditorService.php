<?php

declare(strict_types=1);

namespace App\Services\Compras;

use App\Mail\Compras\ProveedorCuentacorrienteDeudaFichaAuditorMail;
use App\Models\Configuracion\Empresa;
use App\Support\Compras\ProveedorCuentacorrienteConciliacionSupport;
use App\Support\Compras\ProveedorCuentacorrienteDeudaFichaCuadreSupport;
use App\Support\Compras\ProveedorDeudaMayorDeltaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Auditor diario: deuda vs ficha por empresa, y el delta mayor − deuda.
 * El delta se guarda por día. El mail lo muestra y avisa solo si se movió
 * respecto de la medición anterior. No graba ajustes de ficha. La deuda es la verdad.
 */
final class ProveedorCuentacorrienteDeudaFichaAuditorService
{
    public function __construct(
        private readonly ProveedorCuentacorrienteDeudaFichaCuadreSupport $cuadre,
        private readonly ProveedorDeudaMayorDeltaSupport $deltaMayor,
    ) {
    }

    /**
     * @return array{
     *   fecha: string,
     *   expresion: string,
     *   empresas: list<array<string, mixed>>,
     *   mails_enviados: int,
     *   mails_omitidos: int,
     *   requiere_alerta: bool
     * }
     */
    public function ejecutar(
        ?string $fechaCorte = null,
        bool $enviarMail = true,
        ?int $empresaId = null,
    ): array {
        $this->asegurarVistaCompilable();

        $config = config('compras.deuda_ficha_auditor', []);
        $fecha = $fechaCorte ?: Carbon::today()->toDateString();
        $tolerancia = (float) ($config['tolerancia'] ?? ProveedorCuentacorrienteConciliacionSupport::TOLERANCIA);
        $expresion = strtolower(trim((string) ($config['expresion'] ?? ProveedorCuentacorrienteDeudaFichaCuadreSupport::EXPRESION_ORIGEN)));
        if (! in_array($expresion, [
            ProveedorCuentacorrienteDeudaFichaCuadreSupport::EXPRESION_ORIGEN,
            ProveedorCuentacorrienteDeudaFichaCuadreSupport::EXPRESION_PESOS,
        ], true)) {
            $expresion = ProveedorCuentacorrienteDeudaFichaCuadreSupport::EXPRESION_ORIGEN;
        }
        $limite = max(10, (int) ($config['limite_filas_mail'] ?? 120));
        $mailSiempre = filter_var($config['mail_siempre'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $toleranciaDelta = (float) ($config['tolerancia_delta_mayor'] ?? 1);

        $empresas = $this->empresasAAuditar($empresaId, $fecha);
        $salida = [
            'fecha' => $fecha,
            'expresion' => $expresion,
            'empresas' => [],
            'mails_enviados' => 0,
            'mails_omitidos' => 0,
            'requiere_alerta' => false,
        ];

        foreach ($empresas as $empresa) {
            $informe = $this->cuadre->cuadrar(
                $fecha,
                (int) $empresa->id,
                null,
                $tolerancia,
                $expresion,
            );

            $grupos = array_values($informe['grupos'] ?? []);
            $hayDiferencia = $grupos !== [];
            $resumen = $informe['resumen'] ?? [];
            $delta = $this->deltaMayor->controlar((int) $empresa->id, $fecha, $toleranciaDelta);
            $deltaMovio = (int) ($delta['variaciones'] ?? 0) > 0;
            $paquete = [
                'fecha' => $fecha,
                'fecha_fmt' => Carbon::parse($fecha)->format('d/m/Y'),
                'expresion' => $expresion,
                'empresa_id' => (int) $empresa->id,
                'empresa_nombre' => trim((string) $empresa->nombre),
                'tolerancia' => $tolerancia,
                'requiere_alerta' => $hayDiferencia || $deltaMovio,
                'alerta_ficha' => $hayDiferencia,
                'alerta_delta' => $deltaMovio,
                'resumen' => $resumen,
                'grupos' => array_slice($grupos, 0, $limite),
                'grupos_total' => count($grupos),
                'grupos_truncados' => max(0, count($grupos) - $limite),
                'delta_mayor' => $delta,
                'mail_enviado' => false,
                'mail_destino' => null,
                'mail_error' => null,
            ];

            if ($hayDiferencia || $deltaMovio) {
                $salida['requiere_alerta'] = true;
            }

            if ($enviarMail && ($hayDiferencia || $mailSiempre)) {
                $paquete = array_merge($paquete, $this->enviarMail($paquete, $config));
                if (! empty($paquete['mail_enviado'])) {
                    $salida['mails_enviados']++;
                } else {
                    $salida['mails_omitidos']++;
                }
            } elseif (! $enviarMail) {
                $salida['mails_omitidos']++;
            }

            $salida['empresas'][] = $paquete;
        }

        return $salida;
    }

    /**
     * @return list<\App\Models\Configuracion\Empresa|\stdClass>
     */
    private function empresasAAuditar(?int $empresaId, string $fecha): array
    {
        if ($empresaId !== null && $empresaId > 0) {
            $empresa = Empresa::query()->find($empresaId);
            if ($empresa === null) {
                return [];
            }

            return [$empresa];
        }

        $idsCc = DB::table('proveedor_cuentacorriente')
            ->select('empresa_id')
            ->distinct()
            ->whereNotNull('empresa_id')
            ->pluck('empresa_id')
            ->map(static fn ($id) => (int) $id)
            ->all();
        $ids = array_values(array_unique(array_filter(
            array_merge($idsCc, $this->deltaMayor->empresasConSaldo($fecha)),
            static fn (int $id) => $id > 0
        )));
        sort($ids);

        if ($ids === []) {
            return [];
        }

        return Empresa::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $informe
     * @param  array<string, mixed>  $config
     * @return array{mail_enviado?: bool, mail_destino?: string, mail_error?: string}
     */
    private function enviarMail(array $informe, array $config): array
    {
        $destinos = $this->resolverDestinatarios($this->emailConfigurado($config));
        if ($destinos === []) {
            return ['mail_error' => 'Sin destinatario (COMPRAS_DEUDA_FICHA_AUDITOR_EMAIL).'];
        }

        try {
            Mail::to($destinos)->send(new ProveedorCuentacorrienteDeudaFichaAuditorMail($informe));

            return [
                'mail_enviado' => true,
                'mail_destino' => implode(', ', $destinos),
            ];
        } catch (Throwable $e) {
            Log::error('Auditor deuda/ficha proveedor: mail falló', [
                'empresa_id' => $informe['empresa_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return ['mail_error' => $e->getMessage()];
        }
    }

    /**
     * El cron corre como www-data (storage/framework/views OK).
     * En consola de operador a veces no hay escritura ahí: Blade falla con tempnam().
     */
    private function asegurarVistaCompilable(): void
    {
        $compiled = (string) config('view.compiled');
        if ($compiled !== '' && is_dir($compiled) && is_writable($compiled)) {
            return;
        }

        $fallback = rtrim(sys_get_temp_dir(), '/').'/anitaERP-views';
        if (! is_dir($fallback)) {
            @mkdir($fallback, 0775, true);
        }
        if (is_dir($fallback) && is_writable($fallback)) {
            config(['view.compiled' => $fallback]);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function emailConfigurado(array $config): string
    {
        foreach ([
            (string) ($config['email'] ?? ''),
            (string) config('compras.conciliacion_cc_proveedor.email', ''),
            (string) config('mail.from.address', ''),
        ] as $candidato) {
            if (trim($candidato) !== '') {
                return $candidato;
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function resolverDestinatarios(string $raw): array
    {
        $partes = preg_split('/[;,]+/', $raw) ?: [];
        $salida = [];
        foreach ($partes as $parte) {
            $email = strtolower(trim($parte));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $salida[] = $email;
            }
        }

        return array_values(array_unique($salida));
    }
}
