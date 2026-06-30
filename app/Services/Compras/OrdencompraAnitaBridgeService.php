<?php

namespace App\Services\Compras;

use App\ApiAnita;
use App\Models\Compras\Ordencompra;
use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaEscrituraSupport;
use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaErpContext;
use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaLineaSupport;
use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaNumeracionSupport;
use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaWhereSupport;
use Illuminate\Support\Facades\Log;

/**
 * Escritura ERP → Anita (pendmaep, pendmovp, movpresup) con rollback ante fallos.
 */
class OrdencompraAnitaBridgeService
{
    public function habilitado(): bool
    {
        return OrdencompraAnitaNumeracionSupport::estaHabilitada();
    }

    public function sincronizarAlta(Ordencompra $oc): void
    {
        if (! $this->habilitado()) {
            return;
        }

        $this->cargarRelaciones($oc);
        $this->validarCabeceraMinima($oc);

        $ctx = OrdencompraAnitaErpContext::desdeUsuarioActual();
        $clave = OrdencompraAnitaWhereSupport::claveDesdeOrdencompra($oc);
        $estado = ['cabecera_nueva' => false, 'detalle_grabado' => false, 'numero' => (int) $oc->numeroordencompra];

        try {
            OrdencompraAnitaLineaSupport::asignarClavesLineas($oc);
            $this->cargarRelaciones($oc);

            if ($this->existePendmaep($clave)) {
                throw new \RuntimeException(
                    'Ya existe una OC PEP/X/0 #'.$oc->numeroordencompra.' en Anita (pendmaep).'
                );
            }

            $this->insertarPendmaep($oc, $ctx, $clave);
            $estado['cabecera_nueva'] = true;

            $this->grabarDetalle($oc, $ctx, $clave);
            $estado['detalle_grabado'] = true;

            OrdencompraAnitaNumeracionSupport::registrarNumeroAsignadoEnNumerador((int) $oc->numeroordencompra);
        } catch (\Throwable $e) {
            $this->revertir($clave, $estado);
            throw new \RuntimeException('Error al grabar la orden de compra en Anita: '.$e->getMessage(), 0, $e);
        }
    }

    public function sincronizarActualizacion(Ordencompra $oc): void
    {
        if (! $this->habilitado()) {
            return;
        }

        $this->cargarRelaciones($oc);
        $this->validarCabeceraMinima($oc);

        $ctx = OrdencompraAnitaErpContext::desdeUsuarioActual();
        $clave = OrdencompraAnitaWhereSupport::claveDesdeOrdencompra($oc);
        $backup = $this->leerBackupAnita($clave);
        $estado = ['cabecera_nueva' => false, 'detalle_grabado' => false, 'numero' => (int) $oc->numeroordencompra];

        try {
            OrdencompraAnitaLineaSupport::asignarClavesLineas($oc);
            $this->cargarRelaciones($oc);

            if ($backup['cabecera'] === null) {
                $this->insertarPendmaep($oc, $ctx, $clave);
                $estado['cabecera_nueva'] = true;
            } else {
                $this->actualizarPendmaep($oc, $ctx, $clave);
            }

            $this->eliminarDetalle($clave);
            $this->grabarDetalle($oc, $ctx, $clave);
            $estado['detalle_grabado'] = true;

            OrdencompraAnitaNumeracionSupport::registrarNumeroAsignadoEnNumerador((int) $oc->numeroordencompra);
        } catch (\Throwable $e) {
            $this->revertirConBackup($clave, $estado, $backup);
            throw new \RuntimeException('Error al actualizar la orden de compra en Anita: '.$e->getMessage(), 0, $e);
        }
    }

    public function sincronizarBaja(Ordencompra $oc): void
    {
        if (! $this->habilitado()) {
            return;
        }

        $clave = OrdencompraAnitaWhereSupport::claveDesdeOrdencompra($oc);

        if (! $this->existePendmaep($clave)) {
            return;
        }

        $this->assertSinRecepcionesAplicadas($clave);

        $estado = ['cabecera_nueva' => false, 'detalle_grabado' => true, 'numero' => (int) $oc->numeroordencompra];

        try {
            $this->eliminarDetalle($clave);
            $this->eliminarPendmaep($clave);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Error al eliminar la orden de compra en Anita: '.$e->getMessage(), 0, $e);
        }
    }

    private function cargarRelaciones(Ordencompra $oc): void
    {
        $oc->loadMissing([
            'empresas', 'centrocostos', 'proveedores', 'requisiciones', 'transportes',
            'condicioncompras', 'condicionentregas',
            'ordencompra_articulos.articulos.categorias',
            'ordencompra_articulos.articulos.impuestos',
            'ordencompra_articulos.articulos.unidadesdemedidas',
            'ordencompra_articulos.monedas',
            'ordencompra_articulos.centrocostos_destino',
            'ordencompra_articulos.partidagastos.presupuestos',
            'ordencompra_articulos.partidagastos.presupuesto_escenarios',
            'ordencompra_articulos.capexs',
            'ordencompra_comprobantes.condicionpagos',
        ]);
    }

