<?php

namespace App\Services\Compras;

use App\ApiAnita;
use App\Models\Compras\Requisicion;
use App\Support\Compras\AnitaSync\AnitaUsuarioBridgeSupport;
use App\Support\Compras\AnitaSync\Requisicion\ReqmaeCabeceraAnitaMapper;
use App\Support\Compras\AnitaSync\Requisicion\ReqmrefLineaAnitaMapper;
use App\Support\Compras\AnitaSync\Requisicion\ReqmovLineaAnitaMapper;
use App\Support\Compras\AnitaSync\Requisicion\RequisicionAnitaNroInternoSupport;
use App\Support\Compras\AnitaSync\Requisicion\RequisicionAnitaSyncContext;
use App\Support\Compras\RequisicionAnitaColisionSupport;
use App\Support\Compras\RequisicionAnitaNumeracionSupport;
use App\Support\Compras\RequisicionAnitaSyncEstado;
use App\Support\Compras\RequisicionProvisorioSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Sincroniza requisición ERP → Anita (reqmae, reqmov, reqmref).
 *
 * Escritura atómica con ERP: {@see escribirCreacion}/{@see escribirActualizacion} se invocan
 * dentro de la transacción MySQL antes del commit. Si el commit falla, {@see rollbackAnita}
 * o {@see restaurarDesdeErp} compensan Anita.
 */
class RequisicionAnitaSyncService
{
    private const SISTEMA_COMPRAS = 'compras';

    public function __construct(
        private RequisicionAnitaNroInternoSupport $nroInternoSupport,
    ) {}

    /**
     * Alta Anita (reqmov → reqmae → reqmref). Sin commit ERP; sin actualizar estado sync.
     */
    public function escribirCreacion(Requisicion $requisicion): void
    {
        $this->prepararYValidar($requisicion);

        $ctx = $this->nuevoContexto($requisicion);

        if (RequisicionAnitaColisionSupport::existeNroEnReqmae($ctx->numeroRequisicion())) {
            $this->escribirActualizacion($requisicion);

            return;
        }

        $this->insertarLineasMovimiento($ctx);
        $this->insertarCabecera($ctx);
        $this->insertarReferenciasPresupuesto($ctx);
        RequisicionAnitaNumeracionSupport::registrarNumeroAsignadoEnNumerador($ctx->numeroRequisicion());
    }

    /**
     * Modificación Anita: borra detalle, cabecera, reinserta líneas y reqmref.
     */
    public function escribirActualizacion(Requisicion $requisicion): void
    {
        $this->prepararYValidar($requisicion);

        $ctx = $this->nuevoContexto($requisicion);

        $this->eliminarDetalleAnita($ctx);

        if (RequisicionAnitaColisionSupport::existeNroEnReqmae($ctx->numeroRequisicion())) {
            $this->actualizarCabecera($ctx);
        } else {
            $this->insertarCabecera($ctx);
        }

        $this->insertarLineasMovimiento($ctx);
        $this->insertarReferenciasPresupuesto($ctx);
        RequisicionAnitaNumeracionSupport::registrarNumeroAsignadoEnNumerador($ctx->numeroRequisicion());
    }

    /**
     * Elimina reqmref, reqmov y reqmae (compensación tras rollback ERP en alta).
     */
    public function rollbackAnita(int $numerorequisicion): void
    {
        if ($numerorequisicion <= 0) {
            return;
        }

        $whereMae = ' WHERE reqm_nro = '.$numerorequisicion;
        $whereMov = ' WHERE reqv_nro = '.$numerorequisicion;
        $whereRef = ' WHERE reqr_nro_requi = '.$numerorequisicion;

        $this->apiDelete('reqmref', $whereRef);
        $this->apiDelete('reqmov', $whereMov);
        $this->apiDelete('reqmae', $whereMae);

        Log::info('RequisicionAnitaSync: rollback Anita completado', [
            'numerorequisicion' => $numerorequisicion,
        ]);
    }

    /**
     * Tras rollback ERP en edición: vuelve a escribir Anita según el estado ERP vigente.
     */
    public function restaurarDesdeErp(Requisicion $requisicion): void
    {
        if (RequisicionAnitaColisionSupport::existeNroEnReqmae((int) $requisicion->numerorequisicion)) {
            $this->escribirActualizacion($requisicion);
        } else {
            $this->rollbackAnita((int) $requisicion->numerorequisicion);
        }
    }

    public function marcarSyncOk(Requisicion $requisicion): void
    {
        $requisicion->forceFill([
            'anita_sync_estado' => RequisicionAnitaSyncEstado::SYNC_OK,
            'anita_sync_error' => null,
            'anita_sync_at' => now(),
        ])->save();

        Log::info('RequisicionAnitaSync: OK', [
            'requisicion_id' => $requisicion->id,
            'numerorequisicion' => $requisicion->numerorequisicion,
        ]);
    }

