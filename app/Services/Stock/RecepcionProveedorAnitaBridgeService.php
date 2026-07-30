<?php

namespace App\Services\Stock;

use App\ApiAnita;
use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Stock\Recepcion_Proveedor_Articulo;
use App\Services\Compras\OrdencompraAnitaSyncService;
use App\Models\Contable\Asiento_Movimiento;
use App\Support\Stock\RecepcionProveedorAnitaClaveSupport;
use App\Support\Stock\RecepcionProveedorAnitaColisionSupport;
use App\Support\Stock\RecepcionProveedorAsientoAnitaCtamovSupport;
use App\Support\Stock\RecepcionProveedorAnitaEscrituraSupport;
use App\Support\Stock\RecepcionProveedorAnitaOrdenLineaSupport;
use App\Support\Stock\RecepcionProveedorAnitaReferenciaSupport;
use App\Support\Stock\RecepcionProveedorAnitaWhereSupport;
use App\Support\Stock\RecepcionProveedorAnitaCorrespondenciaSupport;
use App\Support\Stock\RecepcionProveedorEstados;
use App\Support\Stock\RecepcionProveedorImpuestoInternoSupport;
use App\Support\Stock\AnitaStkmovClaveErpSupport;
use App\Support\Stock\RecepcionProveedorStkmovAnitaSupport;
use App\Support\Stock\StkmaePrecioCompraAnitaBridgeSupport;
use App\Support\Stock\StockAnitaBridgeSupport;
use App\Support\Stock\DepmaeAnitaCodigoSupport;
use App\Support\Stock\RecpunicaAnitaBridgeSupport;
use App\Support\Stock\StkParteUnicaAnitaBridgeSupport;
use Auth;
use Illuminate\Support\Facades\Log;

class RecepcionProveedorAnitaBridgeService
{
    public function __construct(
        private readonly OrdencompraAnitaSyncService $ordencompraAnitaSync,
    ) {
    }

    /**
     * @return array{cabecera_nueva: bool, pendmov_aplicado: bool}
     */
    public function sincronizarRecepcion(Recepcion_Proveedor $recepcion): array
    {
        if ((int) $recepcion->numerorecepcion <= 0) {
            throw new \RuntimeException('La recepción debe tener numerorecepcion asignado.');
        }

        $recepcion->loadMissing([
            'proveedores', 'empresas', 'ordencompras',
            'recepcion_proveedor_articulos.articulos.categorias',
            'recepcion_proveedor_articulos.articulos.impuestos',
            'recepcion_proveedor_articulos.centrocostos',
        ]);

        $clave = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
        RecepcionProveedorAnitaClaveSupport::asignarEnRecepcion($recepcion, $clave);

        RecepcionProveedorAnitaColisionSupport::assertConfirmacionSegura($recepcion);

        $codigoProveedor = RecepcionProveedorAnitaWhereSupport::codigoProveedorAnita($recepcion);
        $fechaAnita = (int) str_replace('-', '', $recepcion->fecha->format('Y-m-d'));
        $usuario = substr((string) (Auth::user()->usuario ?? Auth::user()->name ?? 'ERP'), 0, 8);
        $empresaCodigo = (int) ($recepcion->empresas->codigo ?? $recepcion->empresa_id);

        $estado = [
            'cabecera_nueva' => false,
            'pendmov_aplicado' => false,
        ];

        try {
            $ordenesAnita = RecepcionProveedorAnitaOrdenLineaSupport::prepararOrdenesAntesDeSincronizarAnita(
                $recepcion,
                $this->ordencompraAnitaSync
            );

            $cabeceraAnita = RecepcionProveedorAnitaColisionSupport::leerRecepmaeProveedorClave($codigoProveedor, $clave);

            if ($this->existeRecepmaeErp($codigoProveedor, $clave)) {
                $this->actualizarRecepmae($recepcion, $codigoProveedor, $clave, $fechaAnita, $usuario, $empresaCodigo, true);
            } elseif (
                $cabeceraAnita !== null
                && RecepcionProveedorAnitaWhereSupport::esTerminalProtegidoAnita(
                    trim((string) ($cabeceraAnita->recm_terminal ?? ''))
                )
                && RecepcionProveedorAnitaCorrespondenciaSupport::correspondeConRecepcionErp($recepcion, $cabeceraAnita)
            ) {
                // Cabecera REF / terminal vacío (actualización referencia Anita): adoptar vía UPDATE.
                $this->actualizarRecepmae($recepcion, $codigoProveedor, $clave, $fechaAnita, $usuario, $empresaCodigo, false);
            } else {
                // Sin recepmae (confirmación nueva o detalle huérfano): INSERT obligatorio.
                $this->grabarRecepmae($recepcion, $codigoProveedor, $clave, $fechaAnita, $usuario, $empresaCodigo);
                $estado['cabecera_nueva'] = true;
            }

            // Siempre limpiar detalle ERP antes de insertar (no tocar líneas Anita legacy).
            $this->eliminarRecepmov($clave, $codigoProveedor);
            $this->eliminarAplicped($codigoProveedor, $clave);
            $this->eliminarStkmov($clave, (int) $recepcion->empresa_id);
            $this->grabarRecepmov($recepcion, $codigoProveedor, $clave, $fechaAnita, $empresaCodigo, $ordenesAnita);
            $this->grabarAplicped($recepcion, $codigoProveedor, $clave, $ordenesAnita);
            StkmaePrecioCompraAnitaBridgeSupport::actualizarDesdeRecepcion($recepcion);
            $recepcion->forceFill(['stkmae_precio_anita_sync_at' => now()])->save();
            if (RecepcionProveedorStkmovAnitaSupport::habilitado()) {
                $this->grabarStkmov($recepcion, $codigoProveedor, $clave, $fechaAnita, $empresaCodigo, $ordenesAnita, $usuario);
            }
            $this->actualizarPendmovp($recepcion, 1);
            $estado['pendmov_aplicado'] = true;
        } catch (\Throwable $e) {
            $this->revertirSincronizacionConfirmacion($recepcion, $estado);
            throw $e;
        }

        return $estado;
    }

    /**
     * @param  array{cabecera_nueva: bool, pendmov_aplicado: bool}  $estado
     */
    public function revertirSincronizacionConfirmacion(Recepcion_Proveedor $recepcion, array $estado): void
    {
        if ((int) $recepcion->numerorecepcion <= 0) {
            return;
        }

        $recepcion->loadMissing([
            'proveedores', 'empresas', 'ordencompras',
            'recepcion_proveedor_articulos.articulos',
        ]);

        $clave = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
        $codigoProveedor = RecepcionProveedorAnitaWhereSupport::codigoProveedorAnita($recepcion);

        try {
            if (! empty($estado['pendmov_aplicado'])) {
                $this->actualizarPendmovp($recepcion, -1);
            }
        } catch (\Throwable $e) {
            Log::warning('RecepcionProveedorAnitaBridge: rollback pendmovp', [
                'recepcion_id' => $recepcion->id,
                'mensaje' => $e->getMessage(),
            ]);
        }

        try {
            $this->eliminarRecepmov($clave, $codigoProveedor);
        } catch (\Throwable $e) {
            Log::warning('RecepcionProveedorAnitaBridge: rollback recepmov', [
                'recepcion_id' => $recepcion->id,
                'mensaje' => $e->getMessage(),
            ]);
        }

        try {
            $this->eliminarAplicped($codigoProveedor, $clave);
        } catch (\Throwable $e) {
            Log::warning('RecepcionProveedorAnitaBridge: rollback aplicped', [
                'recepcion_id' => $recepcion->id,
                'mensaje' => $e->getMessage(),
            ]);
        }

        try {
            $this->eliminarStkmov($clave, (int) $recepcion->empresa_id);
        } catch (\Throwable $e) {
            Log::warning('RecepcionProveedorAnitaBridge: rollback stkmov', [
                'recepcion_id' => $recepcion->id,
                'mensaje' => $e->getMessage(),
            ]);
        }

        // Anular cabecera si fue insertada en este intento, o si quedó ERP vacía de esta recepción
        // (evita fantasma COM sin líneas tras rollback fallido de stock/asiento).
        $anularCabecera = ! empty($estado['cabecera_nueva']);
        if (! $anularCabecera) {
            try {
                $cabecera = RecepcionProveedorAnitaColisionSupport::leerRecepmaeProveedorClave($codigoProveedor, $clave);
                if (
                    $cabecera !== null
                    && trim((string) ($cabecera->recm_terminal ?? '')) === RecepcionProveedorAnitaWhereSupport::TERMINAL_ERP
                    && (int) ($cabecera->recm_documentoid ?? 0) === (int) $recepcion->id
                    && ! RecepcionProveedorAnitaColisionSupport::tieneRecepmovOStkmov($codigoProveedor, $clave)
                ) {
                    $anularCabecera = true;
                }
            } catch (\Throwable $e) {
                Log::warning('RecepcionProveedorAnitaBridge: rollback evaluar recepmae', [
                    'recepcion_id' => $recepcion->id,
                    'mensaje' => $e->getMessage(),
                ]);
            }
        }

        if ($anularCabecera) {
            try {
                $this->marcarRecepmaeAnulada($codigoProveedor, $clave);
            } catch (\Throwable $e) {
                Log::warning('RecepcionProveedorAnitaBridge: rollback recepmae', [
                    'recepcion_id' => $recepcion->id,
                    'mensaje' => $e->getMessage(),
                ]);
            }
        }
    }

