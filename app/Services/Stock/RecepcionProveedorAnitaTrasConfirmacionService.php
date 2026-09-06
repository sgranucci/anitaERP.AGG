<?php

declare(strict_types=1);

namespace App\Services\Stock;

use App\Models\Seguridad\Usuario;
use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Stock\RecepcionProveedorCtamovCuadreSupport;
use App\Support\Stock\RecepcionProveedorCuadreContableSupport;
use App\Support\Stock\RecepcionProveedorEstados;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Verifica y repara recepmae + ctamov Anita tras confirmar una recepción COM en el ERP.
 */
final class RecepcionProveedorAnitaTrasConfirmacionService
{
    public function __construct(
        private readonly RecepcionProveedorAsientoService $asientoService,
        private readonly RecepcionProveedorAnitaBridgeService $anitaBridge,
        private readonly RecepcionProveedorAnitaResincronizacionErpService $resincronizacionErpService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function verificarYReparar(int $recepcionId): array
    {
        $this->autenticarUsuarioSistema();

        $recepcion = Recepcion_Proveedor::query()
            ->with([
                'empresas',
                'asientos.asiento_movimientos.cuentacontables',
                'asientos.asiento_movimientos.centrocostos',
                'asientos.asiento_movimientos.monedas',
            ])
            ->find($recepcionId);

        if ($recepcion === null) {
            return [
                'recepcion_id' => $recepcionId,
                'estado' => 'omitida',
                'motivo' => 'Recepción no encontrada.',
            ];
        }

        if ($recepcion->estado !== RecepcionProveedorEstados::CONFIRMADA) {
            return [
                'recepcion_id' => $recepcionId,
                'com' => (int) $recepcion->numerorecepcion,
                'estado' => 'omitida',
                'motivo' => 'La recepción no está CONFIRMADA.',
            ];
        }

        if ($recepcion->origen_carga === 'ANITA_IMPORT') {
            return [
                'recepcion_id' => $recepcionId,
                'com' => (int) $recepcion->numerorecepcion,
                'estado' => 'omitida',
                'motivo' => 'Origen ANITA_IMPORT: no se repara desde ERP.',
            ];
        }

        $evaluacion = $this->evaluar($recepcion);

        if (! ($evaluacion['requiere_reparacion'] ?? false)) {
            return array_merge($evaluacion, ['estado' => 'ok']);
        }

        Log::warning('recepcion_proveedor.anita_tras_confirmacion.reparando', [
            'recepcion_id' => $recepcionId,
            'com' => (int) $recepcion->numerorecepcion,
            'problemas' => $evaluacion['problemas'] ?? [],
        ]);

        $this->reparar($recepcion);

        $recepcion = $recepcion->fresh([
            'empresas',
            'asientos.asiento_movimientos.cuentacontables',
            'asientos.asiento_movimientos.centrocostos',
            'asientos.asiento_movimientos.monedas',
        ]);

        $post = $this->evaluar($recepcion);
        if ($post['requiere_reparacion'] ?? false) {
            throw new \RuntimeException(
                'Tras reparación siguen discrepancias COM '.(int) $recepcion->numerorecepcion
                .': '.implode(' ', $post['problemas'] ?? [])
            );
        }

        Log::info('recepcion_proveedor.anita_tras_confirmacion.reparada', [
            'recepcion_id' => $recepcionId,
            'com' => (int) $recepcion->numerorecepcion,
        ]);

        return array_merge($post, [
            'estado' => 'reparada',
            'problemas_iniciales' => $evaluacion['problemas'] ?? [],
        ]);
    }

    /**
     * @return array{
     *   requiere_reparacion: bool,
     *   problemas: list<string>,
     *   ctamov?: array<string, mixed>,
     *   recepmae_anita?: int
     * }
     */
    public function evaluar(Recepcion_Proveedor $recepcion): array
    {
        $problemas = [];
        $requiereReparacion = false;

        if ($this->resincronizacionErpService->requiereResincronizacion($recepcion)) {
            $requiereReparacion = true;
            $problemas[] = 'Falta recepmae Anita o claves COM incorrectas.';
        } elseif ($this->anitaBridge->listarRecepmaePorClaveAuditoria($recepcion) === []) {
            $requiereReparacion = true;
            $problemas[] = 'Falta cabecera recepmae en Anita para la clave COM.';
        }

        $detalle = $this->anitaBridge->diagnosticoDetalleComAnita($recepcion);
        if ($detalle['incompleto'] ?? false) {
            $requiereReparacion = true;
            $problemas[] = (string) ($detalle['mensaje'] ?? 'Detalle Anita incompleto (recepmov/stkmov).');
        }

        $ctamov = null;
        if (
            $this->asientoService->debeGenerarAsiento((int) $recepcion->empresa_id)
            && ! $this->asientoService->recepcionSinImporteContable($recepcion)
            && (int) ($recepcion->asiento_id ?? 0) > 0
        ) {
            $preview = $this->asientoService->previewAsientoContable($recepcion);
            $debeEsperado = round((float) ($preview['total_debe'] ?? 0), 2);
            $tol = max(0.0, (float) config(
                'recepcion_proveedor.auditoria_asientos_com_diaria.tolerancia',
                RecepcionProveedorCuadreContableSupport::tolerancia()
            ));

            $movimientos = $recepcion->asientos?->asiento_movimientos ?? collect();
            $ctamov = RecepcionProveedorCtamovCuadreSupport::evaluarContraErp(
                $recepcion,
                $movimientos,
                $debeEsperado,
                $tol,
            );

            if ($ctamov['requiere_reparacion'] ?? false) {
                $requiereReparacion = true;
                $problemas = array_merge($problemas, $ctamov['motivos'] ?? []);
            }
        }

        $problemas = array_values(array_unique($problemas));

        return [
            'recepcion_id' => (int) $recepcion->id,
            'com' => (int) $recepcion->numerorecepcion,
            'requiere_reparacion' => $requiereReparacion,
            'problemas' => $problemas,
            'ctamov' => $ctamov,
            'recepmae_anita' => count($this->anitaBridge->listarRecepmaePorClaveAuditoria($recepcion)),
        ];
    }

    public function reparar(Recepcion_Proveedor $recepcion): void
    {
        $relacionesDetalle = [
            'proveedores', 'empresas', 'ordencompras',
            'recepcion_proveedor_articulos.articulos.categorias',
            'recepcion_proveedor_articulos.articulos.impuestos',
            'recepcion_proveedor_articulos.centrocostos',
        ];

        if ($this->resincronizacionErpService->requiereResincronizacion($recepcion)) {
            $this->resincronizacionErpService->repararRecepcionConfirmada((int) $recepcion->id);
            $recepcion = $recepcion->fresh(array_merge(['asientos'], $relacionesDetalle)) ?? $recepcion;
        } elseif ($this->anitaBridge->listarRecepmaePorClaveAuditoria($recepcion) === []) {
            $this->anitaBridge->sincronizarRecepcion($recepcion->fresh($relacionesDetalle));
            $recepcion = $recepcion->fresh(array_merge(['asientos'], $relacionesDetalle)) ?? $recepcion;
        } elseif ($this->resincronizacionErpService->requiereReparacionDetalleIncompleto($recepcion)
            || $this->anitaBridge->detalleComIncompletoEnAnita($recepcion)
        ) {
            if ($this->resincronizacionErpService->requiereReparacionDetalleIncompleto($recepcion)) {
                $this->anitaBridge->repararDetallePreservandoCabecera($recepcion->fresh($relacionesDetalle));
            } else {
                // Cabecera por clave sin vínculo usable: -pendmovp y resync completo.
                if ($this->anitaBridge->tieneDetalleComEnAnita($recepcion)) {
                    $this->anitaBridge->ajustarPendmovpRecepcion($recepcion->fresh([
                        'ordencompras',
                        'recepcion_proveedor_articulos.articulos',
                    ]), -1);
                }
                $this->anitaBridge->sincronizarRecepcion($recepcion->fresh($relacionesDetalle));
            }
            $recepcion = $recepcion->fresh(array_merge(['asientos'], $relacionesDetalle)) ?? $recepcion;
        }

        if (
            (int) ($recepcion->asiento_id ?? 0) > 0
            && $this->asientoService->debeGenerarAsiento((int) $recepcion->empresa_id)
            && ! $this->asientoService->recepcionSinImporteContable($recepcion)
        ) {
            $this->asientoService->reconciliarCtamovConErp($recepcion->fresh([
                'asientos',
                'empresas',
                'proveedores',
                'ordencompras',
                'recepcion_proveedor_articulos.articulos.categorias',
                'recepcion_proveedor_articulos.articulos.impuestos',
                'recepcion_proveedor_articulos.centrocostos',
            ]));
        }
    }

    private function autenticarUsuarioSistema(): void
    {
        if (Auth::check()) {
            return;
        }

        $usuarioId = (int) config('recepcion_proveedor.anita_tras_confirmacion.usuario_id', 1);
        if ($usuarioId <= 0) {
            $usuarioId = (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);
        }

        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            throw new \RuntimeException('No se pudo autenticar usuario de sistema para reparación Anita COM.');
        }
    }
}
