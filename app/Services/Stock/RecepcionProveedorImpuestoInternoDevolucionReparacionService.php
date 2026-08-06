<?php

declare(strict_types=1);

namespace App\Services\Stock;

use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Stock\RecepcionProveedorEstados;
use App\Support\Stock\RecepcionProveedorImpuestoInternoSupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Corrige devoluciones confirmadas que no grabaron impuesto interno (campo + asiento + Anita).
 */
class RecepcionProveedorImpuestoInternoDevolucionReparacionService
{
    public function __construct(
        private readonly RecepcionProveedorAsientoService $asientoService,
        private readonly RecepcionProveedorAnitaBridgeService $anitaBridge,
    ) {
    }

    /**
     * @param  array{id?: int|null, dry_run?: bool, limite?: int|null, forzar?: bool}  $opciones
     * @return array{
     *     candidatas: int,
     *     reparadas: int,
     *     pendientes: int,
     *     omitidas: int,
     *     errores: int,
     *     detalle: list<array<string, mixed>>
     * }
     */
    public function ejecutar(array $opciones = []): array
    {
        $dryRun = (bool) ($opciones['dry_run'] ?? false);
        $forzar = (bool) ($opciones['forzar'] ?? false);
        $idFiltro = isset($opciones['id']) ? (int) $opciones['id'] : null;
        $limite = isset($opciones['limite']) ? (int) $opciones['limite'] : null;

        $candidatas = $this->listarCandidatas($idFiltro, $limite);

        $stats = [
            'candidatas' => $candidatas->count(),
            'reparadas' => 0,
            'pendientes' => 0,
            'omitidas' => 0,
            'errores' => 0,
            'detalle' => [],
        ];

        foreach ($candidatas as $devolucion) {
            try {
                $resultado = $this->procesarDevolucion($devolucion, $dryRun, $forzar);
                $stats['detalle'][] = $resultado;
                $estado = (string) ($resultado['estado'] ?? '');
                if ($estado === 'reparada') {
                    $stats['reparadas']++;
                } elseif ($estado === 'pendiente') {
                    $stats['pendientes']++;
                } else {
                    $stats['omitidas']++;
                }
            } catch (\Throwable $e) {
                $stats['errores']++;
                $stats['detalle'][] = [
                    'recepcion_id' => (int) $devolucion->id,
                    'numerorecepcion' => (int) $devolucion->numerorecepcion,
                    'estado' => 'error',
                    'mensaje' => $e->getMessage(),
                ];
                Log::error('RecepcionProveedorImpuestoInternoDevolucionReparacion: error', [
                    'recepcion_id' => $devolucion->id,
                    'exception' => $e,
                ]);
            }
        }

        return $stats;
    }