    public function marcarSyncError(Requisicion $requisicion, \Throwable $e): void
    {
        $requisicion->forceFill([
            'anita_sync_estado' => RequisicionAnitaSyncEstado::ERROR,
            'anita_sync_error' => mb_substr($e->getMessage(), 0, 2000),
            'anita_sync_at' => now(),
        ])->save();

        Log::warning('RequisicionAnitaSync: ERROR', [
            'requisicion_id' => $requisicion->id,
            'numerorequisicion' => $requisicion->numerorequisicion,
            'error' => $e->getMessage(),
        ]);
    }

    /** Reintento batch (commit ERP ya existente). */
    public function syncCreate(Requisicion $requisicion): void
    {
        try {
            $this->escribirCreacion($requisicion);
            $this->marcarSyncOk($requisicion->fresh() ?? $requisicion);
        } catch (\Throwable $e) {
            $this->marcarSyncError($requisicion, $e);
            throw $e;
        }
    }

    public function syncUpdate(Requisicion $requisicion): void
    {
        try {
            $this->escribirActualizacion($requisicion);
            $this->marcarSyncOk($requisicion->fresh() ?? $requisicion);
        } catch (\Throwable $e) {
            $this->marcarSyncError($requisicion, $e);
            throw $e;
        }
    }

    public function reintentarPendientes(int $limite = 50): int
    {
        $ids = Requisicion::query()
            ->where(function ($q) {
                $q->whereNull('anita_sync_estado')
                    ->orWhere('anita_sync_estado', RequisicionAnitaSyncEstado::ERROR)
                    ->orWhere('anita_sync_estado', RequisicionAnitaSyncEstado::PENDIENTE);
            })
            ->orderBy('id')
            ->limit($limite)
            ->pluck('id');

        $ok = 0;
        foreach ($ids as $id) {
            $req = Requisicion::query()->find($id);
            if (! $req) {
                continue;
            }
            try {
                if (RequisicionAnitaColisionSupport::existeNroEnReqmae((int) $req->numerorequisicion)) {
                    $this->syncUpdate($req);
                } else {
                    $this->syncCreate($req);
                }
                $ok++;
            } catch (\Throwable $e) {
                Log::warning('RequisicionAnitaSync: reintento fallido', [
                    'requisicion_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $ok;
    }

    public function contarResincronizacionErp(?int $requisicionId = null): int
    {
        return $this->queryResincronizacionErp($requisicionId)->count();
    }

    /**
     * Re-sincroniza requisiciones originadas en ERP ya grabadas en Anita (corrige reqm_usuario, etc.).
     *
     * @return array{procesadas: int, resincronizadas: int, omitidas: int, errores: int}
     */
    public function resincronizarErpEnAnita(?int $requisicionId = null, ?callable $onError = null): array
    {
        AnitaUsuarioBridgeSupport::limpiarCache();

        $stats = [
            'procesadas' => 0,
            'resincronizadas' => 0,
            'omitidas' => 0,
            'errores' => 0,
        ];

        foreach ($this->queryResincronizacionErp($requisicionId)->cursor() as $requisicion) {
            $stats['procesadas']++;
            try {
                if (! RequisicionAnitaColisionSupport::existeNroEnReqmae((int) $requisicion->numerorequisicion)) {
                    $stats['omitidas']++;

                    continue;
                }

                $this->syncUpdate($requisicion);
                $stats['resincronizadas']++;
            } catch (\Throwable $e) {
                $stats['errores']++;
                Log::warning('RequisicionAnitaSync: resincronización fallida', [
                    'requisicion_id' => $requisicion->id,
                    'numerorequisicion' => $requisicion->numerorequisicion,
                    'error' => $e->getMessage(),
                ]);
                if ($onError !== null) {
                    $onError($requisicion, $e);
                }
            }
        }

        return $stats;
    }

    private function queryResincronizacionErp(?int $requisicionId = null): Builder
    {
        $query = Requisicion::query()
            ->where('anita_sync_estado', RequisicionAnitaSyncEstado::SYNC_OK)
            ->where('estado', '!=', RequisicionProvisorioSupport::nombreEstadoProvisorio())
            ->orderBy('id');

        if ($requisicionId !== null && $requisicionId > 0) {
            $query->whereKey($requisicionId);
        }

        return $query;
    }

    private function prepararYValidar(Requisicion $requisicion): void
    {
        $this->cargarRelaciones($requisicion);

        if ($requisicion->requisicion_articulos->isEmpty()) {
            throw new \RuntimeException('La requisición no tiene líneas para sincronizar con Anita.');
        }

        Requisicion::query()->whereKey($requisicion->id)->lockForUpdate()->first();
        $this->prepararNumeracionLineas($requisicion);
        $requisicion->refresh();
        $this->cargarRelaciones($requisicion);
    }

    private function nuevoContexto(Requisicion $requisicion): RequisicionAnitaSyncContext
    {
        return new RequisicionAnitaSyncContext(
            $requisicion,
            (int) (Auth::id() ?? $requisicion->creousuario_id ?? 0)
        );
    }

    private function cargarRelaciones(Requisicion $requisicion): void
    {
        $requisicion->loadMissing([
            'empresas',
            'centrocostos',
            'proveedores',
            'formapagos',
            'usuarios',
            'requisicion_articulos' => fn ($q) => $q->orderBy('id'),
            'requisicion_articulos.articulos.categorias',
            'requisicion_articulos.articulos.lineas',
            'requisicion_articulos.articulos.materiales',
            'requisicion_articulos.articulos.mventas',
            'requisicion_articulos.articulos.unidadesdemedidas',
            'requisicion_articulos.articulos.impuestos',
            'requisicion_articulos.monedas',
            'requisicion_articulos.centrocostos_destino',
            'requisicion_articulos.partidagastos.presupuestos',
            'requisicion_articulos.partidagastos.presupuesto_escenarios',
            'requisicion_articulos.partidagastos.cuentacontables',
            'requisicion_articulos.capexs.presupuestos',
        ]);
    }

    private function prepararNumeracionLineas(Requisicion $requisicion): void
    {
        $orden = 0;
        foreach ($requisicion->requisicion_articulos->sortBy('id') as $linea) {
            $nroInterno = $this->nroInternoSupport->asignarInternoSiFalta($linea);
            RequisicionAnitaNroInternoSupport::registrarInternoAsignado($nroInterno);
            $linea->forceFill(['anita_nro_orden' => $orden])->save();
            $orden++;
        }
    }

    private function insertarCabecera(RequisicionAnitaSyncContext $ctx): void
    {
        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'insert',
            'tabla' => 'reqmae',
            'sistema' => self::SISTEMA_COMPRAS,
            'campos' => ReqmaeCabeceraAnitaMapper::camposInsert(),
            'valores' => ReqmaeCabeceraAnitaMapper::valoresInsert($ctx),
        ], 'reqmae insert requisicion');
    }

    private function actualizarCabecera(RequisicionAnitaSyncContext $ctx): void
    {
        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'update',
            'tabla' => 'reqmae',
            'sistema' => self::SISTEMA_COMPRAS,
            'valores' => ReqmaeCabeceraAnitaMapper::valoresUpdate($ctx),
            'whereArmado' => $ctx->whereReqmae(),
        ], 'reqmae update requisicion');
    }

