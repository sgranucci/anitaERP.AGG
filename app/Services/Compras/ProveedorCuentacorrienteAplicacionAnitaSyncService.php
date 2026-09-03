<?php

namespace App\Services\Compras;

use App\ApiAnita;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportClaveSupport;
use App\Support\Compras\AnitaSync\AplicacionCuentacorriente\AplicacionCuentacorrienteAnitaLadoSupport;
use App\Support\Compras\AnitaSync\AplicacionCuentacorriente\AplmovpAnitaMapper;
use App\Support\Compras\AnitaSync\AplicacionCuentacorriente\PromovPagadoAnitaMapper;
use App\Support\Compras\AnitaSync\AplicacionCuentacorriente\PromovPagoAnitaMapper;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Espejo ERP → Anita de una aplicación de CC: aplmovp + promov.prov_t_pagado.
 *
 * @phpstan-import-type Lado from AplicacionCuentacorrienteAnitaLadoSupport
 */
class ProveedorCuentacorrienteAplicacionAnitaSyncService
{
    private const RELACIONES_CC = [
        'proveedores',
        'empresas',
        'comprobante_proveedores.tipotransaccion_compras',
        'comprobante_proveedor_cuotas',
        'pagoproveedores',
        'monedas',
        'comprobante_proveedores.monedas',
    ];

    public function syncPorIdsAplicacion(array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return;
        }

        $aplicaciones = Proveedor_Cuentacorriente_Aplicacion::query()
            ->whereIn('id', $ids)
            ->where('total', '<', 0)
            ->get();

