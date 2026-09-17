<?php

namespace App\Services\Ventas;

use App\ApiAnita;
use App\Models\Caja\Cobranza;
use App\Models\Ventas\Cliente_Cuentacorriente;
use App\Models\Ventas\Cliente_Cuentacorriente_Aplicacion;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportClaveSupport;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportFormatoSupport;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Espejo ERP → Anita de una aplicación CC cliente: aplmov + climov.cliv_t_cobrado.
 *
 * @phpstan-type Lado array{
 *   tipo: string,
 *   letra: string,
 *   sucursal: int,
 *   numero: int,
 *   nro_cuota: int,
 *   etiqueta: string,
 *   empresa: int,
 *   cod_mon: string,
 *   cotizacion: float
 * }
 */
class ClienteCuentacorrienteAplicacionAnitaSyncService
{
    private const RELACIONES_CC = [
        'clientes',
        'empresas',
        'ventas.tipotransacciones',
        'cobranzas.tipotransaccioncajas',
        'monedas',
    ];

    public function syncPorIdsAplicacion(array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return;
        }

        $aplicaciones = Cliente_Cuentacorriente_Aplicacion::query()
            ->whereIn('id', $ids)
            ->where('total', '<', 0)
            ->get();

        foreach ($aplicaciones as $apl) {
            $this->syncAplicar($apl);
        }
    }

    public function syncAplicar(Cliente_Cuentacorriente_Aplicacion $apl): void
    {
        $par = $this->resolverPar($apl);
        $this->insertarAplmovSiFalta($par['deuda'], $par['credito'], $par['fecha_ymd'], $par['monto']);
        // Absoluto desde ERP (evita doble +monto si se reintenta y alinea deuda + crédito/NC).
        $this->recalcularClimovDesdeErp((int) $par['deuda_cc']->id, $par['deuda'], true);
        $this->recalcularClimovDesdeErp((int) $par['credito_cc']->id, $par['credito'], false);
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
        $this->borrarAplmov(
            $snapshot['deuda'],
            $snapshot['credito'],
            $snapshot['fecha_ymd'],
            (float) $snapshot['monto']
        );

        $this->recalcularClimovDesdeErp((int) $snapshot['deuda_cc_id'], $snapshot['deuda'], true);
        $this->recalcularClimovDesdeErp((int) $snapshot['credito_cc_id'], $snapshot['credito'], false);
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
    public function snapshotDesdeAplicacion(Cliente_Cuentacorriente_Aplicacion $apl): array
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
     * @return array{deuda: Lado, credito: Lado, fecha_ymd: string, monto: float, deuda_cc: Cliente_Cuentacorriente, credito_cc: Cliente_Cuentacorriente}
     */
    private function resolverPar(Cliente_Cuentacorriente_Aplicacion $apl): array
    {
        $propia = $this->cargarCc((int) $apl->cliente_cuentacorriente_id);
        $otra = $this->cargarCc((int) ($apl->cliente_cuentacorriente_aplicado_id ?? 0));
        if ($propia === null || $otra === null) {
            throw new RuntimeException('Aplicación #'.$apl->id.' sin movimientos de CC para sincronizar a Anita.');
        }

        $ladoPropio = $this->ladoDesdeCc($propia);
        $ladoOtro = $this->ladoDesdeCc($otra);
        if ($ladoPropio === null || $ladoOtro === null) {
            $msg = 'No se pudo armar la clave Anita de '.$this->etiquetaCc($propia).' / '.$this->etiquetaCc($otra).'.';
            Log::warning('anita_bridge.fallo', [
                'contexto' => 'aplicacion_cc_cliente clave',
                'aplicacion_id' => $apl->id,
                'mensaje' => $msg,
            ]);
            throw new RuntimeException($msg);
        }

        $esDeuda = (float) $apl->total < 0;
        $deudaCc = $esDeuda ? $propia : $otra;
        $creditoCc = $esDeuda ? $otra : $propia;
        $deuda = $esDeuda ? $ladoPropio : $ladoOtro;
        $credito = $esDeuda ? $ladoOtro : $ladoPropio;

        $fecha = $apl->fecha?->format('Y-m-d') ?? '';
        $fechaYmd = (string) ClienteCuentacorrienteAnitaImportClaveSupport::fechaAnitaDesdeIso($fecha);
        if ($fechaYmd === '0' || $fechaYmd === '') {
            throw new RuntimeException('Aplicación #'.$apl->id.' sin fecha para aplmov.');
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
     * @return Lado|null
     */
    private function ladoDesdeCc(Cliente_Cuentacorriente $cc): ?array
    {
        $cc->loadMissing(self::RELACIONES_CC);

        $empresa = 0;
        $perfil = ClienteCuentacorrienteAnitaImportFormatoSupport::perfil();
        if ($perfil['climov_tiene_empresa']) {
            $empresa = SicoreEmpresaAnitaSupport::codigoEmpresaAnita((int) ($cc->empresa_id ?? 0));
            if ($empresa <= 0) {
                $empresa = (int) ($cc->empresas?->codigo ?? $cc->empresa_id ?? 0);
            }
        }

        $codMon = trim((string) ($cc->monedas?->codigo ?? ''));
        if ($codMon === '') {
            $codMon = (string) ((int) ($cc->moneda_id ?? 1) ?: 1);
        }
        $cotizacion = (float) ($cc->cotizacion ?? 1);
        if ($cotizacion <= 0) {
            $cotizacion = 1.0;
        }

        if ((int) ($cc->venta_id ?? 0) > 0 && $cc->ventas) {
            $partes = $this->partesDesdeCodigoVenta((string) ($cc->ventas->codigo ?? ''));
            if ($partes === null) {
                return null;
            }

            return $this->armarLado(
                $partes['tipo'],
                $partes['letra'],
                $partes['sucursal'],
                $partes['numero'],
                $empresa,
                $codMon,
                $cotizacion,
            );
        }

        if ((int) ($cc->cobranza_id ?? 0) > 0 && $cc->cobranzas) {
            $partes = $this->partesDesdeCobranza($cc->cobranzas);

            return $this->armarLado(
                $partes['tipo'],
                $partes['letra'],
                $partes['sucursal'],
                $partes['numero'],
                $empresa,
                $codMon,
                $cotizacion,
            );
        }

        return null;
    }

    /**
     * @return array{tipo: string, letra: string, sucursal: int, numero: int}|null
     */
    private function partesDesdeCodigoVenta(string $codigo): ?array
    {
        $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDesdeCodigoVenta($codigo);
        if ($clave === null) {
            return null;
        }
        $partes = explode('|', $clave);
        if (count($partes) < 4) {
            return null;
        }

        return [
            'tipo' => ClienteCuentacorrienteAnitaImportClaveSupport::tipo($partes[0]),
            'letra' => ClienteCuentacorrienteAnitaImportClaveSupport::letra($partes[1]),
            'sucursal' => (int) $partes[2],
            'numero' => (int) $partes[3],
        ];
    }

    /**
     * @return array{tipo: string, letra: string, sucursal: int, numero: int}
     */
    private function partesDesdeCobranza(Cobranza $cobranza): array
    {
        $cobranza->loadMissing(['tipotransaccioncajas']);
        $tipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo(
            (string) ($cobranza->tipotransaccioncajas?->abreviatura ?? 'COB')
        );
        if ($tipo === '') {
            $tipo = 'COB';
        }

        $numRaw = trim((string) ($cobranza->numerotransaccion ?? ''));
        if (preg_match('/(\d+)\s*[-–]\s*(\d+)/', $numRaw, $m)) {
            return [
                'tipo' => $tipo,
                'letra' => 'X',
                'sucursal' => (int) $m[1],
                'numero' => (int) $m[2],
            ];
        }

        return [
            'tipo' => $tipo,
            'letra' => 'X',
            'sucursal' => 0,
            'numero' => (int) preg_replace('/\D/', '', $numRaw),
        ];
    }

    /**
     * @return Lado
     */
    private function armarLado(
        string $tipo,
        string $letra,
        int $sucursal,
        int $numero,
        int $empresa,
        string $codMon,
        float $cotizacion,
        int $nroCuota = 1,
    ): array {
        $tipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo($tipo);
        $letra = ClienteCuentacorrienteAnitaImportClaveSupport::letra($letra);

        return [
            'tipo' => $tipo,
            'letra' => $letra,
            'sucursal' => $sucursal,
            'numero' => $numero,
            'nro_cuota' => $nroCuota < 0 ? 1 : $nroCuota,
            'etiqueta' => ClienteCuentacorrienteAnitaImportClaveSupport::etiqueta($tipo, $letra, $sucursal, $numero),
            'empresa' => $empresa,
            'cod_mon' => $codMon,
            'cotizacion' => $cotizacion,
        ];
    }

    /**
     * @param  Lado  $deuda
     * @param  Lado  $credito
     */
    private function insertarAplmovSiFalta(array $deuda, array $credito, string $fechaYmd, float $monto): void
    {
        $perfil = ClienteCuentacorrienteAnitaImportFormatoSupport::perfil();
        $sistema = $perfil['sistema'];
        $tabla = $perfil['tabla_aplmov'];
        $etiqueta = $deuda['etiqueta'].' ← '.$credito['etiqueta'];
        $api = new ApiAnita;

        if ($this->existeAplmov($deuda, $credito, $fechaYmd, $monto)) {
            return;
        }

        $api->apiCallEscritura([
            'acc' => 'insert',
            'tabla' => $tabla,
            'sistema' => $sistema,
            'campos' => '
                aplv_tipo,
                aplv_letra,
                aplv_sucursal,
                aplv_nro,
                aplv_nro_cuota,
                aplv_ref_tipo,
                aplv_ref_letra,
                aplv_ref_sucursal,
                aplv_ref_nro,
                aplv_fecha,
                aplv_monto,
                aplv_cod_mon,
                aplv_cotizacion,
                aplv_tipo_cob,
                aplv_letra_cob,
                aplv_sucursal_cob,
                aplv_nro_cob,
                aplv_fecha_aplic',
            'valores' => " 
                '".$this->esc($deuda['tipo'])."',
                '".$this->esc($deuda['letra'])."',
                '".(int) $deuda['sucursal']."',
                '".(int) $deuda['numero']."',
                '".(int) $deuda['nro_cuota']."',
                '".$this->esc($credito['tipo'])."',
                '".$this->esc($credito['letra'])."',
                '".(int) $credito['sucursal']."',
                '".(int) $credito['numero']."',
                '".$fechaYmd."',
                '".$this->decimal($monto)."',
                '".$this->esc($deuda['cod_mon'])."',
                '".$this->decimal($deuda['cotizacion'])."',
                '".$this->esc($credito['tipo'])."',
                '".$this->esc($credito['letra'])."',
                '".(int) $credito['sucursal']."',
                '".(int) $credito['numero']."',
                '".$fechaYmd."'",
        ], 'aplmov insert aplicación CC cliente '.$etiqueta);
    }

    /**
     * @param  Lado  $deuda
     * @param  Lado  $credito
     */
    private function existeAplmov(array $deuda, array $credito, string $fechaYmd, float $monto): bool
    {
        $perfil = ClienteCuentacorrienteAnitaImportFormatoSupport::perfil();
        $api = new ApiAnita;
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => $perfil['sistema'],
            'tabla' => $perfil['tabla_aplmov'],
            'campos' => 'aplv_monto',
            'whereArmado' => $this->whereAplmovPar($deuda, $credito, $fechaYmd),
        ]));
        if ($parsed['error_lectura'] !== null) {
            throw new RuntimeException('Anita aplmov: '.$parsed['error_lectura']);
        }
        foreach ($parsed['filas'] as $fila) {
            $a = (array) $fila;
            if (abs((float) ($a['aplv_monto'] ?? 0) - $monto) < 0.01) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Lado  $deuda
     * @param  Lado  $credito
     */
    private function borrarAplmov(array $deuda, array $credito, string $fechaYmd, float $monto): void
    {
        if (! $this->existeAplmov($deuda, $credito, $fechaYmd, $monto)) {
            return;
        }

        $perfil = ClienteCuentacorrienteAnitaImportFormatoSupport::perfil();
        (new ApiAnita)->apiCallEscritura([
            'acc' => 'delete',
            'tabla' => $perfil['tabla_aplmov'],
            'sistema' => $perfil['sistema'],
            'whereArmado' => $this->whereAplmovFila($deuda, $credito, $fechaYmd, $monto),
        ], 'aplmov delete aplicación CC cliente '.$deuda['etiqueta'].' ← '.$credito['etiqueta']);
    }

    /**
     * Setea cliv_t_cobrado / fecha_cobro / estado desde la suma ERP de aplicaciones
     * (deuda y crédito/NC). Equivalente idempotente al +monto de CobranzaService.
     *
     * @param  Lado  $lado
     */
    private function recalcularClimovDesdeErp(int $ccId, array $lado, bool $obligatorio): void
    {
        $cc = $this->cargarCc($ccId);
        if ($cc === null) {
            return;
        }

        $suma = (float) Cliente_Cuentacorriente_Aplicacion::query()
            ->where('cliente_cuentacorriente_id', $cc->id)
            ->sum('total');
        $tCobrado = round(abs($suma), 4);
        $ultimaFecha = Cliente_Cuentacorriente_Aplicacion::query()
            ->where('cliente_cuentacorriente_id', $cc->id)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->value('fecha');
        $fechaYmd = $ultimaFecha
            ? (string) ClienteCuentacorrienteAnitaImportClaveSupport::fechaAnitaDesdeIso((string) $ultimaFecha)
            : '0';
        if ($tCobrado < 0.0001) {
            $fechaYmd = '0';
        }

        $totalAbs = abs((float) $cc->total);
        $estado = ($tCobrado >= $totalAbs - 0.01) ? 'C' : ($tCobrado > 0.0001 ? 'I' : 'P');

        $perfil = ClienteCuentacorrienteAnitaImportFormatoSupport::perfil();
        $where = $this->whereClimovDocumento($lado, $perfil['climov_tiene_empresa']);

        try {
            (new ApiAnita)->apiCallEscritura([
                'acc' => 'update',
                'tabla' => $perfil['tabla_climov'],
                'sistema' => $perfil['sistema'],
                'valores' => "
                    cliv_t_cobrado = ".$this->decimal($tCobrado).",
                    cliv_fecha_cobro = '".$fechaYmd."',
                    cliv_estado = '".$estado."'",
                'whereArmado' => $where,
            ], 'climov recalcular '.$lado['etiqueta']);
        } catch (RuntimeException $e) {
            if ($obligatorio) {
                throw new RuntimeException(
                    'No se recalculó climov de '.$lado['etiqueta'].': '.$e->getMessage()
                );
            }
            Log::warning('anita_bridge.fallo', [
                'contexto' => 'climov recalcular opcional '.$lado['etiqueta'],
                'mensaje' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  Lado  $lado
     */
    private function whereClimovDocumento(array $lado, bool $conEmpresa): string
    {
        $where = " WHERE 
            cliv_tipo     = '".$this->esc($lado['tipo'])."' AND
            cliv_letra    = '".$this->esc($lado['letra'])."' AND
            cliv_sucursal = '".(int) $lado['sucursal']."' AND
            cliv_nro      = '".(int) $lado['numero']."'";

        if ($conEmpresa && (int) ($lado['empresa'] ?? 0) > 0) {
            $where .= " AND cliv_empresa = '".(int) $lado['empresa']."'";
        }

        return $where;
    }

    /**
     * @param  Lado  $deuda
     * @param  Lado  $credito
     */
    private function whereAplmovPar(array $deuda, array $credito, string $fechaYmd): string
    {
        return " WHERE 
            aplv_tipo = '".$this->esc($deuda['tipo'])."' AND
            aplv_letra = '".$this->esc($deuda['letra'])."' AND
            aplv_sucursal = '".(int) $deuda['sucursal']."' AND
            aplv_nro = '".(int) $deuda['numero']."' AND
            aplv_tipo_cob = '".$this->esc($credito['tipo'])."' AND
            aplv_letra_cob = '".$this->esc($credito['letra'])."' AND
            aplv_sucursal_cob = '".(int) $credito['sucursal']."' AND
            aplv_nro_cob = '".(int) $credito['numero']."' AND
            aplv_fecha_aplic = '".$fechaYmd."'";
    }

    /**
     * @param  Lado  $deuda
     * @param  Lado  $credito
     */
    private function whereAplmovFila(array $deuda, array $credito, string $fechaYmd, float $monto): string
    {
        return $this->whereAplmovPar($deuda, $credito, $fechaYmd)
            ." AND aplv_monto = '".$this->decimal($monto)."'";
    }

    private function cargarCc(int $id): ?Cliente_Cuentacorriente
    {
        if ($id <= 0) {
            return null;
        }

        return Cliente_Cuentacorriente::query()->with(self::RELACIONES_CC)->find($id);
    }

    private function etiquetaCc(Cliente_Cuentacorriente $cc): string
    {
        $lado = $this->ladoDesdeCc($cc);

        return $lado['etiqueta'] ?? ('CC#'.$cc->id);
    }

    private function decimal(float $valor): string
    {
        return number_format($valor, 4, '.', '');
    }

    private function esc(string $valor): string
    {
        return str_replace("'", '', $valor);
    }
}
