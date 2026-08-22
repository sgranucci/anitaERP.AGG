<?php

namespace App\Services\Compras;

use App\Mail\Compras\ComprobanteProveedorAnitaAuditoriaDiaria;
use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Contable\Asiento;
use App\Models\Seguridad\Usuario;
use App\Repositories\Contable\AsientoRepository;
use App\Support\Compras\AnitaSync\ComprobanteProveedor\ComprobanteProveedorAnitaIntegridadSupport;
use App\Support\Compras\ComprobanteProveedorAnitaSyncEstado;
use App\Support\Compras\ComprobanteProveedorEstados;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Auditoría diaria facturas ERP → Anita (compra, concmov, promov, aplicped, ctamov).
 */
final class ComprobanteProveedorAnitaAuditoriaDiariaService
{
    public function __construct(
        private readonly ComprobanteProveedorAnitaSyncService $anitaSync,
        private readonly ComprobanteProveedorAsientoService $asientoService,
        private readonly AsientoRepository $asientoRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        bool $enviarMail = true,
        ?bool $autoReparar = null,
    ): array {
        $config = config('comprobante_proveedor_anita.auditoria_diaria', []);
        $autoReparar ??= filter_var($config['auto_reparar'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $desde = $fechaDesde ?: Carbon::today()->subDays(max(1, (int) ($config['ventana_dias'] ?? 7)) - 1)->toDateString();
        $hasta = $fechaHasta ?: Carbon::today()->toDateString();

        $this->autenticarUsuarioSistema($config);

        $informe = [
            'fecha_calendario' => $desde.' → '.$hasta,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'auto_reparar' => $autoReparar,
            'total' => 0,
            'ok' => 0,
            'reparadas' => 0,
            'discrepancias' => [],
            'filas_reparadas' => [],
            'errores' => [],
            'filas' => [],
            'requiere_alerta' => false,
            'mail_enviado' => false,
            'mail_destino' => null,
            'mail_error' => null,
        ];

        $comprobantes = Comprobante_Proveedor::query()
            ->with([
                'proveedores',
                'tipotransaccion_compras',
                'comprobante_proveedor_conceptos',
                'comprobante_proveedor_cuotas',
                'ordencompras',
                'asientos',
                'empresas',
                'monedas',
            ])
            ->where('estado', ComprobanteProveedorEstados::CONTABILIZADO)
            ->whereNotNull('asiento_id')
            ->where(function ($q) {
                $q->whereNull('anita_sync_estado')
                    ->orWhere('anita_sync_estado', '!=', ComprobanteProveedorAnitaSyncEstado::IMPORTADO);
            })
            ->whereDate('fechacomprobante', '>=', $desde)
            ->whereDate('fechacomprobante', '<=', $hasta)
            ->orderBy('id')
            ->get();

        $informe['total'] = $comprobantes->count();

        foreach ($comprobantes as $cp) {
            try {
                $fila = $this->auditarUno($cp, $autoReparar);
            } catch (Throwable $e) {
                $informe['errores'][] = [
                    'id' => (int) $cp->id,
                    'etiqueta' => $this->etiqueta($cp),
                    'mensaje' => $e->getMessage(),
                ];
                Log::warning('ComprobanteProveedorAnitaAuditoria: error', [
                    'id' => (int) $cp->id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $informe['filas'][] = $fila;
            if (($fila['estado'] ?? '') === 'ok') {
                $informe['ok']++;
            } elseif (($fila['estado'] ?? '') === 'reparada') {
                $informe['reparadas']++;
                $informe['filas_reparadas'][] = $fila;
            } else {
                $informe['discrepancias'][] = $fila;
            }
        }

        $informe['requiere_alerta'] = $informe['discrepancias'] !== []
            || $informe['errores'] !== [];

        if ($enviarMail) {
            $this->enviarMailSiCorresponde($informe, $config);
        }

        return $informe;
    }

    /**
     * @return array<string, mixed>
     */
    private function auditarUno(Comprobante_Proveedor $cp, bool $autoReparar): array
    {
        $fila = [
            'id' => (int) $cp->id,
            'etiqueta' => $this->etiqueta($cp),
            'anita_nro_interno' => (int) ($cp->anita_nro_interno ?? 0),
            'anita_sync_estado' => (string) ($cp->anita_sync_estado ?? ''),
            'problemas' => [],
            'acciones' => [],
            'estado' => 'ok',
            'diagnostico' => [],
        ];

        if ((int) ($cp->anita_nro_interno ?? 0) <= 0
            || (string) ($cp->anita_sync_estado ?? '') === ComprobanteProveedorAnitaSyncEstado::ERROR
        ) {
            $fila['problemas'][] = 'Sync Anita incompleto o en ERROR';
        }

        $diag = ComprobanteProveedorAnitaIntegridadSupport::diagnosticar($cp);
        $fila['diagnostico'] = $diag;
        $fila['problemas'] = array_values(array_unique(array_merge($fila['problemas'], $diag['problemas'])));

        if ($fila['problemas'] === []) {
            return $fila;
        }

        if (! $autoReparar) {
            $fila['estado'] = 'discrepancia';

            return $fila;
        }

        try {
            $acciones = [];
            $this->reparar($cp, $fila['problemas'], $acciones);
            $fila['acciones'] = $acciones;
            $cp->refresh()->load([
                'proveedores', 'tipotransaccion_compras',
                'comprobante_proveedor_conceptos', 'comprobante_proveedor_cuotas',
                'ordencompras', 'asientos', 'empresas', 'monedas',
            ]);
            $diag2 = ComprobanteProveedorAnitaIntegridadSupport::diagnosticar($cp);
            $fila['diagnostico'] = $diag2;
            if ($diag2['problemas'] === [] && (int) ($cp->anita_nro_interno ?? 0) > 0) {
                $cp->forceFill([
                    'anita_sync_estado' => ComprobanteProveedorAnitaSyncEstado::SYNC_OK,
                    'anita_sync_error' => null,
                    'anita_sync_at' => now(),
                ])->save();
                $fila['estado'] = 'reparada';
                $fila['problemas'] = [];
                $fila['anita_nro_interno'] = (int) $cp->anita_nro_interno;
            } else {
                $fila['estado'] = 'discrepancia';
                $fila['problemas'] = $diag2['problemas'] !== []
                    ? $diag2['problemas']
                    : ['Reparación parcial: siguen fallas'];
            }
        } catch (Throwable $e) {
            $fila['estado'] = 'discrepancia';
            $fila['problemas'][] = 'Fallo al reparar: '.$e->getMessage();
            $fila['acciones'][] = 'error: '.$e->getMessage();
            $cp->forceFill([
                'anita_sync_estado' => ComprobanteProveedorAnitaSyncEstado::ERROR,
                'anita_sync_error' => $e->getMessage(),
                'anita_sync_at' => now(),
            ])->save();
        }

        return $fila;
    }

    /**
     * @param  list<string>  $problemas
     * @param  list<string>  $acciones
     */
    private function reparar(Comprobante_Proveedor $cp, array $problemas, array &$acciones): void
    {
        $texto = mb_strtolower(implode(' | ', $problemas));
        $necesitaSyncCompra = (int) ($cp->anita_nro_interno ?? 0) <= 0
            || str_contains($texto, 'compra')
            || str_contains($texto, 'concmov')
            || str_contains($texto, 'promov')
            || str_contains($texto, 'sync anita');

        if ($necesitaSyncCompra) {
            if ((int) ($cp->anita_nro_interno ?? 0) > 0) {
                $this->anitaSync->syncUpdate($cp);
                $acciones[] = 'syncUpdate compra/concmov/promov';
            } else {
                $this->anitaSync->syncCreate($cp);
                $acciones[] = 'syncCreate compra/concmov/promov';
            }
            $cp->refresh();
        }

        if (str_contains($texto, 'aplicped') || str_contains($texto, 'aplp_nro_interno')) {
            $this->anitaSync->resyncAplicped($cp);
            $acciones[] = 'resyncAplicped';
        }

        if (str_contains($texto, 'ctamov') && (int) ($cp->asiento_id ?? 0) > 0) {
            $asiento = Asiento::query()->with('asiento_movimientos')->find((int) $cp->asiento_id);
            if ($asiento !== null) {
                $payload = $this->asientoRepository->armarPayloadAnitaDesdeModelo($asiento);
                $this->asientoService->sincronizarCtamovAnita($cp, $payload);
                $acciones[] = 'sincronizarCtamovAnita';
            }
        }
    }

    private function etiqueta(Comprobante_Proveedor $cp): string
    {
        $tipo = (string) ($cp->tipotransaccion_compras?->abreviatura ?? '???');
        $letra = strtoupper(substr((string) ($cp->letra ?? '?'), 0, 1));

        return sprintf('%s %s-%s-%s', $tipo, $letra, (int) $cp->sucursal, (int) $cp->numerocomprobante);
    }

    /**
     * @param  array<string, mixed>  $informe
     * @param  array<string, mixed>  $config
     */
    private function enviarMailSiCorresponde(array &$informe, array $config): void
    {
        $destino = trim((string) ($config['email'] ?? ''));
        if ($destino === '') {
            return;
        }

        $debe = $informe['requiere_alerta']
            || filter_var($config['mail_siempre'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || (
                filter_var($config['mail_si_reparo'] ?? true, FILTER_VALIDATE_BOOLEAN)
                && (int) ($informe['reparadas'] ?? 0) > 0
            );

        if (! $debe) {
            return;
        }

        try {
            Mail::to($destino)->send(new ComprobanteProveedorAnitaAuditoriaDiaria($informe));
            $informe['mail_enviado'] = true;
            $informe['mail_destino'] = $destino;
        } catch (Throwable $e) {
            $informe['mail_error'] = $e->getMessage();
            Log::error('ComprobanteProveedorAnitaAuditoria: mail falló', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function autenticarUsuarioSistema(array $config): void
    {
        $usuarioId = (int) ($config['usuario_id'] ?? 0);
        if ($usuarioId <= 0 || Auth::id()) {
            return;
        }

        $usuario = Usuario::query()->find($usuarioId);
        if ($usuario !== null) {
            Auth::login($usuario);
        }
    }
}
