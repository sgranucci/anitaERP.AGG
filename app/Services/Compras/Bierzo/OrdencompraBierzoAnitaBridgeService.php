<?php

namespace App\Services\Compras\Bierzo;

use App\ApiAnita;
use App\Models\Compras\Ordencompra;
use App\Support\Compras\AnitaSync\Bierzo\OrdencompraBierzoAnitaEscrituraSupport;
use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaErpContext;
use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaLineaSupport;
use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaNumeracionSupport;
use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaWhereSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Stock\SurmarSupport;
use Illuminate\Support\Facades\Log;

/**
 * Escritura ERP → Anita El Bierzo (/usr2/bierzo): solo pendmaep + pendmovp.
 * Aislado del bridge AGG (movpresup, ocvley, occuota, pendfecha, legcompra)
 * y del bridge Surmar (/usr2/surmar).
 */
class OrdencompraBierzoAnitaBridgeService
{
    public function habilitado(): bool
    {
        return OrdencompraAnitaNumeracionSupport::estaHabilitada()
            && EntornoEmpresaSupport::esElBierzo();
    }

    public function sincronizarAlta(Ordencompra $oc): void
    {
        if (! $this->habilitado()) {
            return;
        }

        $this->assertBierzo($oc);
        $this->cargarRelaciones($oc);
        $this->validarCabeceraMinima($oc);

        $ctx = OrdencompraAnitaErpContext::desdeUsuarioActual();
        $clave = OrdencompraAnitaWhereSupport::claveDesdeOrdencompra($oc);
        $estado = [
            'cabecera_nueva' => false,
            'detalle_grabado' => false,
            'numero' => (int) $oc->numeroordencompra,
        ];

        try {
            OrdencompraAnitaLineaSupport::asignarClavesLineas($oc);
            $this->cargarRelaciones($oc);

            if ($this->existePendmaep($clave)) {
                throw new \RuntimeException(
                    'Ya existe una OC PEP/X/0 #'.$oc->numeroordencompra.' en Anita El Bierzo (pendmaep).'
                );
            }

            $this->insertarPendmaep($oc, $ctx, $clave);
            $estado['cabecera_nueva'] = true;

            $this->grabarDetalle($oc, $ctx, $clave);
            $estado['detalle_grabado'] = true;

            OrdencompraAnitaNumeracionSupport::registrarNumeroAsignadoEnNumerador(
                (int) $oc->numeroordencompra,
                (int) ($oc->empresa_id ?? 0)
            );
        } catch (\Throwable $e) {
            $this->revertir($clave, $estado);
            throw new \RuntimeException('Error al grabar la orden de compra en Anita El Bierzo: '.$e->getMessage(), 0, $e);
        }
    }

    public function sincronizarActualizacion(Ordencompra $oc): void
    {
        if (! $this->habilitado()) {
            return;
        }

        $this->assertBierzo($oc);
        $this->cargarRelaciones($oc);
        $this->validarCabeceraMinima($oc);

        $ctx = OrdencompraAnitaErpContext::desdeUsuarioActual();
        $clave = OrdencompraAnitaWhereSupport::claveDesdeOrdencompra($oc);
        $estado = [
            'cabecera_nueva' => false,
            'detalle_grabado' => false,
            'numero' => (int) $oc->numeroordencompra,
        ];

        try {
            OrdencompraAnitaLineaSupport::asignarClavesLineas($oc);
            $this->cargarRelaciones($oc);

            if (! $this->existePendmaep($clave)) {
                $this->insertarPendmaep($oc, $ctx, $clave);
                $estado['cabecera_nueva'] = true;
            } else {
                $this->actualizarPendmaep($oc, $ctx, $clave);
            }

            $this->eliminarDetalle($clave);
            $this->grabarDetalle($oc, $ctx, $clave);
            $estado['detalle_grabado'] = true;

            OrdencompraAnitaNumeracionSupport::registrarNumeroAsignadoEnNumerador(
                (int) $oc->numeroordencompra,
                (int) ($oc->empresa_id ?? 0)
            );
        } catch (\Throwable $e) {
            if ($estado['cabecera_nueva'] ?? false) {
                $this->revertir($clave, $estado);
            }
            throw new \RuntimeException('Error al actualizar la orden de compra en Anita El Bierzo: '.$e->getMessage(), 0, $e);
        }
    }

    public function sincronizarBaja(Ordencompra $oc): void
    {
        if (! $this->habilitado()) {
            return;
        }

        $this->assertBierzo($oc);
        $clave = OrdencompraAnitaWhereSupport::claveDesdeOrdencompra($oc);

        if (! $this->existePendmaep($clave)) {
            return;
        }

        try {
            $this->eliminarDetalle($clave);
            $this->eliminarPendmaep($clave);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Error al eliminar la orden de compra en Anita El Bierzo: '.$e->getMessage(), 0, $e);
        }
    }