    private function validarCabeceraMinima(Ordencompra $oc): void
    {
        if ((int) $oc->numeroordencompra <= 0) {
            throw new \RuntimeException('La orden de compra no tiene número asignado.');
        }
        if (empty($oc->proveedor_id)) {
            throw new \RuntimeException('Proveedor obligatorio para grabar en Anita.');
        }
        if (empty($oc->empresa_id) || empty($oc->centrocosto_id)) {
            throw new \RuntimeException('Empresa y centro de costo son obligatorios para Anita.');
        }
        if ($oc->ordencompra_articulos->isEmpty()) {
            throw new \RuntimeException('La orden de compra debe tener al menos un ítem.');
        }

        foreach ($oc->ordencompra_articulos as $linea) {
            if (empty($linea->articulo_id) || (float) ($linea->cantidad ?? 0) <= 0) {
                throw new \RuntimeException('Cada ítem debe tener artículo y cantidad mayor a cero.');
            }
        }
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function existePendmaep(array $clave): bool
    {
        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'list',
            'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
            'tabla' => config('ordencompra_anita.tablas.cabecera'),
            'campos' => 'penmp_nro',
            'whereArmado' => OrdencompraAnitaWhereSupport::pendmaep($clave),
            'limit' => 'FIRST 1',
        ], 'ordencompra pendmaep existe');

