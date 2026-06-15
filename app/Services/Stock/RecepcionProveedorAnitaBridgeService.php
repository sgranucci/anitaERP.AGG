<?php

namespace App\Services\Stock;

use App\ApiAnita;
use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Stock\RecepcionProveedorAnitaClaveSupport;
use App\Support\Stock\RecepcionProveedorAnitaWhereSupport;
use App\Support\Stock\RecpunicaAnitaBridgeSupport;
use Auth;
use Illuminate\Support\Facades\Log;

class RecepcionProveedorAnitaBridgeService
{
    public function sincronizarRecepcion(Recepcion_Proveedor $recepcion): void
    {
        if ((int) $recepcion->numerorecepcion <= 0) {
            throw new \RuntimeException('La recepción debe tener numerorecepcion asignado.');
        }

        $recepcion->loadMissing([
            'proveedores', 'empresas', 'ordencompras',
            'recepcion_proveedor_articulos.articulos',
            'recepcion_proveedor_articulos.centrocostos',
        ]);

        $clave = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
        RecepcionProveedorAnitaClaveSupport::asignarEnRecepcion($recepcion, $clave);

        $codigoProveedor = RecepcionProveedorAnitaWhereSupport::codigoProveedorAnita($recepcion);
        $fechaAnita = (int) str_replace('-', '', $recepcion->fecha->format('Y-m-d'));
        $usuario = substr((string) (Auth::user()->usuario ?? Auth::user()->name ?? 'ERP'), 0, 8);
        $empresaCodigo = (int) ($recepcion->empresas->codigo ?? $recepcion->empresa_id);

        if ($this->existeRecepmae($codigoProveedor, $clave)) {
            $this->actualizarRecepmae($recepcion, $codigoProveedor, $clave, $fechaAnita, $usuario, $empresaCodigo);
            $this->eliminarRecepmov($clave);
        } else {
            $this->grabarRecepmae($recepcion, $codigoProveedor, $clave, $fechaAnita, $usuario, $empresaCodigo);
        }

        $this->grabarRecepmov($recepcion, $codigoProveedor, $clave, $fechaAnita, $empresaCodigo);
        $this->actualizarPendmovp($recepcion, 1);
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

        $this->eliminarRecpunica($recepcion, $clave);
        $this->eliminarRecepmov($clave);
        $this->marcarRecepmaeAnulada($codigoProveedor, $clave);
        $this->actualizarPendmovp($recepcion, -1);
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function existeRecepmae(string $codigoProveedor, array $clave): bool
    {
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => config('recepcion_proveedor.anita.sistema_compras'),
            'tabla' => config('recepcion_proveedor.anita.tablas.recepcion_cabecera'),
            'campos' => 'recm_nro',
            'whereArmado' => RecepcionProveedorAnitaWhereSupport::recepmae($codigoProveedor, $clave),
            'limit' => 'FIRST 1',
        ]);