    private function assertBierzo(Ordencompra $oc): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            throw new \RuntimeException('Escritura El Bierzo solo en entorno EL BIERZO.');
        }
        if (SurmarSupport::esEmpresaSurmar((int) ($oc->empresa_id ?? 0))) {
            throw new \RuntimeException('OC Surmar debe usar el bridge Surmar, no el de El Bierzo.');
        }
    }

    private function cargarRelaciones(Ordencompra $oc): void
    {
        $oc->loadMissing([
            'empresas', 'centrocostos', 'proveedores', 'requisiciones', 'transportes',
            'condicioncompras', 'condicionentregas', 'condicionpagos',
            'ordencompra_articulos.articulos.categorias',
            'ordencompra_articulos.articulos.impuestos',
            'ordencompra_articulos.articulos.unidadesdemedidas',
            'ordencompra_articulos.monedas',
            'ordencompra_articulos.centrocostos_destino',
            'ordencompra_articulos.partidagastos.presupuestos',
            'ordencompra_articulos.partidagastos.presupuesto_escenarios',
        ]);
    }

    private function validarCabeceraMinima(Ordencompra $oc): void
    {
        if ((int) $oc->numeroordencompra <= 0) {
            throw new \RuntimeException('La orden de compra no tiene número asignado.');
        }
        if (empty($oc->proveedor_id)) {
            throw new \RuntimeException('Proveedor obligatorio para grabar en Anita El Bierzo.');
        }
        if (empty($oc->empresa_id)) {
            throw new \RuntimeException('Empresa obligatoria para grabar en Anita El Bierzo.');
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function anitaEscritura(ApiAnita $api, array $payload, ?string $contexto = null): string
    {
        // Path por defecto: config('anita.bdd_path') = /usr2/bierzo
        return $api->apiCallEscritura($payload, $contexto);
    }

    private function sistemaCompras(): string
    {
        return OrdencompraAnitaNumeracionSupport::sistemaTComp();
    }

    private function tablaCabecera(): string
    {
        return (string) config('ordencompra_anita.tablas.cabecera', 'pendmaep');
    }

    private function tablaLinea(): string
    {
        return (string) config('ordencompra_anita.tablas.linea', 'pendmovp');
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function existePendmaep(array $clave): bool
    {
        $api = new ApiAnita;
        $raw = $this->anitaEscritura($api, [
            'acc' => 'list',
            'sistema' => $this->sistemaCompras(),
            'tabla' => $this->tablaCabecera(),
            'campos' => 'penmp_nro',
            'whereArmado' => OrdencompraAnitaWhereSupport::pendmaep($clave),
            'limit' => 'FIRST 1',
        ], 'ordencompra bierzo pendmaep existe');

        return ApiAnita::primeraFilaLista((string) $raw) !== null;
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function insertarPendmaep(Ordencompra $oc, OrdencompraAnitaErpContext $ctx, array $clave): void
    {
        $insert = OrdencompraBierzoAnitaEscrituraSupport::pendmaepInsert($oc, $ctx, $clave);
        $api = new ApiAnita;
        $this->anitaEscritura($api, [
            'acc' => 'insert',
            'sistema' => $this->sistemaCompras(),
            'tabla' => $this->tablaCabecera(),
            'campos' => $insert['campos'],
            'valores' => $insert['valores'],
        ], 'ordencompra bierzo pendmaep insert');
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function actualizarPendmaep(Ordencompra $oc, OrdencompraAnitaErpContext $ctx, array $clave): void
    {
        $api = new ApiAnita;
        $this->anitaEscritura($api, [
            'acc' => 'update',
            'sistema' => $this->sistemaCompras(),
            'tabla' => $this->tablaCabecera(),
            'valores' => OrdencompraBierzoAnitaEscrituraSupport::pendmaepUpdateSet($oc, $ctx, $clave),
            'whereArmado' => OrdencompraAnitaWhereSupport::pendmaep($clave),
        ], 'ordencompra bierzo pendmaep update');
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function grabarDetalle(Ordencompra $oc, OrdencompraAnitaErpContext $ctx, array $clave): void
    {
        $codigoProveedor = $ctx->codigoProveedor6((int) $oc->proveedor_id);
        $lineas = $oc->ordencompra_articulos->sortBy([['penvp_orden', 'asc'], ['id', 'asc']]);
        $api = new ApiAnita;

        foreach ($lineas as $linea) {
            $nroInterno = (int) ($linea->penvp_nro_interno ?? 0);
            if ($nroInterno <= 0) {
                throw new \RuntimeException('Línea OC sin penvp_nro_interno antes de grabar Anita El Bierzo.');
            }

            $insertLinea = OrdencompraBierzoAnitaEscrituraSupport::pendmovpInsert(
                $oc,
                $linea,
                $ctx,
                $clave,
                $codigoProveedor
            );
            $this->anitaEscritura($api, [
                'acc' => 'insert',
                'sistema' => $this->sistemaCompras(),
                'tabla' => $this->tablaLinea(),
                'campos' => $insertLinea['campos'],
                'valores' => $insertLinea['valores'],
            ], 'ordencompra bierzo pendmovp insert');
        }
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function eliminarDetalle(array $clave): void
    {
        $api = new ApiAnita;
        $this->anitaEscritura($api, [
            'acc' => 'delete',
            'sistema' => $this->sistemaCompras(),
            'tabla' => $this->tablaLinea(),
            'whereArmado' => OrdencompraAnitaWhereSupport::pendmovp($clave),
        ], 'ordencompra bierzo pendmovp delete');
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function eliminarPendmaep(array $clave): void
    {
        $api = new ApiAnita;
        $this->anitaEscritura($api, [
            'acc' => 'delete',
            'sistema' => $this->sistemaCompras(),
            'tabla' => $this->tablaCabecera(),
            'whereArmado' => OrdencompraAnitaWhereSupport::pendmaep($clave),
        ], 'ordencompra bierzo pendmaep delete');
    }

    /**
     * @param  array{cabecera_nueva: bool, detalle_grabado: bool, numero: int}  $estado
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private function revertir(array $clave, array $estado): void
    {
        try {
            if ($estado['detalle_grabado'] ?? false) {
                $this->eliminarDetalle($clave);
            }
            if ($estado['cabecera_nueva'] ?? false) {
                $this->eliminarPendmaep($clave);
            }
        } catch (\Throwable $e) {
            Log::error('OrdencompraBierzoAnitaBridge: rollback incompleto', [
                'numero_oc' => $estado['numero'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