        return ApiAnita::primeraFilaLista((string) $raw) !== null;
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function assertSinRecepcionesAplicadas(array $clave): void
    {
        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'list',
            'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
            'tabla' => config('ordencompra_anita.tablas.linea'),
            'campos' => 'penvp_cantentr',
            'whereArmado' => OrdencompraAnitaWhereSupport::pendmovp($clave),
        ], 'ordencompra pendmovp cantentr baja');

        $filas = ApiAnita::decodificarListaFilas((string) $raw);
        foreach ($filas as $fila) {
            if ((float) ($fila->penvp_cantentr ?? 0) > 0) {
                throw new \RuntimeException(
                    'No se puede eliminar la OC #'.$clave['nro'].' en Anita: tiene cantidades recibidas (penvp_cantentr > 0).'
                );
            }
        }
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function insertarPendmaep(Ordencompra $oc, OrdencompraAnitaErpContext $ctx, array $clave): void
    {
        $insert = OrdencompraAnitaEscrituraSupport::pendmaepInsert($oc, $ctx, $clave);
        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'insert',
            'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
            'tabla' => config('ordencompra_anita.tablas.cabecera'),
            'campos' => $insert['campos'],
            'valores' => $insert['valores'],
        ], 'ordencompra pendmaep insert');
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function actualizarPendmaep(Ordencompra $oc, OrdencompraAnitaErpContext $ctx, array $clave): void
    {
        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'update',
            'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
            'tabla' => config('ordencompra_anita.tablas.cabecera'),
            'valores' => OrdencompraAnitaEscrituraSupport::pendmaepUpdateSet($oc, $ctx, $clave),
            'whereArmado' => OrdencompraAnitaWhereSupport::pendmaep($clave),
        ], 'ordencompra pendmaep update');
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function grabarDetalle(Ordencompra $oc, OrdencompraAnitaErpContext $ctx, array $clave): void
    {
        $codigoProveedor = $ctx->codigoProveedor6((int) $oc->proveedor_id);
        $lineas = $oc->ordencompra_articulos->sortBy([['penvp_orden', 'asc'], ['id', 'asc']]);

        foreach ($lineas as $linea) {
            $nroInterno = (int) ($linea->penvp_nro_interno ?? 0);
            if ($nroInterno <= 0) {
                throw new \RuntimeException('Línea OC sin penvp_nro_interno antes de grabar Anita.');
            }

            $insertLinea = OrdencompraAnitaEscrituraSupport::pendmovpInsert($oc, $linea, $ctx, $clave, $codigoProveedor);
            $api = new ApiAnita;
            $api->apiCallEscritura([
                'acc' => 'insert',
                'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
                'tabla' => config('ordencompra_anita.tablas.linea'),
                'campos' => $insertLinea['campos'],
                'valores' => $insertLinea['valores'],
            ], 'ordencompra pendmovp insert');

            $insertMovp = OrdencompraAnitaEscrituraSupport::movpresupInsert($oc, $linea, $ctx, $clave);
            $api->apiCallEscritura([
                'acc' => 'insert',
                'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
                'tabla' => config('ordencompra_anita.tablas.presupuesto_linea'),
                'campos' => $insertMovp['campos'],
                'valores' => $insertMovp['valores'],
            ], 'ordencompra movpresup insert');
        }
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function eliminarDetalle(array $clave): void
    {
        $api = new ApiAnita;
        $sistema = OrdencompraAnitaNumeracionSupport::sistemaTComp();

        $api->apiCallEscritura([
            'acc' => 'delete',
            'sistema' => $sistema,
            'tabla' => config('ordencompra_anita.tablas.presupuesto_linea'),
            'whereArmado' => OrdencompraAnitaWhereSupport::movpresup($clave),
        ], 'ordencompra movpresup delete');

        $api->apiCallEscritura([
            'acc' => 'delete',
            'sistema' => $sistema,
            'tabla' => config('ordencompra_anita.tablas.linea'),
            'whereArmado' => OrdencompraAnitaWhereSupport::pendmovp($clave),
        ], 'ordencompra pendmovp delete');
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function eliminarPendmaep(array $clave): void
    {
        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'delete',
            'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
            'tabla' => config('ordencompra_anita.tablas.cabecera'),
            'whereArmado' => OrdencompraAnitaWhereSupport::pendmaep($clave),
        ], 'ordencompra pendmaep delete');
    }

    /**
     * @param  array{cabecera_nueva: bool, detalle_grabado: bool, numero: int}  $estado
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private function revertir(array $clave, array $estado): void
    {
        try {
            if ($estado['detalle_grabado']) {
                $this->eliminarDetalle($clave);
            }
            if ($estado['cabecera_nueva']) {
                $this->eliminarPendmaep($clave);
            }
        } catch (\Throwable $e) {
            Log::error('OrdencompraAnitaBridge: rollback incompleto', [
                'numero_oc' => $estado['numero'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array{cabecera_nueva: bool, detalle_grabado: bool, numero: int}  $estado
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @param  array{cabecera: ?object, lineas: list<object>, movpresup: list<object>}  $backup
     */
    private function revertirConBackup(array $clave, array $estado, array $backup): void
    {
        try {
            $this->eliminarDetalle($clave);
            if ($estado['cabecera_nueva']) {
                $this->eliminarPendmaep($clave);
            } elseif ($backup['cabecera'] !== null) {
                $this->restaurarCabeceraDesdeBackup($backup['cabecera'], $clave);
            }

            $this->restaurarDetalleDesdeBackup($backup, $clave);
        } catch (\Throwable $e) {
            Log::error('OrdencompraAnitaBridge: rollback actualización incompleto', [
                'numero_oc' => $estado['numero'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @return array{cabecera: ?object, lineas: list<object>, movpresup: list<object>}
     */
    private function leerBackupAnita(array $clave): array
    {
        $api = new ApiAnita;
        $sistema = OrdencompraAnitaNumeracionSupport::sistemaTComp();
        $whereCab = OrdencompraAnitaWhereSupport::pendmaep($clave);

        $cabRaw = $api->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => config('ordencompra_anita.tablas.cabecera'),
            'campos' => implode(', ', [
                'penmp_proveedor', 'penmp_tipo', 'penmp_letra', 'penmp_sucursal', 'penmp_nro',
                'penmp_fecha', 'penmp_fecha_ent', 'penmp_cond_compra', 'penmp_cond_entrega', 'penmp_cond_pago',
                'penmp_entrega', 'penmp_dto', 'penmp_expreso', 'penmp_cod_mon', 'penmp_cotizacion',
                'penmp_estado', 'penmp_leyenda', 'penmp_requisicion', 'penmp_ccosto', 'penmp_ccosto_dest',
                'penmp_empresa', 'penmp_es_anticipo', 'penmp_usuario_ini', 'penmp_fecha_ing', 'penmp_hora_ing',
                'penmp_estado_aprob', 'penmp_legajo',
            ]),
            'whereArmado' => $whereCab,
            'limit' => 'FIRST 1',
        ]);

        $lineasRaw = $api->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => config('ordencompra_anita.tablas.linea'),
            'campos' => implode(', ', [
                'penvp_proveedor', 'penvp_tipo', 'penvp_letra', 'penvp_sucursal', 'penvp_nro', 'penvp_orden',
                'penvp_articulo', 'penvp_desc', 'penvp_agrupacion', 'penvp_unidad_med', 'penvp_cantidad',
                'penvp_cantentr', 'penvp_cantfact', 'penvp_precio', 'penvp_dto_art', 'penvp_deposito',
                'penvp_tipo_iva', 'penvp_fecha', 'penvp_incl_imp', 'penvp_cod_mon', 'penvp_partida',
                'penvp_fecha_ent', 'penvp_ccosto', 'penvp_requisicion', 'penvp_empresa', 'penvp_nro_interno',
            ]),
            'whereArmado' => OrdencompraAnitaWhereSupport::pendmovp($clave),
        ]);

        $movpRaw = $api->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => config('ordencompra_anita.tablas.presupuesto_linea'),
            'campos' => implode(', ', [
                'movp_tipo', 'movp_letra', 'movp_sucursal', 'movp_nro', 'movp_nro_interno', 'movp_partida',
                'movp_presupuesto', 'movp_escenario', 'movp_proyecto', 'movp_mes', 'movp_cod_proyecto',
                'movp_importe', 'movp_articulo', 'movp_fecha', 'movp_cotizacion',
            ]),
            'whereArmado' => OrdencompraAnitaWhereSupport::movpresup($clave),
        ]);

        $cab = ApiAnita::primeraFilaLista((string) $cabRaw);

        return [
            'cabecera' => $cab,
            'lineas' => ApiAnita::decodificarListaFilas((string) $lineasRaw),
            'movpresup' => ApiAnita::decodificarListaFilas((string) $movpRaw),
        ];
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function restaurarCabeceraDesdeBackup(object $cab, array $clave): void
    {
        $sets = [];
        foreach ((array) $cab as $col => $val) {
            if (! is_string($col) || ! str_starts_with($col, 'penmp_')) {
                continue;
            }
            if (in_array($col, ['penmp_tipo', 'penmp_letra', 'penmp_sucursal', 'penmp_nro'], true)) {
                continue;
            }
            $sets[] = $col.' = '.$this->valorSqlBackup($col, $val);
        }

        if ($sets === []) {
            return;
        }

        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'update',
            'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
            'tabla' => config('ordencompra_anita.tablas.cabecera'),
            'valores' => implode(', ', $sets),
            'whereArmado' => OrdencompraAnitaWhereSupport::pendmaep($clave),
        ], 'ordencompra pendmaep restore backup');
    }

    /**
     * @param  array{cabecera: ?object, lineas: list<object>, movpresup: list<object>}  $backup
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private function restaurarDetalleDesdeBackup(array $backup, array $clave): void
    {
        $sistema = OrdencompraAnitaNumeracionSupport::sistemaTComp();
        $api = new ApiAnita;

        foreach ($backup['lineas'] as $fila) {
            $cols = [];
            $vals = [];
            foreach ((array) $fila as $col => $val) {
                if (! is_string($col) || ! str_starts_with($col, 'penvp_')) {
                    continue;
                }
                $cols[] = $col;
                $vals[] = $this->valorSqlBackup($col, $val);
            }
            if ($cols === []) {
                continue;
            }
            $api->apiCallEscritura([
                'acc' => 'insert',
                'sistema' => $sistema,
                'tabla' => config('ordencompra_anita.tablas.linea'),
                'campos' => implode(', ', $cols),
                'valores' => implode(', ', $vals),
            ], 'ordencompra pendmovp restore backup');
        }

        foreach ($backup['movpresup'] as $fila) {
            $cols = [];
            $vals = [];
            foreach ((array) $fila as $col => $val) {
                if (! is_string($col) || ! str_starts_with($col, 'movp_')) {
                    continue;
                }
                $cols[] = $col;
                $vals[] = $this->valorSqlBackup($col, $val);
            }
            if ($cols === []) {
                continue;
            }
            $api->apiCallEscritura([
                'acc' => 'insert',
                'sistema' => $sistema,
                'tabla' => config('ordencompra_anita.tablas.presupuesto_linea'),
                'campos' => implode(', ', $cols),
                'valores' => implode(', ', $vals),
            ], 'ordencompra movpresup restore backup');
        }
    }

    private function valorSqlBackup(string $columna, mixed $valor): string
    {
        $s = trim((string) $valor);
        if ($s === '' || $s === 'null') {
            if (str_contains($columna, 'fecha') && ! str_contains($columna, 'hora')) {
                return '0';
            }
            if (preg_match('/_(cant|precio|dto|importe|cotizacion|nro|ccosto|partida|presupuesto|escenario|proyecto|mes|deposito|sucursal|empresa|requisicion|expreso|legajo|orden|interno)/', $columna)) {
                return '0';
            }

            return "''";
        }

        if (is_numeric($s) && ! preg_match('/^(penmp_letra|penvp_letra|movp_letra|penvp_incl_imp|penmp_es_anticipo|penmp_entrega|penmp_leyenda|penmp_hora_ing|penmp_estado_aprob|penmp_hora_aprob|penmp_razon_susp)/', $columna)) {
            if (preg_match('/(precio|dto|cant|importe|cotizacion)/', $columna)) {
                return number_format((float) $s, 4, '.', '');
            }

            return (string) (int) $s;
        }

        return "'".str_replace("'", "''", $s)."'";
    }
}
