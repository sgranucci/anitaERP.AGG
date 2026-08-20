<?php

namespace App\Services\Compras;

use App\ApiAnita;
use App\Models\Compras\Comprobante_Proveedor;
use App\Support\Compras\AnitaSync\ComprobanteProveedor\ComprobanteProveedorAnitaContext;
use App\Support\Compras\AnitaSync\ComprobanteProveedor\ComprobanteProveedorAnitaNroInternoSupport;
use App\Support\Compras\AnitaSync\ComprobanteProveedor\ComprobanteProveedorConcmovPertenenciaSupport;
use App\Support\Compras\AnitaSync\ComprobanteProveedor\ConcmovLineaAnitaMapper;
use App\Support\Compras\ComprobanteProveedorAnitaSyncEstado;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Reasigna nro. interno Anita de facturas ERP que chocaron con otra compra.
 * No borra compra/promov ajenos. En concmov solo quita líneas que coinciden
 * con conceptos+importe de la factura ERP.
 */
class ComprobanteProveedorAnitaNroInternoColisionRepararService
{
    private const SISTEMA_COMPRAS = 'compras';

    public function __construct(
        private ComprobanteProveedorAnitaNroInternoSupport $nroInternoSupport,
    ) {}

    /**
     * @return array{
     *     candidatas: int,
     *     reparadas: int,
     *     omitidas: int,
     *     errores: int,
     *     detalle: list<array<string, mixed>>
     * }
     */
    public function ejecutar(bool $dryRun, ?int $comprobanteId = null): array
    {
        $query = Comprobante_Proveedor::query()
            ->with([
                'comprobante_proveedor_conceptos.concepto_ivacompras',
                'comprobante_proveedor_cuotas',
                'empresas',
                'proveedores',
                'tipotransaccion_compras',
                'monedas',
                'condicionpagos',
                'ordencompras',
            ])
            ->where('anita_sync_estado', ComprobanteProveedorAnitaSyncEstado::SYNC_OK)
            ->whereNotNull('anita_nro_interno')
            ->where('anita_nro_interno', '>', 0)
            ->orderBy('anita_nro_interno')
            ->orderBy('id');

        if ($comprobanteId) {
            $query->whereKey($comprobanteId);
        }

        $stats = [
            'candidatas' => 0,
            'reparadas' => 0,
            'omitidas' => 0,
            'errores' => 0,
            'detalle' => [],
        ];

        foreach ($query->get() as $comprobante) {
            $plan = $this->planificar($comprobante);
            $stats['candidatas']++;
            $stats['detalle'][] = $plan;

            if ($plan['accion'] === 'omitir') {
                $stats['omitidas']++;

                continue;
            }
            if ($plan['accion'] === 'error') {
                $stats['errores']++;

                continue;
            }
            if ($dryRun) {
                continue;
            }

            try {
                $this->aplicar($comprobante, $plan);
                $stats['reparadas']++;
                $stats['detalle'][count($stats['detalle']) - 1]['aplicado'] = true;
                $stats['detalle'][count($stats['detalle']) - 1]['nuevo_interno'] = (int) $comprobante->fresh()->anita_nro_interno;
            } catch (\Throwable $e) {
                $stats['errores']++;
                $stats['detalle'][count($stats['detalle']) - 1]['accion'] = 'error';
                $stats['detalle'][count($stats['detalle']) - 1]['motivo'] = $e->getMessage();
                Log::error('comprobante_proveedor.nro_interno_colision.fallo', [
                    'comprobante_id' => $comprobante->id,
                    'interno_viejo' => $plan['interno_viejo'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * @return array<string, mixed>
     */
    public function planificar(Comprobante_Proveedor $comprobante): array
    {
        $interno = (int) $comprobante->anita_nro_interno;
        $etiqueta = $this->etiquetaErp($comprobante);
        $base = [
            'comprobante_id' => $comprobante->id,
            'etiqueta' => $etiqueta,
            'interno_viejo' => $interno,
            'accion' => 'omitir',
            'motivo' => '',
            'otras' => [],
            'concmov_erp' => [],
            'concmov_otras' => [],
            'aplicado' => false,
            'nuevo_interno' => null,
        ];

        $compras = $this->listarCompraPorInterno($interno);
        if (count($compras) <= 1) {
            $base['motivo'] = 'sin colisión en compra';

            return $base;
        }

        $ctx = new ComprobanteProveedorAnitaContext($comprobante, $interno);
        $erpClave = [
            'proveedor' => $ctx->proveedorCodigo(),
            'tipo' => $ctx->tipoComprobante(),
            'letra' => $ctx->letra(),
            'sucursal' => $ctx->sucursal(),
            'nro' => $ctx->numero(),
        ];

        $encontradaErp = false;
        $otras = [];
        foreach ($compras as $compra) {
            if ($this->compraEsErp($compra, $erpClave)) {
                $encontradaErp = true;

                continue;
            }
            $otras[] = sprintf(
                '%s %s %s-%s %s',
                $compra['com_tipo'] ?? '',
                $compra['com_letra'] ?? '',
                $compra['com_sucursal'] ?? '',
                $compra['com_nro'] ?? '',
                $compra['com_nombre_prov'] ?? ''
            );
        }

        $base['otras'] = $otras;
        if (! $encontradaErp) {
            $base['accion'] = 'error';
            $base['motivo'] = 'no está la fila compra del ERP en ese interno';

            return $base;
        }

        $lineasErp = $this->lineasErp($comprobante);
        $lineasConcmov = $this->listarConcmov($interno);
        $part = ComprobanteProveedorConcmovPertenenciaSupport::particionar($lineasErp, $lineasConcmov);
        if (! $part['ok']) {
            $base['accion'] = 'error';
            $base['motivo'] = $part['error'];

            return $base;
        }

        $base['accion'] = 'reasignar';
        $base['motivo'] = 'colisión con '.count($otras).' compra(s)';
        $base['concmov_erp'] = $part['de_erp'];
        $base['concmov_otras'] = $part['de_otras'];
        $base['erp_sin_concmov'] = $part['erp_sin_concmov'];

        return $base;
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function aplicar(Comprobante_Proveedor $comprobante, array $plan): void
    {
        $viejo = (int) $plan['interno_viejo'];
        $nuevo = $this->nroInternoSupport->siguiente();
        if ($this->listarCompraPorInterno($nuevo) !== []) {
            throw new RuntimeException('El interno nuevo '.$nuevo.' ya existe en compra.');
        }

        $ctxNuevo = new ComprobanteProveedorAnitaContext($comprobante, $nuevo);
        $this->insertarConcmovErp($ctxNuevo);

        $this->apiUpdate(
            'compra',
            "com_nro_interno = '".$nuevo."'",
            $this->whereCompraErp($comprobante, $viejo)
        );
        $this->apiUpdate(
            'promov',
            "prov_nro_interno = '".$nuevo."'",
            $this->wherePromovErp($comprobante, $viejo)
        );

        foreach ($plan['concmov_erp'] as $linea) {
            $this->apiDelete(
                'concmov',
                ComprobanteProveedorConcmovPertenenciaSupport::whereBorrarLinea(
                    $viejo,
                    (int) $linea['concepto'],
                    (float) $linea['importe']
                )
            );
        }

        $comprobante->forceFill([
            'anita_nro_interno' => $nuevo,
            'anita_sync_at' => now(),
        ])->save();

        $this->assertReparacion($viejo, $nuevo, $plan);

        Log::info('comprobante_proveedor.nro_interno_colision.reparado', [
            'comprobante_id' => $comprobante->id,
            'interno_viejo' => $viejo,
            'interno_nuevo' => $nuevo,
            'otras' => $plan['otras'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function assertReparacion(int $viejo, int $nuevo, array $plan): void
        $comprasNuevo = $this->listarCompraPorInterno($nuevo);
        if (count($comprasNuevo) !== 1) {
            throw new RuntimeException(
                'Tras reasignar, el interno nuevo '.$nuevo.' tiene '.count($comprasNuevo).' compra(s).'
            );
        }
        $comprasViejo = $this->listarCompraPorInterno($viejo);
        if ($comprasViejo === []) {
            throw new RuntimeException(
                'Tras reasignar, desapareció la otra compra del interno viejo '.$viejo.'.'
            );
        }

        $concmovViejo = $this->listarConcmov($viejo);
        $esperadasOtras = count($plan['concmov_otras']);
        if (count($concmovViejo) !== $esperadasOtras) {
            throw new RuntimeException(
                'concmov del interno viejo '.$viejo.' quedó con '.count($concmovViejo)
                .' líneas (se esperaban '.$esperadasOtras.' de la otra factura).'
            );
        }
    }

    private function insertarConcmovErp(ComprobanteProveedorAnitaContext $ctx): void
    {
        $api = new ApiAnita;
        $orden = 1;
        foreach ($ctx->comprobante->comprobante_proveedor_conceptos as $linea) {
            $api->apiCallEscritura([
                'acc' => 'insert',
                'tabla' => 'concmov',
                'sistema' => self::SISTEMA_COMPRAS,
                'campos' => ConcmovLineaAnitaMapper::camposInsert(),
                'valores' => ConcmovLineaAnitaMapper::valoresInsert($ctx, $linea, $orden),
            ], 'concmov insert reparación nro interno');
            $orden++;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarCompraPorInterno(int $nroInterno): array
    {
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => self::SISTEMA_COMPRAS,
            'tabla' => 'compra',
            'campos' => 'com_proveedor,com_tipo,com_letra,com_sucursal,com_nro,com_nro_interno,com_nombre_prov,com_monto',
            'whereArmado' => ' WHERE com_nro_interno = '.(int) $nroInterno,
        ]);

        $out = [];
        foreach (ApiAnita::decodificarListaFilas($raw) as $fila) {
            $out[] = (array) $fila;
        }

        return $out;
    }

    /**
     * @return list<array{concepto: int, importe: float}>
     */
    private function listarConcmov(int $nroInterno): array
    {
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => self::SISTEMA_COMPRAS,
            'tabla' => 'concmov',
            'campos' => 'concv_nro_interno, concv_concepto, concv_importe',
            'whereArmado' => ' WHERE concv_nro_interno = '.(int) $nroInterno,
            'orderBy' => 'concv_concepto',
        ]);

        $out = [];
        foreach (ApiAnita::decodificarListaFilas($raw) as $fila) {
            $a = (array) $fila;
            $out[] = [
                'concepto' => (int) ($a['concv_concepto'] ?? 0),
                'importe' => (float) ($a['concv_importe'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{concepto: int, importe: float}>
     */
    private function lineasErp(Comprobante_Proveedor $comprobante): array
    {
        $out = [];
        foreach ($comprobante->comprobante_proveedor_conceptos as $linea) {
            $out[] = [
                'concepto' => (int) ($linea->concepto_ivacompras?->codigo ?? 0),
                'importe' => (float) $linea->monto,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $compra
     * @param  array{proveedor: string, tipo: string, letra: string, sucursal: int, nro: int}  $erpClave
     */
    private function compraEsErp(array $compra, array $erpClave): bool
    {
        $prov = str_pad(trim((string) ($compra['com_proveedor'] ?? '')), 6, '0', STR_PAD_LEFT);

        return $prov === $erpClave['proveedor']
            && strtoupper(substr(trim((string) ($compra['com_tipo'] ?? '')), 0, 3)) === $erpClave['tipo']
            && strtoupper(substr(trim((string) ($compra['com_letra'] ?? '')), 0, 1)) === $erpClave['letra']
            && (int) ($compra['com_sucursal'] ?? 0) === $erpClave['sucursal']
            && (int) ($compra['com_nro'] ?? 0) === $erpClave['nro'];
    }

    private function whereCompraErp(Comprobante_Proveedor $comprobante, int $nroInterno): string
    {
        $ctx = new ComprobanteProveedorAnitaContext($comprobante, $nroInterno);

        return $ctx->claveWhereCompra();
    }

    private function wherePromovErp(Comprobante_Proveedor $comprobante, int $nroInterno): string
    {
        $ctx = new ComprobanteProveedorAnitaContext($comprobante, $nroInterno);

        return $ctx->claveWherePromov();
    }

    private function etiquetaErp(Comprobante_Proveedor $comprobante): string
    {
        $tipo = (string) ($comprobante->tipotransaccion_compras?->abreviatura ?? '');

        return trim($tipo.' '.$comprobante->letra.' '.$comprobante->sucursal.'-'.$comprobante->numerocomprobante);
    }

    private function apiUpdate(string $tabla, string $valores, string $whereArmado): void
    {
        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'update',
            'tabla' => $tabla,
            'sistema' => self::SISTEMA_COMPRAS,
            'valores' => $valores,
            'whereArmado' => $whereArmado,
        ], $tabla.' update reparación nro interno');
        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new RuntimeException($tabla.' update: '.$err);
        }
    }

    private function apiDelete(string $tabla, string $whereArmado): void
    {
        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'delete',
            'tabla' => $tabla,
            'sistema' => self::SISTEMA_COMPRAS,
            'whereArmado' => $whereArmado,
        ], $tabla.' delete reparación nro interno');
        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new RuntimeException($tabla.' delete: '.$err);
        }
    }
}