    private function insertarLineasMovimiento(RequisicionAnitaSyncContext $ctx): void
    {
        $api = new ApiAnita;
        $orden = 0;

        foreach ($ctx->requisicion->requisicion_articulos->sortBy('id') as $linea) {
            $nroOrden = (int) ($linea->anita_nro_orden ?? $orden);
            $nroInterno = (int) ($linea->anita_nro_interno ?? 0);
            if ($nroInterno <= 0) {
                throw new \RuntimeException('Línea requisición #'.$linea->id.' sin nro_interno Anita.');
            }

            $api->apiCallEscritura([
                'acc' => 'insert',
                'tabla' => 'reqmov',
                'sistema' => self::SISTEMA_COMPRAS,
                'campos' => ReqmovLineaAnitaMapper::camposInsert(),
                'valores' => ReqmovLineaAnitaMapper::valoresInsert($ctx, $linea, $nroOrden, $nroInterno),
            ], 'reqmov insert requisicion orden '.$nroOrden);

            $orden++;
        }
    }

    private function insertarReferenciasPresupuesto(RequisicionAnitaSyncContext $ctx): void
    {
        $api = new ApiAnita;
        $orden = 0;

        foreach ($ctx->requisicion->requisicion_articulos->sortBy('id') as $linea) {
            $nroOrden = (int) ($linea->anita_nro_orden ?? $orden);
            $nroInterno = (int) ($linea->anita_nro_interno ?? 0);
            if ($nroInterno <= 0) {
                throw new \RuntimeException('Línea requisición #'.$linea->id.' sin nro_interno Anita.');
            }

            if (ReqmrefLineaAnitaMapper::tieneDatosPresupuesto($linea)) {
                $api->apiCallEscritura([
                    'acc' => 'insert',
                    'tabla' => 'reqmref',
                    'sistema' => self::SISTEMA_COMPRAS,
                    'campos' => ReqmrefLineaAnitaMapper::camposInsert(),
                    'valores' => ReqmrefLineaAnitaMapper::valoresInsert($ctx, $linea, $nroOrden, $nroInterno),
                ], 'reqmref insert requisicion orden '.$nroOrden);
            }

            $orden++;
        }
    }

    private function eliminarDetalleAnita(RequisicionAnitaSyncContext $ctx): void
    {
        $this->apiDelete('reqmov', $ctx->whereReqmov());
        $this->apiDelete('reqmref', $ctx->whereReqmref());
    }

    private function apiDelete(string $tabla, string $whereArmado): void
    {
        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'delete',
            'tabla' => $tabla,
            'sistema' => self::SISTEMA_COMPRAS,
            'whereArmado' => $whereArmado,
        ], "{$tabla} delete requisicion");
    }
}