    public function contarCandidatas(?int $id = null): int
    {
        return $this->listarCandidatas($id, null)->count();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Recepcion_Proveedor>
     */
    private function listarCandidatas(?int $idFiltro, ?int $limite)
    {
        $query = Recepcion_Proveedor::query()
            ->where('tipo', Recepcion_Proveedor::TIPO_DEVOLUCION)
            ->where('estado', RecepcionProveedorEstados::CONFIRMADA)
            ->whereNotNull('recepcion_referencia_id')
            ->where(function ($q) {
                $q->whereNull('impuesto_interno')
                    ->orWhere('impuesto_interno', '<=', 0.009);
            })
            ->whereHas('recepcion_referencia', function ($q) {
                $q->where('impuesto_interno', '>', 0.009);
            })
            ->with([
                'recepcion_proveedor_articulos.articulos',
                'recepcion_referencia.recepcion_proveedor_articulos.articulos',
                'ordencompras',
                'asientos',
            ])
            ->orderBy('id');

        if ($idFiltro !== null && $idFiltro > 0) {
            $query->where('id', $idFiltro);
        }

        if ($limite !== null && $limite > 0) {
            $query->limit($limite);
        }

        return $query->get()->filter(function (Recepcion_Proveedor $dev) {
            $origen = $dev->recepcion_referencia;
            if (! $origen instanceof Recepcion_Proveedor) {
                return false;
            }

            $ii = RecepcionProveedorImpuestoInternoSupport::calcularImpuestoInternoProporcionalEntreRecepciones(
                $origen,
                $dev
            );

            return $ii !== null && $ii > 0.000001;
        })->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function procesarDevolucion(Recepcion_Proveedor $devolucion, bool $dryRun, bool $forzar = false): array
    {
        $origen = $devolucion->recepcion_referencia;
        if (! $origen instanceof Recepcion_Proveedor) {
            return [
                'recepcion_id' => (int) $devolucion->id,
                'numerorecepcion' => (int) $devolucion->numerorecepcion,
                'estado' => 'omitida',
                'mensaje' => 'Sin recepción origen.',
            ];
        }

        $iiNuevo = RecepcionProveedorImpuestoInternoSupport::calcularImpuestoInternoProporcionalEntreRecepciones(
            $origen,
            $devolucion
        );
        if ($iiNuevo === null || $iiNuevo <= 0.000001) {
            return [
                'recepcion_id' => (int) $devolucion->id,
                'numerorecepcion' => (int) $devolucion->numerorecepcion,
                'estado' => 'omitida',
                'mensaje' => 'No corresponde impuesto interno proporcional.',
            ];
        }

        $base = [
            'recepcion_id' => (int) $devolucion->id,
            'numerorecepcion' => (int) $devolucion->numerorecepcion,
            'ordencompra' => (string) ($devolucion->ordencompras->numeroordencompra ?? ''),
            'origen_nro' => (int) $origen->numerorecepcion,
            'ii_origen' => round((float) $origen->impuesto_interno, 2),
            'ii_anterior' => round((float) ($devolucion->impuesto_interno ?? 0), 2),
            'ii_nuevo' => $iiNuevo,
            'asiento_id' => (int) ($devolucion->asiento_id ?? 0),
        ];

        if ($dryRun) {
            return array_merge($base, ['estado' => 'pendiente']);
        }

        DB::transaction(function () use ($devolucion, $iiNuevo, $forzar) {
            $devolucion->forceFill(['impuesto_interno' => $iiNuevo])->save();
            $devolucion->refresh();
            $devolucion->loadMissing([
                'recepcion_proveedor_articulos.articulos.articulo_cuentacontables',
                'ordencompras',
                'proveedores',
                'empresas',
                'asientos',
            ]);

            if ((int) ($devolucion->asiento_id ?? 0) > 0
                && $this->asientoService->debeGenerarAsiento((int) $devolucion->empresa_id)
            ) {
                $this->asientoService->recuadrarAsientoExistente($devolucion, $forzar);
            }
        });

        // Anita fuera de la TX ERP: reescribe recepmov (incluye IMPINTERNO con cantidad -1).
        try {
            if (! Auth::check()) {
                $usuarioId = (int) config('recepcion_proveedor.auditoria_asientos_com_diaria.usuario_id', 1);
                if ($usuarioId > 0) {
                    Auth::loginUsingId($usuarioId);
                }
            }
            $this->anitaBridge->repararDetallePreservandoCabecera(
                $devolucion->fresh([
                    'proveedores', 'empresas', 'ordencompras',
                    'recepcion_proveedor_articulos.articulos.categorias',
                    'recepcion_proveedor_articulos.articulos.impuestos',
                    'recepcion_proveedor_articulos.centrocostos',
                ])
            );
        } catch (\Throwable $e) {
            Log::warning('RecepcionProveedorImpuestoInternoDevolucionReparacion: Anita detalle no reparado', [
                'recepcion_id' => $devolucion->id,
                'mensaje' => $e->getMessage(),
            ]);

            return array_merge($base, [
                'estado' => 'reparada',
                'mensaje' => 'ERP OK; Anita detalle: '.$e->getMessage(),
            ]);
        }

        return array_merge($base, ['estado' => 'reparada']);
    }
}
