<?php

declare(strict_types=1);

namespace App\Services\Stock;

use App\Mail\Stock\RecepcionProveedorAsientoAuditoriaDiaria;
use App\Models\Seguridad\Usuario;
use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Stock\RecepcionProveedorAsientoAuditoriaSupport;
use App\Support\Stock\RecepcionProveedorCuadreContableSupport;
use App\Support\Stock\RecepcionProveedorEstados;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Auditoría diaria de asientos contables ERP ↔ ctamov Anita para recepciones COM.
 */
final class RecepcionProveedorAsientoAuditoriaDiariaService
{
    public function __construct(
        private readonly RecepcionProveedorAsientoService $asientoService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(
        ?string $fechaCalendario = null,
        bool $enviarMail = true,
        ?int $empresaId = null,
    ): array {
        $config = config('recepcion_proveedor.auditoria_asientos_com_diaria', []);
        $fecha = $fechaCalendario ?? Carbon::yesterday()->toDateString();
        $tol = max(0.0, (float) ($config['tolerancia'] ?? RecepcionProveedorCuadreContableSupport::tolerancia()));
        $incluirImportadas = filter_var($config['incluir_importadas_anita'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $this->autenticarUsuarioSistema($config);

        $query = Recepcion_Proveedor::query()
            ->where('estado', RecepcionProveedorEstados::CONFIRMADA)
            ->whereDate('fecha', $fecha)
            ->orderBy('empresa_id')
            ->orderBy('numerorecepcion');

        if ($empresaId !== null && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        if (! $incluirImportadas) {
            $query->where('origen_carga', '!=', 'ANITA_IMPORT');
        }

        $recepciones = $query->get();

        $informe = [
            'fecha_calendario' => $fecha,
            'empresa_id' => $empresaId,
            'tolerancia' => $tol,
            'total_com' => $recepciones->count(),
            'ok' => 0,
            'omitidas' => 0,
            'discrepancias' => [],
            'errores_lectura' => [],
        ];

        foreach ($recepciones as $recepcion) {
            try {
                $resultado = $this->auditarRecepcion($recepcion, $tol);
            } catch (\Throwable $e) {
                $informe['errores_lectura'][] = [
                    'recepcion_id' => (int) $recepcion->id,
                    'com' => (int) $recepcion->numerorecepcion,
                    'mensaje' => $e->getMessage(),
                ];

                continue;
            }

            if (($resultado['estado'] ?? '') === 'omitida') {
                $informe['omitidas']++;

                continue;
            }

            if (($resultado['estado'] ?? '') === 'ok') {
                $informe['ok']++;

                continue;
            }

            $informe['discrepancias'][] = $resultado;
        }

        $informe['requiere_alerta'] = $informe['discrepancias'] !== [] || $informe['errores_lectura'] !== [];

        if ($enviarMail && $informe['requiere_alerta']) {
            $destino = trim((string) ($config['email'] ?? ''));
            if ($destino !== '') {
                try {
                    Mail::to($destino)->send(new RecepcionProveedorAsientoAuditoriaDiaria($informe));
                    $informe['mail_enviado'] = true;
                    $informe['mail_destino'] = $destino;
                } catch (\Throwable $e) {
                    $informe['mail_enviado'] = false;
                    $informe['mail_error'] = $e->getMessage();
                    Log::error('recepcion_proveedor.auditoria_asientos_com.mail_fallo', [
                        'fecha' => $fecha,
                        'destino' => $destino,
                        'msg' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('recepcion_proveedor.auditoria_asientos_com.ok', [
            'fecha_calendario' => $fecha,
            'total_com' => $informe['total_com'],
            'ok' => $informe['ok'],
            'discrepancias' => count($informe['discrepancias']),
            'errores_lectura' => count($informe['errores_lectura']),
            'requiere_alerta' => $informe['requiere_alerta'],
        ]);

        return $informe;
    }

    /**
     * @return array<string, mixed>
     */
    private function auditarRecepcion(Recepcion_Proveedor $recepcion, float $tol): array
    {
        $recepcion->loadMissing([
            'empresas',
            'asientos.tipoasientos',
            'asientos.asiento_movimientos.cuentacontables',
            'asientos.asiento_movimientos.centrocostos',
            'asientos.asiento_movimientos.monedas',
        ]);

        $base = [
            'recepcion_id' => (int) $recepcion->id,
            'com' => (int) $recepcion->numerorecepcion,
            'empresa_id' => (int) $recepcion->empresa_id,
            'empresa_codigo' => (int) ($recepcion->empresas->codigo ?? 0),
            'fecha' => optional($recepcion->fecha)->format('Y-m-d'),
            'asiento_id' => (int) ($recepcion->asiento_id ?? 0),
            'problemas' => [],
        ];

        if (! $this->asientoService->debeGenerarAsiento((int) $recepcion->empresa_id)) {
            return array_merge($base, ['estado' => 'omitida']);
        }

        $asientoId = (int) ($recepcion->asiento_id ?? 0);
        if ($asientoId <= 0) {
            return array_merge($base, [
                'estado' => 'discrepancia',
                'problemas' => ['Falta asiento contable en el ERP (contabilidad activa).'],
            ]);
        }

        $asiento = $recepcion->asientos;
        if (! $asiento) {
            return array_merge($base, [
                'estado' => 'discrepancia',
                'problemas' => ['La recepción referencia asiento id '.$asientoId.' inexistente en el ERP.'],
            ]);
        }

        $numeroAsiento = trim((string) ($asiento->numeroasiento ?? ''));
        $fechaAsiento = $asiento->fecha instanceof \DateTimeInterface
            ? $asiento->fecha->format('Y-m-d')
            : (string) $asiento->fecha;

        $base['numero_asiento'] = $numeroAsiento;
        $base['fecha_asiento'] = $fechaAsiento;

        $movimientos = $asiento->asiento_movimientos ?? collect();
        if ($movimientos->isEmpty()) {
            $base['problemas'][] = 'El asiento ERP no tiene movimientos contables.';
        }

        $totalesErp = RecepcionProveedorCuadreContableSupport::totalesDesdeMovimientos($movimientos);
        $base['debe_erp'] = $totalesErp['debe'];
        $base['haber_erp'] = $totalesErp['haber'];

        if (abs($totalesErp['debe'] - $totalesErp['haber']) >= $tol) {
            $base['problemas'][] = sprintf(
                'Asiento ERP desbalanceado: debe %s vs haber %s.',
                number_format($totalesErp['debe'], 2, ',', '.'),
                number_format($totalesErp['haber'], 2, ',', '.'),
            );
        }

        try {
            $preview = $this->asientoService->previewAsientoContable($recepcion);
            $debeEsperado = round((float) ($preview['total_debe'] ?? 0), 2);
            $base['debe_esperado'] = $debeEsperado;

            if (abs($debeEsperado - $totalesErp['debe']) >= $tol) {
                $base['problemas'][] = sprintf(
                    'Importe ERP (%s) distinto del esperado por la recepción (%s).',
                    number_format($totalesErp['debe'], 2, ',', '.'),
                    number_format($debeEsperado, 2, ',', '.'),
                );
            }
        } catch (\Throwable $e) {
            $base['problemas'][] = 'No se pudo calcular preview contable: '.$e->getMessage();
        }

        $fechaRecepcion = optional($recepcion->fecha)->format('Y-m-d');
        if ($fechaRecepcion !== null && $fechaAsiento !== '' && $fechaRecepcion !== $fechaAsiento) {
            $base['problemas'][] = 'Fecha asiento ERP '.$fechaAsiento.' distinta de fecha recepción '.$fechaRecepcion.'.';
        }

        $filasCtamov = RecepcionProveedorAsientoAuditoriaSupport::lineasCtamovPorCom($recepcion);
        $origenCtamov = 'com';

        if ($filasCtamov === [] && $numeroAsiento !== '') {
            $filasCtamov = RecepcionProveedorAsientoAuditoriaSupport::lineasCtamovPorNumeroAsiento(
                (int) ($recepcion->empresas->codigo ?? 0),
                $numeroAsiento,
            );
            $origenCtamov = 'numero_asiento';
        }

        $base['ctamov_origen'] = $origenCtamov;
        $base['ctamov_lineas'] = count($filasCtamov);

        if ($filasCtamov === []) {
            $base['problemas'][] = 'No hay movimientos ctamov en Anita para esta COM.';
        } else {
            $totalesAnita = RecepcionProveedorAsientoAuditoriaSupport::totalesDesdeCtamov($filasCtamov);
            $base['debe_anita'] = $totalesAnita['debe'];
            $base['haber_anita'] = $totalesAnita['haber'];

            if (abs($totalesAnita['debe'] - $totalesAnita['haber']) >= $tol) {
                $base['problemas'][] = sprintf(
                    'ctamov Anita desbalanceado: debe %s vs haber %s.',
                    number_format($totalesAnita['debe'], 2, ',', '.'),
                    number_format($totalesAnita['haber'], 2, ',', '.'),
                );
            }

            if (abs($totalesAnita['debe'] - $totalesErp['debe']) >= $tol) {
                $base['problemas'][] = sprintf(
                    'Importe ERP (%s) distinto de ctamov Anita (%s).',
                    number_format($totalesErp['debe'], 2, ',', '.'),
                    number_format($totalesAnita['debe'], 2, ',', '.'),
                );
            }

            $cabecera = RecepcionProveedorAsientoAuditoriaSupport::validarCabeceraCtamov(
                $recepcion,
                $filasCtamov,
                $numeroAsiento,
                $fechaAsiento,
                (int) ($recepcion->empresas->codigo ?? 0),
            );
            $base['problemas'] = array_merge($base['problemas'], $cabecera);

            $lineasErp = RecepcionProveedorAsientoAuditoriaSupport::normalizarLineasErp($movimientos);
            $lineasAnita = RecepcionProveedorAsientoAuditoriaSupport::normalizarLineasAnita($filasCtamov);
            $diffLineas = RecepcionProveedorAsientoAuditoriaSupport::diferenciasLineas($lineasErp, $lineasAnita, $tol);
            $base['problemas'] = array_merge($base['problemas'], $diffLineas);
        }

        $base['problemas'] = array_values(array_unique($base['problemas']));

        return array_merge($base, [
            'estado' => $base['problemas'] === [] ? 'ok' : 'discrepancia',
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function autenticarUsuarioSistema(array $config): void
    {
        if (Auth::check()) {
            return;
        }

        $usuarioId = (int) ($config['usuario_id'] ?? 0);
        if ($usuarioId <= 0) {
            $usuarioId = (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);
        }

        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            throw new \RuntimeException('No se pudo autenticar usuario de sistema para auditoría COM.');
        }
    }
}