    public function anularRecepcion(Recepcion_Proveedor $recepcion): void
    {
        if ((int) $recepcion->numerorecepcion <= 0) {
            return;
        }

        $recepcion->loadMissing([
            'proveedores', 'empresas', 'ordencompras',
            'recepcion_proveedor_articulos.articulos',
            'recepcion_proveedor_partes_unicas.recepcion_proveedor_articulos.articulos',
        ]);

        $clave = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
        $codigoProveedor = RecepcionProveedorAnitaWhereSupport::codigoProveedorAnita($recepcion);

        $this->eliminarStkParteUnica($recepcion);
        $this->eliminarRecpunica($recepcion, $clave);
        $this->eliminarStkmov($clave, (int) $recepcion->empresa_id);
        $this->eliminarAplicped($codigoProveedor, $clave);
        $this->eliminarRecepmov($clave, $codigoProveedor);
        $this->marcarRecepmaeAnulada($codigoProveedor, $clave);
        $this->actualizarPendmovp($recepcion, -1);
    }

    /**
     * @return array<int, object>
     */
    public function listarRecepmaeErpPorDocumento(int $documentoId): array
    {
        return $this->listarRecepmaePorDocumento($documentoId, soloErp: true);
    }

    /**
     * Auditoría COM: recepmae por clave Anita (tipo/letra/sucursal/nro), sin recm_documentoid.
     *
     * @return array<int, object>
     */
    public function listarRecepmaePorClaveAuditoria(Recepcion_Proveedor $recepcion): array
    {
        $recepcion->loadMissing(['proveedores', 'empresas']);

        $clave = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
        if ((int) ($clave['nro'] ?? 0) <= 0) {
            return [];
        }

        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => config('recepcion_proveedor.anita.sistema_compras'),
            'tabla' => config('recepcion_proveedor.anita.tablas.recepcion_cabecera'),
            'campos' => 'recm_proveedor, recm_tipo, recm_letra, recm_sucursal, recm_nro, recm_estado, recm_documentoid, recm_terminal',
            'whereArmado' => RecepcionProveedorAnitaWhereSupport::recepmaePorClave($clave),
            'limit' => 'FIRST 20',
        ]);

        return ApiAnita::decodificarListaFilas((string) $raw);
    }

    /**
     * @return array<int, object>
     */
    private function listarRecepmaePorDocumento(int $documentoId, bool $soloErp): array
    {
        if ($documentoId <= 0) {
            return [];
        }

        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => config('recepcion_proveedor.anita.sistema_compras'),
            'tabla' => config('recepcion_proveedor.anita.tablas.recepcion_cabecera'),
            'campos' => 'recm_proveedor, recm_tipo, recm_letra, recm_sucursal, recm_nro, recm_estado, recm_documentoid, recm_terminal',
            'whereArmado' => $soloErp
                ? RecepcionProveedorAnitaWhereSupport::recepmaeDocumentoErp($documentoId)
                : RecepcionProveedorAnitaWhereSupport::recepmaeDocumentoErpORef($documentoId),
            'limit' => 'FIRST 20',
        ]);

        return ApiAnita::decodificarListaFilas((string) $raw);
    }

    /**
     * Ajuste explícito de pendmovp (p. ej. -1 antes de re-sincronizar detalle ya aplicado).
     */
    public function ajustarPendmovpRecepcion(Recepcion_Proveedor $recepcion, int $multiplicador): void
    {
        if ($multiplicador === 0) {
            return;
        }

        $recepcion->loadMissing([
            'ordencompras',
            'recepcion_proveedor_articulos.articulos',
        ]);

        $this->actualizarPendmovp($recepcion, $multiplicador);
    }

    public function tieneDetalleComEnAnita(Recepcion_Proveedor $recepcion): bool
    {
        $recepcion->loadMissing(['proveedores', 'empresas']);

        $clave = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
        if ((int) ($clave['nro'] ?? 0) <= 0) {
            return false;
        }

        $codigoProveedor = RecepcionProveedorAnitaWhereSupport::codigoProveedorAnita($recepcion);

        return RecepcionProveedorAnitaColisionSupport::tieneRecepmovOStkmov($codigoProveedor, $clave);
    }

    /**
     * recepmae existente en Anita vinculada a esta recepción ERP (documentoid o correspondencia OC/proveedor/fecha).
     */
    public function cabeceraRecepmaeVinculadaDocumento(Recepcion_Proveedor $recepcion): ?object
    {
        $recepcion->loadMissing(['proveedores', 'empresas', 'ordencompras', 'asientos']);

        $clave = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
        if ((int) ($clave['nro'] ?? 0) <= 0) {
            return null;
        }

        $codigoProveedor = RecepcionProveedorAnitaWhereSupport::codigoProveedorAnita($recepcion);
        $cabecera = RecepcionProveedorAnitaColisionSupport::leerRecepmaeProveedorClave($codigoProveedor, $clave);
        if ($cabecera === null) {
            return null;
        }

        if ((int) ($cabecera->recm_documentoid ?? 0) === (int) $recepcion->id) {
            return $cabecera;
        }

        // documentoid huérfano / desfasado: misma COM por OC + proveedor + fecha.
        if (RecepcionProveedorAnitaColisionSupport::esMismaRecepcionEnAnita($recepcion, $cabecera)) {
            return $cabecera;
        }

        return null;
    }

    public function contarRecepmovPorClave(array $clave): int
    {
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => config('recepcion_proveedor.anita.sistema_compras'),
            'tabla' => config('recepcion_proveedor.anita.tablas.recepcion_linea'),
            'campos' => 'recv_nro',
            'whereArmado' => RecepcionProveedorAnitaWhereSupport::recepmovCabecera($clave),
        ]);

        return count(ApiAnita::decodificarListaFilas((string) $raw));
    }

    public function contarStkmovPorClave(array $clave, int $empresaId): int
    {
        $api = new ApiAnita;
        $raw = $api->apiCall(
            StockAnitaBridgeSupport::mergePayload([
                'acc' => 'list',
                'sistema' => config('recepcion_proveedor.anita.sistema_ventas'),
                'tabla' => config('recepcion_proveedor.anita.tablas.stock_movimiento'),
                'campos' => 'stkv_nro',
                'whereArmado' => RecepcionProveedorAnitaWhereSupport::stkmovCabecera($clave),
            ], $empresaId)
        );

        return count(ApiAnita::decodificarListaFilas((string) $raw));
    }

    /**
     * Detalle Anita (recepmov/stkmov) con menos líneas que la recepción ERP confirmada.
     */
    public function detalleComIncompletoEnAnita(Recepcion_Proveedor $recepcion): bool
    {
        return (bool) ($this->diagnosticoDetalleComAnita($recepcion)['incompleto'] ?? false);
    }

    /**
     * @return array{
     *   lineas_erp: int,
     *   recepmov: int,
     *   stkmov: int|null,
     *   incompleto: bool,
     *   mensaje: string|null
     * }
     */
    public function diagnosticoDetalleComAnita(Recepcion_Proveedor $recepcion): array
    {
        $recepcion->loadMissing(['empresas', 'recepcion_proveedor_articulos']);

        $lineasErp = $recepcion->recepcion_proveedor_articulos->count();
        $clave = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
        $recepmov = (int) ($clave['nro'] ?? 0) > 0 ? $this->contarRecepmovPorClave($clave) : 0;
        $stkmov = null;
        $incompleto = false;

        if ($lineasErp > 0) {
            if ((int) ($clave['nro'] ?? 0) <= 0) {
                $incompleto = true;
            } elseif ($recepmov < $lineasErp) {
                $incompleto = true;
            } elseif (RecepcionProveedorStkmovAnitaSupport::habilitado()) {
                $stkmov = $this->contarStkmovPorClave($clave, (int) $recepcion->empresa_id);
                $incompleto = $stkmov < $lineasErp;
            }
        }

        if ($stkmov === null && RecepcionProveedorStkmovAnitaSupport::habilitado() && (int) ($clave['nro'] ?? 0) > 0) {
            $stkmov = $this->contarStkmovPorClave($clave, (int) $recepcion->empresa_id);
        }

        $mensaje = null;
        if ($incompleto) {
            $mensaje = sprintf(
                'Detalle Anita incompleto: ERP %d ítem(s), recepmov %d%s.',
                $lineasErp,
                $recepmov,
                $stkmov !== null ? ', stkmov '.$stkmov : '',
            );
        }

        return [
            'lineas_erp' => $lineasErp,
            'recepmov' => $recepmov,
            'stkmov' => $stkmov,
            'incompleto' => $incompleto,
            'mensaje' => $mensaje,
        ];
    }

    /**
     * Inserta aplicped faltante para una recepción CONFIRMADA vinculada a OC, sin tocar recepmov/stkmov.
     *
     * @return array{recepcion_id: int, com: int, acciones: list<string>, problemas: list<string>}
     */
    public function repararAplicpedSiFalta(Recepcion_Proveedor $recepcion): array
    {
        $recepcion->loadMissing([
            'proveedores',
            'empresas',
            'ordencompras.ordencompra_articulos',
            'recepcion_proveedor_articulos.articulos',
            'recepcion_proveedor_articulos.ordencompra_articulos',
        ]);

        $result = [
            'recepcion_id' => (int) $recepcion->id,
            'com' => (int) $recepcion->numerorecepcion,
            'acciones' => [],
            'problemas' => [],
        ];

        if ((int) $recepcion->numerorecepcion <= 0) {
            $result['problemas'][] = 'Recepción sin numerorecepcion.';

            return $result;
        }

        if ((int) ($recepcion->ordencompra_id ?? 0) <= 0 || $recepcion->ordencompras === null) {
            $result['problemas'][] = 'Recepción sin OC vinculada.';

            return $result;
        }

        if ($this->cabeceraRecepmaeVinculadaDocumento($recepcion) === null) {
            $result['problemas'][] = 'No hay recepmae Anita vinculada a la recepción.';

            return $result;
        }

        $clave = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
        RecepcionProveedorAnitaClaveSupport::asignarEnRecepcion($recepcion, $clave);
        $codigoProveedor = RecepcionProveedorAnitaWhereSupport::codigoProveedorAnita($recepcion);

        if ($this->existeAplicped($codigoProveedor, $clave)) {
            $result['acciones'][] = 'aplicped ya existía';

            return $result;
        }

        $ordenesAnita = RecepcionProveedorAnitaOrdenLineaSupport::prepararOrdenesAntesDeSincronizarAnita(
            $recepcion,
            $this->ordencompraAnitaSync
        );
        $this->grabarAplicped($recepcion, $codigoProveedor, $clave, $ordenesAnita);
        $result['acciones'][] = 'insertó aplicped';

        if (! $this->existeAplicped($codigoProveedor, $clave)) {
            $result['problemas'][] = 'No se pudo verificar aplicped tras el insert.';
        }

        return $result;
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $claveCom
     */
    public function existeAplicped(string $codigoProveedor, array $claveCom): bool
    {
        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'list',
            'sistema' => config('recepcion_proveedor.anita.sistema_compras'),
            'tabla' => config('recepcion_proveedor.anita.tablas.aplicacion_oc'),
            'campos' => 'aplp_nro,aplp_ref_nro,aplp_orden_com',
            'whereArmado' => RecepcionProveedorAnitaWhereSupport::aplicpedCom($codigoProveedor, $claveCom),
            'limit' => 'FIRST 1',
        ], 'recepcion aplicped existe');

        return ApiAnita::primeraFilaLista((string) $raw) !== null;
    }

    /**
     * Re-graba recepmov/aplicped/stkmov sin tocar recepmae (cabecera REF u otra ya vinculada).
     */
    public function repararDetallePreservandoCabecera(Recepcion_Proveedor $recepcion): void
    {
        if ((int) $recepcion->numerorecepcion <= 0) {
            throw new \RuntimeException('La recepción debe tener numerorecepcion asignado.');
        }

        $cabecera = $this->cabeceraRecepmaeVinculadaDocumento($recepcion);
        if ($cabecera === null) {
            throw new \RuntimeException(
                'Reparación detalle: no hay recepmae Anita vinculada al documento ERP '.$recepcion->id.'.'
            );
        }

        $recepcion->loadMissing([
            'proveedores', 'empresas', 'ordencompras',
            'recepcion_proveedor_articulos.articulos.categorias',
            'recepcion_proveedor_articulos.articulos.impuestos',
            'recepcion_proveedor_articulos.centrocostos',
        ]);

        $clave = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
        RecepcionProveedorAnitaClaveSupport::asignarEnRecepcion($recepcion, $clave);

        $codigoProveedor = RecepcionProveedorAnitaWhereSupport::codigoProveedorAnita($recepcion);
        $fechaAnita = (int) str_replace('-', '', $recepcion->fecha->format('Y-m-d'));
        $usuario = substr((string) (Auth::user()->usuario ?? Auth::user()->name ?? 'ERP'), 0, 8);
        $empresaCodigo = (int) ($recepcion->empresas->codigo ?? $recepcion->empresa_id);
        $empresaId = (int) $recepcion->empresa_id;

        $pendmovpReaplicado = false;

        try {
            $ordenesAnita = RecepcionProveedorAnitaOrdenLineaSupport::prepararOrdenesAntesDeSincronizarAnita(
                $recepcion,
                $this->ordencompraAnitaSync
            );

            try {
                $this->actualizarPendmovp($recepcion, -1);
            } catch (\Throwable $e) {
                Log::warning('RecepcionProveedorAnitaBridge: reparar detalle pendmovp -1 omitido', [
                    'recepcion_id' => $recepcion->id,
                    'mensaje' => $e->getMessage(),
                ]);
            }

            $this->eliminarDetalleComPorClaveCompleto($clave, $codigoProveedor, $empresaId);

            $this->grabarRecepmov($recepcion, $codigoProveedor, $clave, $fechaAnita, $empresaCodigo, $ordenesAnita);
            $this->grabarAplicped($recepcion, $codigoProveedor, $clave, $ordenesAnita);
            StkmaePrecioCompraAnitaBridgeSupport::actualizarDesdeRecepcion($recepcion);
            $recepcion->forceFill(['stkmae_precio_anita_sync_at' => now()])->save();
            if (RecepcionProveedorStkmovAnitaSupport::habilitado()) {
                $this->grabarStkmov($recepcion, $codigoProveedor, $clave, $fechaAnita, $empresaCodigo, $ordenesAnita, $usuario);
            }
            $this->actualizarPendmovp($recepcion, 1);
            $pendmovpReaplicado = true;
        } catch (\Throwable $e) {
            if ($pendmovpReaplicado) {
                try {
                    $this->actualizarPendmovp($recepcion, -1);
                } catch (\Throwable $rollbackPendmovp) {
                    Log::warning('RecepcionProveedorAnitaBridge: rollback pendmovp reparación detalle', [
                        'recepcion_id' => $recepcion->id,
                        'mensaje' => $rollbackPendmovp->getMessage(),
                    ]);
                }
            }

            throw $e;
        }
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private function eliminarDetalleComPorClaveCompleto(array $clave, string $codigoProveedor, int $empresaId): void
    {
        $this->eliminarRecepmov($clave, null);
        $this->eliminarAplicped($codigoProveedor, $clave);
        $this->eliminarStkmovPorClaveCompleto($clave, $empresaId);
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $claveCom */
    private function eliminarStkmovPorClaveCompleto(array $claveCom, int $empresaId): void
    {
        $api = new ApiAnita;
        $api->apiCallEscritura(
            StockAnitaBridgeSupport::mergePayload([
                'acc' => 'delete',
                'sistema' => config('recepcion_proveedor.anita.sistema_ventas'),
                'tabla' => config('recepcion_proveedor.anita.tablas.stock_movimiento'),
                'whereArmado' => RecepcionProveedorAnitaWhereSupport::stkmovCabecera($claveCom),
            ], $empresaId),
            'recepcion stkmov delete completo'
        );
    }

    /**
     * Elimina en Anita una clave COM concreta (p. ej. sucursal virtual 991) sin anular la recepción en ERP.
     *
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    public function revertirClaveComEnAnita(
        Recepcion_Proveedor $recepcion,
        array $clave,
        bool $revertirPendmovp = true
    ): void {
        if ((int) ($clave['nro'] ?? 0) <= 0 || (int) ($clave['sucursal'] ?? 0) <= 0) {
            return;
        }

        $recepcion->loadMissing([
            'proveedores', 'empresas', 'ordencompras',
            'recepcion_proveedor_articulos.articulos',
            'recepcion_proveedor_partes_unicas.recepcion_proveedor_articulos.articulos',
        ]);

        $codigoProveedor = RecepcionProveedorAnitaWhereSupport::codigoProveedorAnita($recepcion);

        if (
            $revertirPendmovp
            && $recepcion->estado === RecepcionProveedorEstados::CONFIRMADA
        ) {
            $this->actualizarPendmovp($recepcion, -1);
        }

        if (! $this->existeRecepmaeErp($codigoProveedor, $clave)) {
            return;
        }

        $this->eliminarStkParteUnica($recepcion);
        $this->eliminarRecpunica($recepcion, $clave);
        $this->eliminarStkmov($clave, (int) $recepcion->empresa_id);
        $this->eliminarAplicped($codigoProveedor, $clave);
        $this->eliminarRecepmov($clave, $codigoProveedor);
        $this->marcarRecepmaeAnulada($codigoProveedor, $clave);
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function existeRecepmaeErp(string $codigoProveedor, array $clave): bool
    {
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => config('recepcion_proveedor.anita.sistema_compras'),
            'tabla' => config('recepcion_proveedor.anita.tablas.recepcion_cabecera'),
            'campos' => 'recm_nro',
            'whereArmado' => RecepcionProveedorAnitaWhereSupport::recepmaeSoloErp($codigoProveedor, $clave),
            'limit' => 'FIRST 1',
        ]);

        return ApiAnita::primeraFilaLista($raw) !== null;
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function marcarRecepmaeAnulada(string $codigoProveedor, array $clave): void
    {
        if (! $this->existeRecepmaeErp($codigoProveedor, $clave)) {
            return;
        }

        $estadoAnulada = (string) config('recepcion_proveedor.anita.recepcion_estado_anulada', '3');
        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'update',
            'sistema' => config('recepcion_proveedor.anita.sistema_compras'),
            'tabla' => config('recepcion_proveedor.anita.tablas.recepcion_cabecera'),
            'valores' => RecepcionProveedorAnitaEscrituraSupport::recepmaeAnularSet($estadoAnulada),
            'whereArmado' => RecepcionProveedorAnitaWhereSupport::recepmaeSoloErp($codigoProveedor, $clave),
        ], 'recepcion recepmae anular ERP');
    }

    /**
     * Actualiza únicamente recv_cotizacion de las líneas recepmov de la COM (sin tocar cantidades,
     * precios ni stkmov/pendmovp). Se usa al cambiar la cotización de una recepción confirmada.
     * Exige filas afectadas en el bridge y relee recepmov para confirmar el valor.
     */
    public function actualizarCotizacionRecepmov(Recepcion_Proveedor $recepcion, float $nuevaCotizacion): void
    {
        if ((int) $recepcion->numerorecepcion <= 0) {
            return;
        }

        $recepcion->loadMissing('proveedores');

        $clave = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
        if ((int) ($clave['nro'] ?? 0) <= 0) {
            return;
        }

        $codigoProveedor = RecepcionProveedorAnitaWhereSupport::codigoProveedorAnita($recepcion);
        $sistema = (string) config('recepcion_proveedor.anita.sistema_compras');
        $tabla = (string) config('recepcion_proveedor.anita.tablas.recepcion_linea');
        $where = RecepcionProveedorAnitaWhereSupport::recepmovProveedorCabecera($codigoProveedor, $clave);

        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'update',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'valores' => RecepcionProveedorAnitaEscrituraSupport::updateSet([
                'recv_cotizacion' => RecepcionProveedorAnitaEscrituraSupport::decimalSql($nuevaCotizacion),
            ]),
            'whereArmado' => $where,
        ], 'recepcion recepmov update cotizacion', 'anita_bridge.fallo', true);

        $this->assertCotizacionRecepmov($api, $sistema, $tabla, $where, $nuevaCotizacion, $clave);
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private function assertCotizacionRecepmov(
        ApiAnita $api,
        string $sistema,
        string $tabla,
        string $where,
        float $nuevaCotizacion,
        array $clave,
    ): void {
        $raw = (string) $api->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'campos' => 'recv_cotizacion,recv_articulo,recv_orden',
            'whereArmado' => $where,
        ]);
        $parsed = ApiAnita::parsearRespuestaLista($raw);
        if ($parsed['error_lectura'] !== null) {
            throw new \RuntimeException(
                'No se pudo verificar recv_cotizacion en Anita tras el UPDATE: '.$parsed['error_lectura']
            );
        }

        $filas = $parsed['filas'];
        if ($filas === []) {
            throw new \RuntimeException(sprintf(
                'UPDATE recepmov cotización: no hay líneas COM %s/%s/%d/%d en Anita para verificar.',
                $clave['tipo'],
                $clave['letra'],
                (int) $clave['sucursal'],
                (int) $clave['nro']
            ));
        }

        foreach ($filas as $fila) {
            $actual = (float) ($fila->recv_cotizacion ?? 0);
            if (abs($actual - $nuevaCotizacion) > 0.0005) {
                throw new \RuntimeException(sprintf(
                    'recv_cotizacion en Anita quedó en %s (esperado %s) para artículo %s orden %s.',
                    rtrim(rtrim(number_format($actual, 6, '.', ''), '0'), '.') ?: '0',
                    rtrim(rtrim(number_format($nuevaCotizacion, 6, '.', ''), '0'), '.') ?: '0',
                    (string) ($fila->recv_articulo ?? '?'),
                    (string) ($fila->recv_orden ?? '?')
                ));
            }
        }
    }

    /**
     * Repara solo recepmae + recepmov (adopta cabecera desktop/huérfana y regraba líneas).
     * No toca stkmov, aplicped ni pendmovp. Pensado para COM importadas (ANITA_IMPORT)
     * donde el sync borró recepmov y no adoptó la cabecera.
     *
     * @return array{
     *     lineas_erp: int,
     *     lineas_recepmov: int,
     *     recepmae_estado: string,
     *     recepmae_documentoid: int,
     *     ctamov_debe: float,
     *     ctamov_haber: float,
     *     ctamov_lineas: int,
     *     subdiario_lineas: int
     * }
     */
    public function repararRecepmaeYRecepmovSinStkmov(Recepcion_Proveedor $recepcion): array
    {
        if ((int) $recepcion->numerorecepcion <= 0) {
            throw new \RuntimeException('La recepción debe tener numerorecepcion asignado.');
        }

        if ($recepcion->estado !== RecepcionProveedorEstados::CONFIRMADA) {
            throw new \RuntimeException('Solo se repara recepmae/recepmov de recepciones CONFIRMADA.');
        }

        $cotizacionCabecera = (float) ($recepcion->cotizacion ?: 1);
        // Homogeneiza líneas ERP con cabecera (import dejaba 1500 en líneas y 1 en cabecera).
        Recepcion_Proveedor_Articulo::query()
            ->where('recepcion_proveedor_id', $recepcion->id)
            ->where(function ($q) use ($cotizacionCabecera) {
                $q->whereNull('cotizacion')
                    ->orWhere('cotizacion', '!=', $cotizacionCabecera);
            })
            ->update(['cotizacion' => $cotizacionCabecera]);

        $recepcion->loadMissing([
            'proveedores', 'empresas', 'ordencompras',
            'recepcion_proveedor_articulos.articulos.categorias',
            'recepcion_proveedor_articulos.articulos.impuestos',
            'recepcion_proveedor_articulos.centrocostos',
        ]);

        $clave = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
        RecepcionProveedorAnitaClaveSupport::asignarEnRecepcion($recepcion, $clave);

        $codigoProveedor = RecepcionProveedorAnitaWhereSupport::codigoProveedorAnita($recepcion);
        $fechaAnita = (int) str_replace('-', '', $recepcion->fecha->format('Y-m-d'));
        $usuario = substr((string) (Auth::user()->usuario ?? Auth::user()->name ?? 'ERP'), 0, 8);
        $empresaCodigo = (int) ($recepcion->empresas->codigo ?? $recepcion->empresa_id);
        $cfg = config('recepcion_proveedor.anita');
        $estadoConfirmada = (string) ($cfg['recepcion_estado_confirmada'] ?? '2');
        $ocFac = $this->claveOcFacDesdeRecepcion($recepcion);
        $refFac = RecepcionProveedorAnitaReferenciaSupport::referenciaFacturaRemitoDesdeRecepcion($recepcion);

        $empresaId = (int) $recepcion->empresa_id;
        $stkmovAntes = $this->contarStkmovAnita($clave, $empresaId);

        $api = new ApiAnita;
        $valoresRecepmae = RecepcionProveedorAnitaEscrituraSupport::recepmaeUpdateSet(
            $fechaAnita,
            $estadoConfirmada,
            $usuario,
            substr((string) ($recepcion->observacion ?? ''), 0, 40),
            $empresaCodigo,
            $ocFac,
            $refFac,
            (int) $recepcion->id,
        );
        $valoresRecepmae .= ', recm_terminal = '.RecepcionProveedorAnitaEscrituraSupport::textoSql(
            RecepcionProveedorAnitaWhereSupport::TERMINAL_ERP,
            8
        );

        $api->apiCallEscritura([
            'acc' => 'update',
            'sistema' => $cfg['sistema_compras'],
            'tabla' => $cfg['tablas']['recepcion_cabecera'],
            'valores' => $valoresRecepmae,
            'whereArmado' => RecepcionProveedorAnitaWhereSupport::recepmae($codigoProveedor, $clave),
        ], 'recepcion recepmae adoptar sin stkmov', 'anita_bridge.fallo', true);

        $this->eliminarRecepmov($clave, $codigoProveedor);
        $ordenesAnita = RecepcionProveedorAnitaOrdenLineaSupport::ordenRecepmovPorLineaId($recepcion);
        $this->grabarRecepmov($recepcion, $codigoProveedor, $clave, $fechaAnita, $empresaCodigo, $ordenesAnita);

        $stkmovDespues = $this->contarStkmovAnita($clave, $empresaId);
        if ($stkmovAntes !== $stkmovDespues) {
            throw new \RuntimeException(sprintf(
                'Reparación abortada: stkmov cambió de %d a %d líneas (no debía tocarse).',
                $stkmovAntes,
                $stkmovDespues
            ));
        }

        $lineasErp = $recepcion->recepcion_proveedor_articulos->count();
        $rawMov = (string) $api->apiCall([
            'acc' => 'list',
            'sistema' => $cfg['sistema_compras'],
            'tabla' => $cfg['tablas']['recepcion_linea'],
            'campos' => 'recv_articulo,recv_cotizacion,recv_cantidad,recv_precio,recv_orden',
            'whereArmado' => RecepcionProveedorAnitaWhereSupport::recepmovProveedorCabecera($codigoProveedor, $clave),
        ]);
        $filasMov = ApiAnita::decodificarListaFilas($rawMov);
        if (count($filasMov) < $lineasErp) {
            throw new \RuntimeException(sprintf(
                'Tras reparar, recepmov tiene %d líneas y ERP %d (COM %d).',
                count($filasMov),
                $lineasErp,
                (int) $recepcion->numerorecepcion
            ));
        }

        foreach ($filasMov as $fila) {
            $cot = (float) ($fila->recv_cotizacion ?? 0);
            if (abs($cot - $cotizacionCabecera) > 0.0005) {
                throw new \RuntimeException(sprintf(
                    'recv_cotizacion=%s distinta de cabecera ERP=%s (artículo %s).',
                    (string) ($fila->recv_cotizacion ?? ''),
                    (string) $cotizacionCabecera,
                    (string) ($fila->recv_articulo ?? '?')
                ));
            }
        }

        $rawMae = (string) $api->apiCall([
            'acc' => 'list',
            'sistema' => $cfg['sistema_compras'],
            'tabla' => $cfg['tablas']['recepcion_cabecera'],
            'campos' => 'recm_estado,recm_documentoid,recm_terminal',
            'whereArmado' => RecepcionProveedorAnitaWhereSupport::recepmae($codigoProveedor, $clave),
            'limit' => 'FIRST 1',
        ]);
        $mae = ApiAnita::primeraFilaLista($rawMae);
        if ($mae === null) {
            throw new \RuntimeException('No se pudo releer recepmae tras la reparación.');
        }

        $docId = (int) ($mae->recm_documentoid ?? 0);
        $estadoMae = trim((string) ($mae->recm_estado ?? ''));
        $terminalMae = trim((string) ($mae->recm_terminal ?? ''));
        if ($docId !== (int) $recepcion->id || $estadoMae !== $estadoConfirmada) {
            throw new \RuntimeException(sprintf(
                'recepmae no adoptada: documentoid=%d estado=%s (esperado doc=%d estado=%s).',
                $docId,
                $estadoMae,
                (int) $recepcion->id,
                $estadoConfirmada
            ));
        }

        $contable = $this->assertContabilidadAnitaSinDobleSubdiario($recepcion, $clave);

        Log::info('RecepcionProveedorAnitaBridge: reparar recepmae+recepmov sin stkmov', [
            'recepcion_id' => $recepcion->id,
            'com' => (int) $recepcion->numerorecepcion,
            'lineas_erp' => $lineasErp,
            'lineas_recepmov' => count($filasMov),
            'stkmov_intactas' => $stkmovDespues,
            'recm_terminal' => $terminalMae,
            'ctamov' => $contable,
        ]);

        return [
            'lineas_erp' => $lineasErp,
            'lineas_recepmov' => count($filasMov),
            'recepmae_estado' => $estadoMae,
            'recepmae_documentoid' => $docId,
            'ctamov_debe' => $contable['ctamov_debe'],
            'ctamov_haber' => $contable['ctamov_haber'],
            'ctamov_lineas' => $contable['ctamov_lineas'],
            'subdiario_lineas' => $contable['subdiario_lineas'],
        ];
    }

    /**
     * COM nativa Anita suele ir a subdiario; el sync ERP escribe ctamov.
     * Ambos a la vez = doble en el mayor. Exige ctamov alineado al asiento ERP y subdiario vacío.
     *
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @return array{ctamov_debe: float, ctamov_haber: float, ctamov_lineas: int, subdiario_lineas: int, erp_debe: float}
     */
    public function assertContabilidadAnitaSinDobleSubdiario(Recepcion_Proveedor $recepcion, ?array $clave = null): array
    {
        $clave ??= RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
        $tipo = trim((string) ($clave['tipo'] ?? ''));
        $letra = trim((string) ($clave['letra'] ?? ''));
        $sucursal = (int) ($clave['sucursal'] ?? 0);
        $nro = (int) ($clave['nro'] ?? 0);

        if ($tipo === '' || $nro <= 0) {
            throw new \RuntimeException('Clave Anita inválida para controlar ctamov/subdiario.');
        }

        $erpDebe = $this->totalDebeAsientoErp($recepcion);
        $totalesCtamov = RecepcionProveedorAsientoAnitaCtamovSupport::totalesCtamovRecepcion($recepcion);
        if ($totalesCtamov === null) {
            throw new \RuntimeException(sprintf(
                'COM %d sin ctamov en Anita (esperado asiento ERP debe=%.2f).',
                $nro,
                $erpDebe
            ));
        }

        if (abs($totalesCtamov['debe'] - $erpDebe) > 0.05 || abs($totalesCtamov['haber'] - $erpDebe) > 0.05) {
            throw new \RuntimeException(sprintf(
                'ctamov COM %d descuadrado vs asiento ERP: ctamov D=%.2f H=%.2f / ERP=%.2f.',
                $nro,
                $totalesCtamov['debe'],
                $totalesCtamov['haber'],
                $erpDebe
            ));
        }

        $api = new ApiAnita;
        $rawSub = (string) $api->apiCall([
            'acc' => 'list',
            'sistema' => (string) config('recepcion_proveedor.anita.sistema_contab', 'contab'),
            'tabla' => (string) config('recepcion_proveedor.anita.tablas.subdiario', 'subdiario'),
            'campos' => 'subd_cuenta,subd_contrapartida,subd_importe,subd_tipo_mov,subd_fecha',
            'whereArmado' => " WHERE subd_tipo='".addslashes($tipo)."'"
                ." AND subd_letra='".addslashes($letra)."'"
                .' AND subd_sucursal='.$sucursal
                .' AND subd_nro='.$nro,
        ]);
        $filasSub = ApiAnita::decodificarListaFilas($rawSub);
        if ($filasSub !== []) {
            throw new \RuntimeException(sprintf(
                'COM %d tiene %d línea(s) en subdiario y también ctamov (doble imputación nativa Anita + sync ERP).',
                $nro,
                count($filasSub)
            ));
        }

        return [
            'ctamov_debe' => $totalesCtamov['debe'],
            'ctamov_haber' => $totalesCtamov['haber'],
            'ctamov_lineas' => $totalesCtamov['lineas'],
            'subdiario_lineas' => 0,
            'erp_debe' => $erpDebe,
        ];
    }

    private function totalDebeAsientoErp(Recepcion_Proveedor $recepcion): float
    {
        $asientoId = (int) ($recepcion->asiento_id ?? 0);
        if ($asientoId <= 0) {
            throw new \RuntimeException('La recepción no tiene asiento ERP para controlar ctamov.');
        }

        $debe = (float) Asiento_Movimiento::query()
            ->where('asiento_id', $asientoId)
            ->whereNull('deleted_at')
            ->where('monto', '>', 0)
            ->sum('monto');

        return round($debe, 2);
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private function contarStkmovAnita(array $clave, int $empresaId): int
    {
        $api = new ApiAnita;
        $raw = (string) $api->apiCall(StockAnitaBridgeSupport::mergePayload([
            'acc' => 'list',
            'sistema' => config('recepcion_proveedor.anita.sistema_ventas'),
            'tabla' => config('recepcion_proveedor.anita.tablas.stock_movimiento'),
            'campos' => 'stkv_articulo',
            'whereArmado' => RecepcionProveedorAnitaWhereSupport::stkmovCabecera($clave),
        ], max(1, $empresaId)));

        return count(ApiAnita::decodificarListaFilas($raw));
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private function eliminarRecepmov(array $clave, ?string $codigoProveedor = null): void
    {
        $where = $codigoProveedor !== null && $codigoProveedor !== ''
            ? RecepcionProveedorAnitaWhereSupport::recepmovProveedorCabecera($codigoProveedor, $clave)
            : RecepcionProveedorAnitaWhereSupport::recepmovCabecera($clave);

        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'delete',
            'sistema' => config('recepcion_proveedor.anita.sistema_compras'),
            'tabla' => config('recepcion_proveedor.anita.tablas.recepcion_linea'),
            'whereArmado' => $where,
        ], 'recepcion recepmov delete');
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $claveCom */
    private function eliminarAplicped(string $codigoProveedor, array $claveCom): void
    {
        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'delete',
            'sistema' => config('recepcion_proveedor.anita.sistema_compras'),
            'tabla' => config('recepcion_proveedor.anita.tablas.aplicacion_oc'),
            'whereArmado' => RecepcionProveedorAnitaWhereSupport::aplicpedCom($codigoProveedor, $claveCom),
        ], 'recepcion aplicped delete');
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $claveCom */
    private function eliminarStkmov(array $claveCom, int $empresaId): void
    {
        $api = new ApiAnita;
        $api->apiCallEscritura(
            StockAnitaBridgeSupport::mergePayload([
                'acc' => 'delete',
                'sistema' => config('recepcion_proveedor.anita.sistema_ventas'),
                'tabla' => config('recepcion_proveedor.anita.tablas.stock_movimiento'),
                'whereArmado' => RecepcionProveedorAnitaWhereSupport::stkmovCabeceraSoloErp($claveCom),
            ], $empresaId),
            'recepcion stkmov delete'
        );
    }

    /**
     * @return array{tipo: string, letra: string, sucursal: int, nro: int}
     */
    private function claveOcFacDesdeRecepcion(Recepcion_Proveedor $recepcion): array
    {
        $cfg = config('recepcion_proveedor.anita');
        $oc = $recepcion->ordencompras;

        return [
            'tipo' => (string) $cfg['oc_tipo'],
            'letra' => (string) $cfg['oc_letra'],
            'sucursal' => (int) $cfg['oc_sucursal'],
            'nro' => (int) ($oc->numeroordencompra ?? 0),
        ];
    }

    private function eliminarStkParteUnica(Recepcion_Proveedor $recepcion): void
    {
        foreach ($recepcion->recepcion_proveedor_partes_unicas as $parte) {
            $parte->loadMissing('recepcion_proveedor_articulos.articulos');
            $apu = \App\Models\Stock\Articulo_ParteUnica::query()
                ->where('numeroparte', $parte->numeroparte)
                ->first();

            if ($apu && ! StkParteUnicaAnitaBridgeSupport::eliminar($apu)) {
                throw new \RuntimeException(
                    'Error al eliminar stk_parte_unica en Anita (NPU '.$parte->numeroparte.').'
                );
            }
        }
    }

    private function eliminarRecpunica(Recepcion_Proveedor $recepcion, array $clave): void
    {
        foreach ($recepcion->recepcion_proveedor_partes_unicas as $parte) {
            RecpunicaAnitaBridgeSupport::eliminarDesdeParte($parte, $clave);
        }

        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'delete',
            'sistema' => config('recepcion_proveedor.anita.sistema_compras'),
            'tabla' => config('recepcion_proveedor.anita.tablas.recepcion_parte_unica'),
            'whereArmado' => RecepcionProveedorAnitaWhereSupport::recpunicaCabecera($clave),
        ], 'recepcion recpunica delete');
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function actualizarRecepmae(
        Recepcion_Proveedor $recepcion,
        string $codigoProveedor,
        array $clave,
        int $fechaAnita,
        string $usuario,
        int $empresaCodigo,
        bool $soloTerminalErp = true,
    ): void {
        $obs = substr((string) ($recepcion->observacion ?? ''), 0, 40);
        $cfg = config('recepcion_proveedor.anita');
        $estadoConfirmada = (string) ($cfg['recepcion_estado_confirmada'] ?? '2');
        $ocFac = $this->claveOcFacDesdeRecepcion($recepcion);
        $refFac = RecepcionProveedorAnitaReferenciaSupport::referenciaFacturaRemitoDesdeRecepcion($recepcion);

        $where = $soloTerminalErp
            ? RecepcionProveedorAnitaWhereSupport::recepmaeSoloErp($codigoProveedor, $clave)
            : RecepcionProveedorAnitaWhereSupport::recepmae($codigoProveedor, $clave);

        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'update',
            'sistema' => $cfg['sistema_compras'],
            'tabla' => $cfg['tablas']['recepcion_cabecera'],
            'valores' => RecepcionProveedorAnitaEscrituraSupport::recepmaeUpdateSet(
                $fechaAnita,
                $estadoConfirmada,
                $usuario,
                $obs,
                $empresaCodigo,
                $ocFac,
                $refFac,
                (int) $recepcion->id,
            ),
            'whereArmado' => $where,
        ], 'recepcion recepmae update');
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function grabarRecepmae(
        Recepcion_Proveedor $recepcion,
        string $codigoProveedor,
        array $clave,
        int $fechaAnita,
        string $usuario,
        int $empresaCodigo
    ): void {
        $api = new ApiAnita;
        $obs = substr((string) ($recepcion->observacion ?? ''), 0, 40);
        $cfg = config('recepcion_proveedor.anita');
        $estadoConfirmada = (string) ($cfg['recepcion_estado_confirmada'] ?? '2');
        $ocFac = $this->claveOcFacDesdeRecepcion($recepcion);
        $refFac = RecepcionProveedorAnitaReferenciaSupport::referenciaFacturaRemitoDesdeRecepcion($recepcion);

        $insert = RecepcionProveedorAnitaEscrituraSupport::recepmaeInsert(
            $codigoProveedor,
            $clave,
            $fechaAnita,
            $estadoConfirmada,
            $usuario,
            $obs,
            $empresaCodigo,
            $ocFac,
            $refFac,
            (int) $recepcion->id,
        );

        $api->apiCallEscritura([
            'acc' => 'insert',
            'sistema' => $cfg['sistema_compras'],
            'tabla' => $cfg['tablas']['recepcion_cabecera'],
            'campos' => $insert['campos'],
            'valores' => $insert['valores'],
        ], 'recepcion recepmae insert');
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave
     *  @param array<int, int> $ordenesAnita */
    private function grabarRecepmov(
        Recepcion_Proveedor $recepcion,
        string $codigoProveedor,
        array $clave,
        int $fechaAnita,
        int $empresaCodigo,
        array $ordenesAnita
    ): void {
        $api = new ApiAnita;
        $signo = $recepcion->tipo === Recepcion_Proveedor::TIPO_DEVOLUCION ? -1 : 1;
        $cfg = config('recepcion_proveedor.anita');
        $ordenMax = 0;

        foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
            $linea->loadMissing(['articulos.categorias', 'articulos.impuestos']);
            $articulo = $linea->articulos;
            $sku = RecepcionProveedorAnitaEscrituraSupport::skuAnita13((string) ($articulo->sku ?? ''));
            $codigoAgrupacion = (string) optional($articulo?->categorias)->codigo;
            $tipoIvaAnita = RecepcionProveedorAnitaEscrituraSupport::tipoIvaAnitaCodigo($articulo);
            $orden = RecepcionProveedorAnitaOrdenLineaSupport::ordenAnitaLinea($linea, $ordenesAnita);
            if ($orden <= 0) {
                throw new \RuntimeException(
                    'Línea sin recv_orden válido (artículo '.trim($sku).', recepción '.$recepcion->numerorecepcion.').'
                );
            }
            $ordenMax = max($ordenMax, $orden);

            $insert = RecepcionProveedorAnitaEscrituraSupport::recepmovInsert(
                $codigoProveedor,
                $clave,
                $orden,
                $sku,
                substr((string) ($articulo->descripcion ?? ''), 0, 30),
                (float) $linea->cantidad * $signo,
                (float) ($linea->cantidad_rechazada ?? 0) * $signo,
                substr((string) ($linea->motivorechazo ?? ''), 0, 40),
                (float) $linea->precio,
                (float) ($linea->descuento ?? 0),
                (int) ($linea->deposito_id ?? 1),
                $fechaAnita,
                $this->codigoMonedaAnita((int) $linea->moneda_id),
                (int) optional($linea->centrocostos)->codigo ?? 0,
                $empresaCodigo,
                (float) ($linea->cotizacion ?? 1),
                $codigoAgrupacion,
                $tipoIvaAnita,
            );

            $api->apiCallEscritura([
                'acc' => 'insert',
                'sistema' => $cfg['sistema_compras'],
                'tabla' => $cfg['tablas']['recepcion_linea'],
                'campos' => $insert['campos'],
                'valores' => $insert['valores'],
            ], 'recepcion recepmov insert orden '.$orden);
        }

        $this->grabarRecepmovImpuestoInterno($recepcion, $codigoProveedor, $clave, $fechaAnita, $empresaCodigo, $ordenMax);
    }

    /**
     * Línea recepmov sintética para el impuesto interno de cigarrillos (SKU IMPINTERNO):
     * hace que la suma de montos de recepmov coincida con el asiento COM (que lo discrimina).
     * No genera stkmov/aplicped/pendmovp (no está en la OC ni mueve stock).
     *
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private function grabarRecepmovImpuestoInterno(
        Recepcion_Proveedor $recepcion,
        string $codigoProveedor,
        array $clave,
        int $fechaAnita,
        int $empresaCodigo,
        int $ordenMax
    ): void {
        if (! RecepcionProveedorImpuestoInternoSupport::recepcionRequiereImpuestoInterno($recepcion)) {
            return;
        }

        $importe = (float) ($recepcion->impuesto_interno ?? 0);
        if ($importe <= 0.000001) {
            return;
        }

        $articulo = RecepcionProveedorImpuestoInternoSupport::resolverArticuloImpuestoInterno();
        if ($articulo === null) {
            Log::warning('RecepcionProveedorAnitaBridge: sin artículo IMPINTERNO, no se graba línea recepmov de impuesto interno', [
                'recepcion_id' => $recepcion->id,
                'sku' => RecepcionProveedorImpuestoInternoSupport::skuArticuloImpuestoInterno(),
            ]);

            return;
        }

        $articulo->loadMissing(['categorias', 'impuestos']);
        $sku = RecepcionProveedorAnitaEscrituraSupport::skuAnita13((string) ($articulo->sku ?? ''));

        $insert = RecepcionProveedorAnitaEscrituraSupport::recepmovImpuestoInternoInsert(
            $codigoProveedor,
            $clave,
            $ordenMax,
            $sku,
            substr((string) ($articulo->descripcion ?? 'IMPUESTO INTERNO'), 0, 30),
            $importe,
            (int) ($recepcion->deposito_id ?? 1),
            $fechaAnita,
            $this->codigoMonedaAnita((int) $recepcion->moneda_id),
            (int) optional($recepcion->centrocostos)->codigo ?? 0,
            $empresaCodigo,
            (float) ($recepcion->cotizacion ?? 1),
            (string) optional($articulo->categorias)->codigo,
            RecepcionProveedorAnitaEscrituraSupport::tipoIvaAnitaCodigo($articulo),
        );

        if ($insert === null) {
            return;
        }

        $orden = $ordenMax + 1;
        $cfg = config('recepcion_proveedor.anita');
        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'insert',
            'sistema' => $cfg['sistema_compras'],
            'tabla' => $cfg['tablas']['recepcion_linea'],
            'campos' => $insert['campos'],
            'valores' => $insert['valores'],
        ], 'recepcion recepmov insert IMPINTERNO orden '.$orden);
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $claveCom
     * @param  array<int, int>  $ordenesAnita
     */
    private function grabarAplicped(
        Recepcion_Proveedor $recepcion,
        string $codigoProveedor,
        array $claveCom,
        array $ordenesAnita
    ): void {
        $ocFac = $this->claveOcFacDesdeRecepcion($recepcion);
        $cfg = config('recepcion_proveedor.anita');
        $api = new ApiAnita;
        $signo = $recepcion->tipo === Recepcion_Proveedor::TIPO_DEVOLUCION ? -1 : 1;

        foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
            $linea->loadMissing('articulos');
            $articulo = $linea->articulos;
            $sku = RecepcionProveedorAnitaEscrituraSupport::skuAnita13((string) ($articulo->sku ?? ''));
            $recvOrden = RecepcionProveedorAnitaOrdenLineaSupport::ordenAnitaLinea($linea, $ordenesAnita);
            if ($recvOrden <= 0) {
                throw new \RuntimeException(
                    'Línea sin recv_orden válido para aplicped (artículo '.trim($sku).', recepción '.$recepcion->numerorecepcion.').'
                );
            }

            $penvpOrden = RecepcionProveedorAnitaOrdenLineaSupport::penvpOrdenLinea($linea);
            $nroInterno = RecepcionProveedorAnitaOrdenLineaSupport::nroInternoLinea($linea);
            if (RecepcionProveedorAnitaOrdenLineaSupport::aplicaPendmovp($linea)
                && $penvpOrden <= 0
                && $nroInterno <= 0
            ) {
                throw new \RuntimeException(
                    'Línea sin penvp_orden ni penvp_nro_interno para aplicped (artículo '.$sku.', recepción '.$recepcion->numerorecepcion.').'
                );
            }

            $insert = RecepcionProveedorAnitaEscrituraSupport::aplicpedLineaInsert(
                $codigoProveedor,
                $claveCom,
                $ocFac,
                $recvOrden,
                $penvpOrden,
                $sku,
                (float) $linea->cantidad * $signo,
                $nroInterno,
            );

            $api->apiCallEscritura([
                'acc' => 'insert',
                'sistema' => $cfg['sistema_compras'],
                'tabla' => $cfg['tablas']['aplicacion_oc'],
                'campos' => $insert['campos'],
                'valores' => $insert['valores'],
            ], 'recepcion aplicped insert recv_orden '.$recvOrden);
        }
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @param  array<int, int>  $ordenesAnita
     */
    private function grabarStkmov(
        Recepcion_Proveedor $recepcion,
        string $codigoProveedor,
        array $clave,
        int $fechaAnita,
        int $empresaCodigo,
        array $ordenesAnita,
        string $usuario
    ): void {
        $api = new ApiAnita;
        $cfg = config('recepcion_proveedor.anita');
        $empresaId = max(1, (int) ($recepcion->empresa_id ?? 1));

        foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
            $linea->loadMissing(['articulos.categorias', 'articulo_stock.categorias']);
            $articulo = $linea->articulos;
            $articuloMovimiento = (int) ($linea->articulo_stock_id ?? 0) > 0
                ? ($linea->articulo_stock ?? $articulo)
                : $articulo;
            $sku = RecepcionProveedorAnitaEscrituraSupport::skuAnita13((string) ($articuloMovimiento->sku ?? ''));
            $orden = RecepcionProveedorAnitaOrdenLineaSupport::ordenAnitaLinea($linea, $ordenesAnita);
            if ($orden <= 0) {
                throw new \RuntimeException(
                    'Línea sin stkv_nro_orden válido (artículo '.trim($sku).', recepción '.$recepcion->numerorecepcion.').'
                );
            }

            $codigoAgrupacion = (string) optional($articuloMovimiento->categorias)->codigo;
            if ($codigoAgrupacion === '') {
                $codigoAgrupacion = '0';
            }

            $cantidadStkmov = AnitaStkmovClaveErpSupport::cantidadStkmov(
                (float) ($linea->cantidad_stock ?: $linea->cantidad)
            );
            $precioStkmov = (float) ($linea->precio_stock ?? $linea->precio);

            $depositoAnita = DepmaeAnitaCodigoSupport::codigoDeposito(
                (int) ($linea->deposito_id ?? $recepcion->deposito_id ?? 0)
            );
            if ($depositoAnita <= 0) {
                throw new \RuntimeException(
                    'Línea sin depósito Anita válido (artículo '.trim($sku).', recepción '.$recepcion->numerorecepcion.').'
                );
            }

            $insert = RecepcionProveedorAnitaEscrituraSupport::stkmovInsert(
                $clave,
                $fechaAnita,
                $sku,
                $codigoAgrupacion,
                $orden,
                $codigoProveedor,
                $depositoAnita,
                $cantidadStkmov,
                $precioStkmov,
                $this->codigoMonedaAnita((int) $linea->moneda_id),
                $empresaCodigo,
                $usuario,
                $empresaId,
            );

            $api->apiCallEscritura(
                StockAnitaBridgeSupport::mergePayload([
                    'acc' => 'insert',
                    'sistema' => $cfg['sistema_ventas'],
                    'tabla' => $cfg['tablas']['stock_movimiento'],
                    'campos' => $insert['campos'],
                    'valores' => $insert['valores'],
                ], $empresaId),
                'recepcion stkmov insert orden '.$orden
            );
        }
    }

    private function actualizarPendmovp(Recepcion_Proveedor $recepcion, int $multiplicador): void
    {
        $oc = $recepcion->ordencompras;
        if (! $oc) {
            throw new \RuntimeException('Recepción sin orden de compra asociada para actualizar pendmovp.');
        }

        $cfg = config('recepcion_proveedor.anita');
        $claveOc = [
            'tipo' => $cfg['oc_tipo'],
            'letra' => $cfg['oc_letra'],
            'sucursal' => (int) $cfg['oc_sucursal'],
            'nro' => (int) $oc->numeroordencompra,
        ];
        $api = new ApiAnita;
        $signo = $recepcion->tipo === Recepcion_Proveedor::TIPO_DEVOLUCION ? -1 : 1;
        $plan = [];

        foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
            if (! RecepcionProveedorAnitaOrdenLineaSupport::aplicaPendmovp($linea)) {
                continue;
            }

            $articulo = $linea->articulos;
            $sku = trim((string) ($articulo->sku ?? ''));
            $nroInterno = RecepcionProveedorAnitaOrdenLineaSupport::nroInternoLinea($linea);
            $penvpOrden = (int) ($linea->penvp_orden ?? 0);
            if ($nroInterno <= 0 && $penvpOrden <= 0) {
                throw new \RuntimeException(
                    'Línea sin penvp_nro_interno ni penvp_orden para pendmovp (artículo '.$sku.', OC '.$oc->numeroordencompra.').'
                );
            }

            $where = RecepcionProveedorAnitaWhereSupport::pendmovpLinea(
                $claveOc,
                (int) $oc->numeroordencompra,
                $nroInterno,
                $penvpOrden,
                $sku
            );

            $rows = json_decode($api->apiCall([
                'acc' => 'list',
                'sistema' => $cfg['sistema_compras'],
                'tabla' => $cfg['tablas']['oc_linea'],
                'campos' => 'penvp_cantentr, penvp_cantidad',
                'whereArmado' => $where,
            ]));

            if (! is_array($rows) || count($rows) === 0) {
                $ref = $nroInterno > 0 ? 'nro_interno '.$nroInterno : 'orden '.$penvpOrden;
                throw new \RuntimeException(
                    'No se encontró línea pendmovp para OC '.(int) $oc->numeroordencompra
                    .', '.$ref.', artículo '.$sku.'.'
                );
            }

            $cantidadOc = (float) ($rows[0]->penvp_cantidad ?? 0);
            $ref = $nroInterno > 0 ? 'nro_interno '.$nroInterno : 'orden '.$penvpOrden;

            if ((bool) ($linea->fl_cerrar_linea_oc ?? false)) {
                $plan[] = [
                    'where' => $where,
                    'cerrar_linea' => true,
                    'cantidad_oc' => $cantidadOc,
                    'ref' => $ref,
                ];

                continue;
            }

            $delta = ((float) $linea->cantidad + (float) ($linea->cantidad_rechazada ?? 0)) * $signo * $multiplicador;

            $actual = (float) ($rows[0]->penvp_cantentr ?? 0);
            $nuevaCant = $actual + $delta;

            $plan[] = [
                'where' => $where,
                'nueva_cant' => $nuevaCant,
                'ref' => $ref,
            ];
        }

        $whereCab = " WHERE
            penmp_tipo='{$cfg['oc_tipo']}' and
            penmp_letra='{$cfg['oc_letra']}' and
            penmp_sucursal={$cfg['oc_sucursal']} and
            penmp_nro=".(int) $oc->numeroordencompra;

        foreach ($plan as $paso) {
            $valores = ! empty($paso['cerrar_linea'])
                ? RecepcionProveedorAnitaEscrituraSupport::pendmovpCerrarLineaUpdateSet((float) $paso['cantidad_oc'])
                : RecepcionProveedorAnitaEscrituraSupport::pendmovpCantentrUpdateSet($paso['nueva_cant']);

            $api->apiCallEscritura([
                'acc' => 'update',
                'sistema' => $cfg['sistema_compras'],
                'tabla' => $cfg['tablas']['oc_linea'],
                'valores' => $valores,
                'whereArmado' => $paso['where'],
            ], 'recepcion pendmovp update '.$paso['ref']);
        }

        if ($plan !== []) {
            $estadoCabecera = $this->resolverEstadoCabeceraOcDesdePendmovp($api, $cfg, (int) $oc->numeroordencompra);
            $api->apiCallEscritura([
                'acc' => 'update',
                'sistema' => $cfg['sistema_compras'],
                'tabla' => $cfg['tablas']['oc_cabecera'],
                'valores' => RecepcionProveedorAnitaEscrituraSupport::penmpEstadoUpdateSet($estadoCabecera),
                'whereArmado' => $whereCab,
            ], 'recepcion penmp estado OC '.(int) $oc->numeroordencompra);
        }
    }

    /**
     * Cabecera C solo si todas las líneas pendmovp están completas o cerradas (partida -1).
     */
    private function resolverEstadoCabeceraOcDesdePendmovp(ApiAnita $api, array $cfg, int $numeroOc): string
    {
        $whereLineas = " WHERE
            penvp_tipo='{$cfg['oc_tipo']}' and
            penvp_letra='{$cfg['oc_letra']}' and
            penvp_sucursal=".(int) $cfg['oc_sucursal']." and
            penvp_nro={$numeroOc}";

        $rows = json_decode($api->apiCall([
            'acc' => 'list',
            'sistema' => $cfg['sistema_compras'],
            'tabla' => $cfg['tablas']['oc_linea'],
            'campos' => 'penvp_cantentr, penvp_cantidad, penvp_partida',
            'whereArmado' => $whereLineas,
        ]));

        if (! is_array($rows) || $rows === []) {
            return \App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaEstadosSupport::PENTREGAR;
        }

        $algunaActividad = false;
        $todasCompletas = true;

        foreach ($rows as $row) {
            $cantOc = (float) ($row->penvp_cantidad ?? 0);
            $cantEntr = (float) ($row->penvp_cantentr ?? 0);
            $partida = (int) ($row->penvp_partida ?? 0);
            $cerrada = $partida === -1;
            $completa = $cerrada || ($cantOc > 0.000001 && $cantEntr + 0.000001 >= $cantOc);

            if (! $completa) {
                $todasCompletas = false;
            }
            if ($cantEntr > 0.000001 || $cerrada) {
                $algunaActividad = true;
            }
        }

        return \App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaEstadosSupport::desdeRecepcionPendmovp(
            $algunaActividad,
            $todasCompletas
        );
    }

    private function codigoMonedaAnita(int $monedaId): string
    {
        return match ($monedaId) {
            2 => '2',
            3 => '3',
            default => '1',
        };
    }
}