        foreach ($aplicaciones as $apl) {
            $this->syncAplicar($apl);
        }
    }

    public function syncPorPagoproveedor(int $pagoproveedorId): void
    {
        if ($pagoproveedorId <= 0) {
            return;
        }

        $ids = Proveedor_Cuentacorriente_Aplicacion::query()
            ->where('pagoproveedor_id', $pagoproveedorId)
            ->where('total', '<', 0)
            ->pluck('id')
            ->all();

        $this->syncPorIdsAplicacion($ids);

        $ccs = Proveedor_Cuentacorriente::query()
            ->with(self::RELACIONES_CC)
            ->where('pagoproveedor_id', $pagoproveedorId)
            ->get();
        foreach ($ccs as $cc) {
            $lado = AplicacionCuentacorrienteAnitaLadoSupport::desdeCc($cc);
            if ($lado === null || ! $this->esTipoPagoAnita($lado['tipo'])) {
                continue;
            }
            $this->actualizarPromov($cc, $lado, $this->ultimaRef($cc), false);
        }
    }

    public function syncAplicar(Proveedor_Cuentacorriente_Aplicacion $apl): void
    {
        $par = $this->resolverPar($apl);
        $this->insertarAplmovpSiFalta($par['deuda'], $par['credito'], $par['fecha_ymd'], $par['monto']);
        $this->actualizarPromov($par['deuda_cc'], $par['deuda'], $par['credito'], true);
        $this->actualizarPromov($par['credito_cc'], $par['credito'], $par['deuda'], false);
    }

    /**
     * @param  array{
     *   deuda: Lado,
     *   credito: Lado,
     *   fecha_ymd: string,
     *   monto: float,
     *   deuda_cc_id: int,
     *   credito_cc_id: int
     * }  $snapshot
     */
    public function revertir(array $snapshot): void
    {
        $this->borrarAplmovp(
            $snapshot['deuda'],
            $snapshot['credito'],
            $snapshot['fecha_ymd'],
            (float) $snapshot['monto']
        );

        $deudaCc = $this->cargarCc((int) $snapshot['deuda_cc_id']);
        $creditoCc = $this->cargarCc((int) $snapshot['credito_cc_id']);
        if ($deudaCc) {
            $this->actualizarPromov($deudaCc, $snapshot['deuda'], $this->ultimaRef($deudaCc), true);
        }
        if ($creditoCc) {
            $this->actualizarPromov($creditoCc, $snapshot['credito'], $this->ultimaRef($creditoCc), false);
        }
    }

    /**
     * @return array{
     *   deuda: Lado,
     *   credito: Lado,
     *   fecha_ymd: string,
     *   monto: float,
     *   deuda_cc_id: int,
     *   credito_cc_id: int
     * }
     */
    public function snapshotDesdeAplicacion(Proveedor_Cuentacorriente_Aplicacion $apl): array
    {
        $par = $this->resolverPar($apl);

        return [
            'deuda' => $par['deuda'],
            'credito' => $par['credito'],
            'fecha_ymd' => $par['fecha_ymd'],
            'monto' => $par['monto'],
            'deuda_cc_id' => (int) $par['deuda_cc']->id,
            'credito_cc_id' => (int) $par['credito_cc']->id,
        ];
    }

    /**
     * @return array{deuda: Lado, credito: Lado, fecha_ymd: string, monto: float, deuda_cc: Proveedor_Cuentacorriente, credito_cc: Proveedor_Cuentacorriente}
     */
    private function resolverPar(Proveedor_Cuentacorriente_Aplicacion $apl): array
    {
        $propia = $this->cargarCc((int) $apl->proveedor_cuentacorriente_id);
        $otra = $this->cargarCc((int) ($apl->proveedor_cuentacorriente_aplicado_id ?? 0));
        if ($propia === null || $otra === null) {
            throw new RuntimeException('Aplicación #'.$apl->id.' sin movimientos de CC para sincronizar a Anita.');
        }

        $ladoPropio = AplicacionCuentacorrienteAnitaLadoSupport::desdeCc($propia);
        $ladoOtro = AplicacionCuentacorrienteAnitaLadoSupport::desdeCc($otra);
        if ($ladoPropio === null || $ladoOtro === null) {
            throw new RuntimeException(
                'No se pudo armar la clave Anita de '.$this->etiquetaCc($propia).' / '.$this->etiquetaCc($otra).'.'
            );
        }

        $esDeuda = (float) $apl->total < 0;
        $deudaCc = $esDeuda ? $propia : $otra;
        $creditoCc = $esDeuda ? $otra : $propia;
        $deuda = $esDeuda ? $ladoPropio : $ladoOtro;
        $credito = $esDeuda ? $ladoOtro : $ladoPropio;

        $fecha = $apl->fecha?->format('Y-m-d') ?? '';
        $fechaYmd = (string) ComprobanteProveedorAnitaImportClaveSupport::fechaAnitaDesdeIso($fecha);
        if ($fechaYmd === '0' || $fechaYmd === '') {
            throw new RuntimeException('Aplicación #'.$apl->id.' sin fecha para aplmovp.');
        }

        return [
            'deuda' => $deuda,
            'credito' => $credito,
            'fecha_ymd' => $fechaYmd,
            'monto' => round(abs((float) $apl->total), 4),
            'deuda_cc' => $deudaCc,
            'credito_cc' => $creditoCc,
        ];
    }

    /**
     * @param  Lado  $deuda
     * @param  Lado  $credito
     */
    private function insertarAplmovpSiFalta(array $deuda, array $credito, string $fechaYmd, float $monto): void
    {
        $api = new ApiAnita;
        $tabla = (string) config('comprobante_proveedor.anita_tabla_aplmovp', 'aplmovp');
        $sistema = (string) config('comprobante_proveedor.anita_sistema_compras', 'compras');
        $etiqueta = $deuda['etiqueta'].' ← '.$credito['etiqueta'];

        if ($this->existeAplmovp($deuda, $credito, $fechaYmd, $monto)) {
            $api->apiCallEscritura([
                'acc' => 'update',
                'tabla' => $tabla,
                'sistema' => $sistema,
                'valores' => AplmovpAnitaMapper::valoresUpdate($deuda, $credito),
                'whereArmado' => AplmovpAnitaMapper::whereFila($deuda, $credito, $fechaYmd, $monto),
            ], 'aplmovp update aplicación CC '.$etiqueta);

            return;
        }

        $api->apiCallEscritura([
            'acc' => 'insert',
            'tabla' => $tabla,
            'sistema' => $sistema,
            'campos' => AplmovpAnitaMapper::camposInsert(),
            'valores' => AplmovpAnitaMapper::valoresInsert($deuda, $credito, $fechaYmd, $monto),
        ], 'aplmovp insert aplicación CC '.$etiqueta);
    }

    /**
     * @param  Lado  $deuda
     * @param  Lado  $credito
     */
    private function existeAplmovp(array $deuda, array $credito, string $fechaYmd, float $monto): bool
    {
        $api = new ApiAnita;
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => (string) config('comprobante_proveedor.anita_sistema_compras', 'compras'),
            'tabla' => (string) config('comprobante_proveedor.anita_tabla_aplmovp', 'aplmovp'),
            'campos' => 'aplvp_monto',
            'whereArmado' => AplmovpAnitaMapper::wherePar($deuda, $credito, $fechaYmd),
        ]));
        if ($parsed['error_lectura'] !== null) {
            throw new RuntimeException('Anita aplmovp: '.$parsed['error_lectura']);
        }
        foreach ($parsed['filas'] as $fila) {
            $a = (array) $fila;
            if (abs((float) ($a['aplvp_monto'] ?? 0) - $monto) < 0.01) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Lado  $deuda
     * @param  Lado  $credito
     */
    private function borrarAplmovp(array $deuda, array $credito, string $fechaYmd, float $monto): void
    {
        if (! $this->existeAplmovp($deuda, $credito, $fechaYmd, $monto)) {
            return;
        }

        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'delete',
            'tabla' => (string) config('comprobante_proveedor.anita_tabla_aplmovp', 'aplmovp'),
            'sistema' => (string) config('comprobante_proveedor.anita_sistema_compras', 'compras'),
            'whereArmado' => AplmovpAnitaMapper::whereFila($deuda, $credito, $fechaYmd, $monto),
        ], 'aplmovp delete aplicación CC '.$deuda['etiqueta'].' ← '.$credito['etiqueta']);
    }

    /**
     * @param  Lado  $lado
     * @param  Lado|null  $ref
     */
    private function actualizarPromov(
        Proveedor_Cuentacorriente $cc,
        array $lado,
        ?array $ref,
        bool $obligatorio,
    ): void {
        $suma = (float) Proveedor_Cuentacorriente_Aplicacion::query()
            ->where('proveedor_cuentacorriente_id', $cc->id)
            ->sum('total');
        $tPagado = AplicacionCuentacorrienteAnitaLadoSupport::tPagadoDesdeSumaAplicaciones($suma);
        $ultimaFecha = Proveedor_Cuentacorriente_Aplicacion::query()
            ->where('proveedor_cuentacorriente_id', $cc->id)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->value('fecha');
        $fechaYmd = $ultimaFecha
            ? (string) ComprobanteProveedorAnitaImportClaveSupport::fechaAnitaDesdeIso((string) $ultimaFecha)
            : '0';
        if ($tPagado < 0.0001) {
            $ref = null;
            $fechaYmd = '0';
        }

        $api = new ApiAnita;
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => (string) config('comprobante_proveedor.anita_sistema_compras', 'compras'),
            'tabla' => 'promov',
            'campos' => 'prov_t_pagado, prov_nro_cuota',
            'whereArmado' => PromovPagadoAnitaMapper::whereCuota($lado),
        ]));
        if ($parsed['error_lectura'] !== null) {
            $error = 'Anita promov '.$lado['etiqueta'].': '.$parsed['error_lectura'];
            if ($obligatorio) {
                throw new RuntimeException($error);
            }
            Log::warning('anita_bridge.fallo', ['contexto' => 'promov list '.$lado['etiqueta'], 'mensaje' => $error]);

            return;
        }
        if ($parsed['filas'] === []) {
            if ($this->esTipoPagoAnita((string) $lado['tipo'])) {
                $this->insertarPromovPago($cc, $lado);
                $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
                    'acc' => 'list',
                    'sistema' => (string) config('comprobante_proveedor.anita_sistema_compras', 'compras'),
                    'tabla' => 'promov',
                    'campos' => 'prov_t_pagado, prov_nro_cuota',
                    'whereArmado' => PromovPagadoAnitaMapper::whereCuota($lado),
                ]));
            }
        }
        if ($parsed['filas'] === []) {
            $error = 'No está en promov Anita: '.$lado['etiqueta'];
            if ($obligatorio) {
                throw new RuntimeException($error);
            }
            Log::warning('anita_bridge.fallo', ['contexto' => 'promov ausente '.$lado['etiqueta'], 'mensaje' => $error]);

            return;
        }

        try {
            $api->apiCallEscritura([
                'acc' => 'update',
                'tabla' => 'promov',
                'sistema' => (string) config('comprobante_proveedor.anita_sistema_compras', 'compras'),
                'valores' => PromovPagadoAnitaMapper::valoresUpdate($tPagado, $fechaYmd, $ref),
                'whereArmado' => PromovPagadoAnitaMapper::whereCuota($lado),
            ], 'promov t_pagado '.$lado['etiqueta']);
        } catch (RuntimeException $e) {
            if ($obligatorio) {
                throw new RuntimeException(
                    'No se actualizó promov de '.$lado['etiqueta'].': '.$e->getMessage()
                );
            }
            Log::warning('anita_bridge.fallo', [
                'contexto' => 'promov t_pagado opcional '.$lado['etiqueta'],
                'mensaje' => $e->getMessage(),
            ]);
        }
    }

    private function esTipoPagoAnita(string $tipo): bool
    {
        return in_array(strtoupper(substr(trim($tipo), 0, 3)), ['OPP', 'OPA', 'OPV'], true);
    }

    /**
     * @param  Lado  $lado
     */
    private function insertarPromovPago(Proveedor_Cuentacorriente $cc, array $lado): void
    {
        $fechaYmd = (string) ComprobanteProveedorAnitaImportClaveSupport::fechaAnitaDesdeIso(
            $cc->fecha?->format('Y-m-d') ?? ''
        );
        if ($fechaYmd === '' || $fechaYmd === '0') {
            $fechaYmd = date('Ymd');
        }

        (new ApiAnita)->apiCallEscritura([
            'acc' => 'insert',
            'tabla' => 'promov',
            'sistema' => (string) config('comprobante_proveedor.anita_sistema_compras', 'compras'),
            'campos' => PromovPagoAnitaMapper::camposInsert(),
            'valores' => PromovPagoAnitaMapper::valoresInsert($lado, abs((float) $cc->total), $fechaYmd),
        ], 'promov insert OP '.$lado['etiqueta']);
    }

    /**
     * @return Lado|null
     */
    private function ultimaRef(Proveedor_Cuentacorriente $cc): ?array
    {
        $cc->loadMissing(self::RELACIONES_CC);
        $apl = Proveedor_Cuentacorriente_Aplicacion::query()
            ->where('proveedor_cuentacorriente_id', $cc->id)
            ->whereNotNull('proveedor_cuentacorriente_aplicado_id')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->first();
        if ($apl === null) {
            return null;
        }
        $otro = $this->cargarCc((int) $apl->proveedor_cuentacorriente_aplicado_id);
        if ($otro === null) {
            return null;
        }

        return AplicacionCuentacorrienteAnitaLadoSupport::desdeCc($otro);
    }

    private function cargarCc(int $id): ?Proveedor_Cuentacorriente
    {
        if ($id <= 0) {
            return null;
        }

        return Proveedor_Cuentacorriente::query()->with(self::RELACIONES_CC)->find($id);
    }

    private function etiquetaCc(Proveedor_Cuentacorriente $cc): string
    {
        $lado = AplicacionCuentacorrienteAnitaLadoSupport::desdeCc($cc);

        return $lado['etiqueta'] ?? ('CC#'.$cc->id);
    }
}