        return ApiAnita::primeraFilaLista($raw) !== null;
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function marcarRecepmaeAnulada(string $codigoProveedor, array $clave): void
    {
        $estadoAnulada = (string) config('recepcion_proveedor.anita.recepcion_estado_anulada', '3');
        $api = new ApiAnita;
        $payload = [
            'acc' => 'update',
            'sistema' => config('recepcion_proveedor.anita.sistema_compras'),
            'tabla' => config('recepcion_proveedor.anita.tablas.recepcion_cabecera'),
            'campos' => [
                'recm_estado' => $estadoAnulada,
                'recm_fe_ult_act' => (int) date('Ymd'),
            ],
            'whereArmado' => RecepcionProveedorAnitaWhereSupport::recepmae($codigoProveedor, $clave),
        ];

        $raw = (string) $api->apiCallEscritura($payload);
        if (stripos($raw, 'error') !== false) {
            Log::warning('RecepcionProveedorAnitaBridge: anular recepmae', ['respuesta' => $raw, 'clave' => $clave]);
        }
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function eliminarRecepmov(array $clave): void
    {
        $api = new ApiAnita;
        $payload = [
            'acc' => 'delete',
            'sistema' => config('recepcion_proveedor.anita.sistema_compras'),
            'tabla' => config('recepcion_proveedor.anita.tablas.recepcion_linea'),
            'whereArmado' => RecepcionProveedorAnitaWhereSupport::recepmovCabecera($clave),
        ];

        $raw = (string) $api->apiCallEscritura($payload);
        if (stripos($raw, 'error') !== false) {
            Log::warning('RecepcionProveedorAnitaBridge: delete recepmov', ['respuesta' => $raw, 'clave' => $clave]);
        }
    }

    private function eliminarRecpunica(Recepcion_Proveedor $recepcion, array $clave): void
    {
        foreach ($recepcion->recepcion_proveedor_partes_unicas as $parte) {
            RecpunicaAnitaBridgeSupport::eliminarDesdeParte($parte, $clave);
        }

        $api = new ApiAnita;
        $payload = [
            'acc' => 'delete',
            'sistema' => config('recepcion_proveedor.anita.sistema_compras'),
            'tabla' => config('recepcion_proveedor.anita.tablas.recepcion_parte_unica'),
            'whereArmado' => RecepcionProveedorAnitaWhereSupport::recpunicaCabecera($clave),
        ];

        $raw = (string) $api->apiCallEscritura($payload);
        if (stripos($raw, 'error') !== false) {
            Log::warning('RecepcionProveedorAnitaBridge: delete recpunica cabecera', ['respuesta' => $raw, 'clave' => $clave]);
        }
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function actualizarRecepmae(
        Recepcion_Proveedor $recepcion,
        string $codigoProveedor,
        array $clave,
        int $fechaAnita,
        string $usuario,
        int $empresaCodigo
    ): void {
        $oc = $recepcion->ordencompras;
        $obs = substr((string) ($recepcion->observacion ?? ''), 0, 40);
        $cfg = config('recepcion_proveedor.anita');
        $estadoConfirmada = (string) ($cfg['recepcion_estado_confirmada'] ?? '2');

        $api = new ApiAnita;
        $payload = [
            'acc' => 'update',
            'sistema' => $cfg['sistema_compras'],
            'tabla' => $cfg['tablas']['recepcion_cabecera'],
            'campos' => [
                'recm_fecha' => $fechaAnita,
                'recm_estado' => $estadoConfirmada,
                'recm_usuario' => $usuario,
                'recm_fe_ult_act' => (int) date('Ymd'),
                'recm_observacion' => $obs,
                'recm_empresa' => $empresaCodigo,
                'recm_com_nro' => (int) ($oc->numeroordencompra ?? 0),
            ],
            'whereArmado' => RecepcionProveedorAnitaWhereSupport::recepmae($codigoProveedor, $clave),
        ];

        $raw = (string) $api->apiCallEscritura($payload);
        if (stripos($raw, 'error') !== false) {
            Log::warning('RecepcionProveedorAnitaBridge: update recepmae', ['respuesta' => $raw]);
        }
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
        $oc = $recepcion->ordencompras;
        $obs = substr((string) ($recepcion->observacion ?? ''), 0, 40);
        $cfg = config('recepcion_proveedor.anita');
        $estadoConfirmada = (string) ($cfg['recepcion_estado_confirmada'] ?? '2');

        $payload = [
            'acc' => 'insert',
            'sistema' => $cfg['sistema_compras'],
            'tabla' => $cfg['tablas']['recepcion_cabecera'],
            'campos' => [
                'recm_proveedor' => $codigoProveedor,
                'recm_tipo' => $clave['tipo'],
                'recm_letra' => $clave['letra'],
                'recm_sucursal' => $clave['sucursal'],
                'recm_nro' => $clave['nro'],
                'recm_fecha' => $fechaAnita,
                'recm_estado' => $estadoConfirmada,
                'recm_usuario' => $usuario,
                'recm_terminal' => 'ERP',
                'recm_fe_ult_act' => (int) date('Ymd'),
                'recm_observacion' => $obs,
                'recm_empresa' => $empresaCodigo,
                'recm_com_tipo' => $cfg['oc_tipo'],
                'recm_com_letra' => $cfg['oc_letra'],
                'recm_com_sucursal' => $cfg['oc_sucursal'],
                'recm_com_nro' => (int) ($oc->numeroordencompra ?? 0),
            ],
        ];

        $raw = (string) $api->apiCallEscritura($payload);
        if (stripos($raw, 'error') !== false) {
            Log::warning('RecepcionProveedorAnitaBridge: recepmae', ['respuesta' => $raw]);
        }
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function grabarRecepmov(
        Recepcion_Proveedor $recepcion,
        string $codigoProveedor,
        array $clave,
        int $fechaAnita,
        int $empresaCodigo
    ): void {
        $api = new ApiAnita;
        $signo = $recepcion->tipo === Recepcion_Proveedor::TIPO_DEVOLUCION ? -1 : 1;
        $cfg = config('recepcion_proveedor.anita');

        foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
            $articulo = $linea->articulos;
            $sku = str_pad(substr((string) ($articulo->sku ?? ''), 0, 13), 13, ' ', STR_PAD_RIGHT);
            $orden = (int) ($linea->penvp_orden ?? $linea->orden);

            $payload = [
                'acc' => 'insert',
                'sistema' => $cfg['sistema_compras'],
                'tabla' => $cfg['tablas']['recepcion_linea'],
                'campos' => [
                    'recv_proveedor' => $codigoProveedor,
                    'recv_tipo' => $clave['tipo'],
                    'recv_letra' => $clave['letra'],
                    'recv_sucursal' => $clave['sucursal'],
                    'recv_nro' => $clave['nro'],
                    'recv_orden' => $orden,
                    'recv_articulo' => $sku,
                    'recv_desc' => substr((string) ($articulo->descripcion ?? ''), 0, 30),
                    'recv_cantidad' => (float) $linea->cantidad * $signo,
                    'recv_precio' => (float) $linea->precio,
                    'recv_dto_art' => (float) ($linea->descuento ?? 0),
                    'recv_deposito' => (int) ($linea->deposito_id ?? 1),
                    'recv_fecha' => $fechaAnita,
                    'recv_incl_impuesto' => 'N',
                    'recv_cod_mon' => $this->codigoMonedaAnita((int) $linea->moneda_id),
                    'recv_ccosto' => (int) optional($linea->centrocostos)->codigo ?? 0,
                    'recv_empresa' => $empresaCodigo,
                    'recv_cotizacion' => (float) ($linea->cotizacion ?? 1),
                ],
            ];

            $raw = (string) $api->apiCallEscritura($payload);
            if (stripos($raw, 'error') !== false) {
                Log::warning('RecepcionProveedorAnitaBridge: recepmov', ['orden' => $orden, 'respuesta' => $raw]);
            }
        }
    }

    private function actualizarPendmovp(Recepcion_Proveedor $recepcion, int $multiplicador): void
    {
        $oc = $recepcion->ordencompras;
        if (! $oc) {
            return;
        }

        $cfg = config('recepcion_proveedor.anita');
        $api = new ApiAnita;
        $signo = $recepcion->tipo === Recepcion_Proveedor::TIPO_DEVOLUCION ? -1 : 1;

        foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
            $articulo = $linea->articulos;
            $sku = trim((string) ($articulo->sku ?? ''));
            $orden = (int) ($linea->penvp_orden ?? $linea->orden);
            $delta = (float) $linea->cantidad * $signo * $multiplicador;

            $where = " WHERE
                penvp_tipo='{$cfg['oc_tipo']}' and
                penvp_letra='{$cfg['oc_letra']}' and
                penvp_sucursal={$cfg['oc_sucursal']} and
                penvp_nro=".(int) $oc->numeroordencompra." and
                penvp_orden={$orden} and
                penvp_articulo='{$sku}'";

            $rows = json_decode($api->apiCall([
                'acc' => 'list',
                'sistema' => $cfg['sistema_compras'],
                'tabla' => $cfg['tablas']['oc_linea'],
                'campos' => 'penvp_cantentr, penvp_cantidad',
                'whereArmado' => $where,
            ]));

            if (! is_array($rows) || count($rows) === 0) {
                continue;
            }

            $actual = (float) ($rows[0]->penvp_cantentr ?? 0);
            $nuevaCant = $actual + $delta;
            $cantidadOc = (float) ($rows[0]->penvp_cantidad ?? 0);
            $estado = $nuevaCant >= $cantidadOc ? 'C' : ($nuevaCant > 0 ? 'P' : 'A');

            $api->apiCallEscritura([
                'acc' => 'update',
                'sistema' => $cfg['sistema_compras'],
                'tabla' => $cfg['tablas']['oc_linea'],
                'campos' => [
                    'penvp_cantentr' => $nuevaCant,
                ],
                'whereArmado' => $where,
            ]);

            $whereCab = " WHERE
                penmp_tipo='{$cfg['oc_tipo']}' and
                penmp_letra='{$cfg['oc_letra']}' and
                penmp_sucursal={$cfg['oc_sucursal']} and
                penmp_nro=".(int) $oc->numeroordencompra;

            $api->apiCallEscritura([
                'acc' => 'update',
                'sistema' => $cfg['sistema_compras'],
                'tabla' => $cfg['tablas']['oc_cabecera'],
                'campos' => ['penmp_estado' => $estado],
                'whereArmado' => $whereCab,
            ]);
        }
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
