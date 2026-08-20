<?php

declare(strict_types=1);

namespace App\Support\Contable\MayorPlanoCuenta;

use App\ApiAnita;
use App\Services\Contable\AnitaAsientoImportService;
use App\Support\Contable\AsientoAnitaMetadatosSupport;
use App\Support\Contable\AsientoOrigenProcesoSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Lee asientos locales ERP y los proyecta como filas estilo ctamov para el mayor plano.
 * Asientos importados desde subdiario/subhist llevan tag [SUBD]/[SUBH] en observacion.
 */
final class MayorPlanoCuentaErpAsientoReader
{
    private const SUBHIST_EMISOR_CAMPOS = 'subh_empresa,subh_fecha,subh_tipo,subh_letra,subh_sucursal,subh_nro,'
        .'subh_emisor,subh_sistema,subh_nro_operacion';

    public function __construct(
        private readonly ApiAnita $api = new ApiAnita,
    ) {}

    /**
     * @param  list<int>  $empresaIds
     * @param  list<int>  $cuentas
     * @return array{ctamov: list<object>, subdiario: list<object>, errores: list<string>, timings: array<string, float|int>}
     */
    public function cargarPeriodo(
        array $empresaIds,
        int $fechaDesdeYmd,
        int $fechaHastaYmd,
        bool $incluyeSubdiario,
        int $cuentaDesde = 0,
        int $cuentaHasta = 0,
        array $cuentas = [],
        bool $cargarMetadatosComprobante = true,
    ): array {
        $t0 = microtime(true);
        $errores = [];
        $ctamov = [];

        if ($fechaDesdeYmd <= 0 || $fechaHastaYmd <= 0 || $fechaHastaYmd < $fechaDesdeYmd || $empresaIds === []) {
            return [
                'ctamov' => [],
                'subdiario' => [],
                'errores' => $errores,
                'timings' => ['erp_asientos_ms' => 0, 'erp_asientos_filas' => 0],
            ];
        }

        $desdeIso = $this->ymdAIso($fechaDesdeYmd);
        $hastaIso = $this->ymdAIso($fechaHastaYmd);
        $cuentasSet = $cuentas !== [] ? array_fill_keys($cuentas, true) : null;
        // El id y las FK de origen viajan en la misma lectura: evita que los enrichers
        // vuelvan a consultar asiento por numeroasiento y por id.
        $columnasFk = $this->columnasFkDisponibles();
        $columnasAnita = $this->columnasAnitaDisponibles();

        $query = DB::table('asiento_movimiento as am')
            ->join('asiento as a', 'a.id', '=', 'am.asiento_id')
            ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
            ->leftJoin('tipoasiento as t', 't.id', '=', 'a.tipoasiento_id')
            ->leftJoin('centrocosto as cco', 'cco.id', '=', 'am.centrocosto_id')
            ->leftJoin('moneda as m', 'm.id', '=', 'am.moneda_id')
            ->whereIn('a.empresa_id', $empresaIds)
            ->whereBetween('a.fecha', [$desdeIso, $hastaIso])
            ->orderBy('a.fecha')
            ->orderBy('a.numeroasiento')
            ->orderBy('a.id')
            ->orderBy('am.id')
            ->select(array_merge([
                'a.id as asiento_id',
                'a.empresa_id',
                'a.numeroasiento',
                'a.fecha',
                'a.observacion as asiento_obs',
                't.abreviatura as tipo_asiento',
                'am.id as mov_id',
                'am.cuentacontable_id',
                'am.centrocosto_id',
                'am.moneda_id',
                'am.monto',
                'am.cotizacion',
                'am.observacion as mov_obs',
                'cc.codigo as cuenta_codigo',
                'cco.codigo as ccosto_codigo',
                'm.codigo as moneda_codigo',
            ], array_map(fn (string $columna) => 'a.'.$columna, array_merge($columnasFk, $columnasAnita))));

        if (! $incluyeSubdiario) {
            $query->where('a.observacion', 'not like', '%'.AnitaAsientoImportService::TAG_SUBHIST.'%')
                ->where('a.observacion', 'not like', '%'.AnitaAsientoImportService::TAG_SUBDIARIO.'%')
                ->where('a.observacion', 'not like', '%[subhist]%')
                ->where('a.observacion', 'not like', '%[subdiario]%');
        }

        $linea = 0;
        $movimientosOrigen = 0;
        $gruposAsiento = [];
        $asientoActualId = 0;
        $requiereEmisoresBridge = false;
        foreach ($query->cursor() as $row) {
            $rowAsientoId = (int) $row->asiento_id;
            if ($asientoActualId > 0 && $rowAsientoId !== $asientoActualId) {
                $this->volcarGruposAsiento(
                    $gruposAsiento,
                    $ctamov,
                    $linea,
                    $requiereEmisoresBridge,
                    $columnasFk,
                );
                $gruposAsiento = [];
            }
            $asientoActualId = $rowAsientoId;

            $cuenta = (int) preg_replace('/\D/', '', (string) $row->cuenta_codigo);
            if ($cuenta <= 0) {
                continue;
            }
            if ($cuentasSet !== null && ! isset($cuentasSet[$cuenta])) {
                continue;
            }
            if ($cuentaDesde > 0 && $cuenta < $cuentaDesde) {
                continue;
            }
            if ($cuentaHasta > 0 && $cuenta > $cuentaHasta) {
                continue;
            }

            $monto = (float) $row->monto;
            if (abs($monto) < 0.00005) {
                continue;
            }

            $movimientosOrigen++;
            $obsAsiento = trim((string) ($row->asiento_obs ?? ''));
            $comprobante = AsientoAnitaMetadatosSupport::desdeObservacion($obsAsiento);
            $origenPersistido = trim((string) ($row->anita_origen ?? ''));
            $origen = $origenPersistido !== '' ? $origenPersistido : $comprobante['origen'];
            $esDetalleImportado = AsientoAnitaMetadatosSupport::esDetalle($origen);

            $claveGrupo = $esDetalleImportado
                ? implode('|', [
                    $rowAsientoId,
                    (int) $row->cuentacontable_id,
                    (int) ($row->moneda_id ?? 0),
                    (int) ($row->centrocosto_id ?? 0),
                    $monto >= 0 ? 'D' : 'H',
                ])
                : 'mov|'.(int) $row->mov_id;
            if (! isset($gruposAsiento[$claveGrupo])) {
                $gruposAsiento[$claveGrupo] = [
                    'row' => $row,
                    'cuenta' => $cuenta,
                    'monto' => 0.0,
                    'observacion' => '',
                ];
            }
            $gruposAsiento[$claveGrupo]['monto'] += $monto;
            if ($gruposAsiento[$claveGrupo]['observacion'] === '') {
                $gruposAsiento[$claveGrupo]['observacion'] = trim((string) ($row->mov_obs ?? ''));
            }
        }
        $this->volcarGruposAsiento(
            $gruposAsiento,
            $ctamov,
            $linea,
            $requiereEmisoresBridge,
            $columnasFk,
        );

        $subhistEmisores = 0;
        $cutoffErp = $this->fuenteErpHastaYmd();
        $subhistHasta = $cutoffErp > 0 ? min($fechaHastaYmd, $cutoffErp) : 0;
        if ($cargarMetadatosComprobante && $requiereEmisoresBridge && $incluyeSubdiario
            && $ctamov !== [] && $fechaDesdeYmd <= $subhistHasta) {
            $tSubhist = microtime(true);
            $indiceEmisores = $this->cargarEmisoresSubhistMasivo(
                $empresaIds,
                $fechaDesdeYmd,
                $subhistHasta,
                $cuentaDesde,
                $cuentaHasta,
                $cuentas,
                $errores,
            );
            foreach ($ctamov as $fila) {
                if (empty($fila->erp_origen_subdiario)) {
                    continue;
                }
                if (trim((string) ($fila->erp_emisor_anita ?? '')) !== '') {
                    continue;
                }
                $clave = $this->claveDocumentoSubhistDesdeCtamov($fila);
                $emisor = trim((string) ($indiceEmisores[$clave]['emisor'] ?? ''));
                if ($emisor !== '') {
                    $fila->erp_emisor_anita = $emisor;
                    // El subsistema define si ese código es proveedor, cliente o cuenta de caja.
                    if (trim((string) ($fila->ctav_sistema ?? '')) === '') {
                        $fila->ctav_sistema = trim((string) ($indiceEmisores[$clave]['sistema'] ?? ''));
                    }
                    $subhistEmisores++;
                }
            }
            $timingSubhist = round((microtime(true) - $tSubhist) * 1000, 1);
        }

        return [
            'ctamov' => $ctamov,
            'subdiario' => [],
            'errores' => $errores,
            'timings' => [
                'erp_asientos_ms' => round((microtime(true) - $t0) * 1000, 1),
                'erp_asientos_filas' => count($ctamov),
                'erp_asientos_movimientos_origen' => $movimientosOrigen,
                'erp_asientos_grupos' => count($ctamov),
                'erp_subhist_emisores_ms' => $timingSubhist ?? 0,
                'erp_subhist_emisores_resueltos' => $subhistEmisores,
            ],
        ];
    }

    /**
     * Solo el detalle importado de subdiario/subhist se resume como Anita: por
     * asiento, cuenta, moneda y centro de costo. Los asientos contables nativos
     * conservan cada renglón, porque observaciones distintas pueden expresar una
     * discriminación deliberada. Debe y Haber permanecen como piernas separadas
     * para no alterar los totales brutos del balance de sumas y saldos.
     *
     * @param  array<string, array{row: object, cuenta: int, monto: float, observacion: string}>  $grupos
     * @param  list<object>  $ctamov
     * @param  list<string>  $columnasFk
     */
    private function volcarGruposAsiento(
        array $grupos,
        array &$ctamov,
        int &$linea,
        bool &$requiereEmisoresBridge,
        array $columnasFk,
    ): void {
        foreach ($grupos as $grupo) {
            $monto = (float) $grupo['monto'];
            if (abs($monto) < 0.00005) {
                continue;
            }

            $row = $grupo['row'];
            $obsAsiento = trim((string) ($row->asiento_obs ?? ''));
            $comprobante = AsientoAnitaMetadatosSupport::desdeObservacion($obsAsiento);
            $origenPersistido = trim((string) ($row->anita_origen ?? ''));
            $origen = $origenPersistido !== '' ? $origenPersistido : $comprobante['origen'];
            $esSub = AsientoAnitaMetadatosSupport::esDetalle($origen);
            $emisorPersistido = trim((string) ($row->anita_emisor ?? ''));
            if ($origenPersistido === '' && $esSub && $emisorPersistido === '') {
                $requiereEmisoresBridge = true;
            }
            $linea++;

            $filaCtamov = (object) [
                'ctav_empresa' => (int) $row->empresa_id,
                'ctav_nro_asiento' => (int) $row->numeroasiento,
                'ctav_nro_linea' => $linea,
                'ctav_d_h' => $monto >= 0 ? 'D' : 'H',
                'ctav_cuenta' => (int) $grupo['cuenta'],
                'ctav_fecha' => (int) str_replace('-', '', substr((string) $row->fecha, 0, 10)),
                'ctav_tipo' => trim((string) ($row->anita_tipo ?? '')) ?: $comprobante['tipo'],
                'ctav_letra' => ($row->anita_letra ?? null) !== null
                    ? (string) $row->anita_letra
                    : $comprobante['letra'],
                'ctav_sucursal' => ($row->anita_sucursal ?? null) !== null
                    ? (int) $row->anita_sucursal
                    : $comprobante['sucursal'],
                'ctav_nro' => ($row->anita_nro ?? null) !== null
                    ? (int) $row->anita_nro
                    : $comprobante['nro'],
                'ctav_importe' => abs($monto),
                'ctav_desc_mov' => $grupo['observacion'] !== '' ? $grupo['observacion'] : $obsAsiento,
                'ctav_cod_mon' => (string) ($row->moneda_codigo ?? '1'),
                'ctav_cotizacion' => (float) ($row->cotizacion ?? 1),
                'ctav_tipo_asiento' => strtoupper(trim((string) ($row->tipo_asiento ?? ''))),
                'ctav_balancea' => 'S',
                'ctav_o_compra' => 0,
                'ctav_ccosto' => (int) ($row->ccosto_codigo ?? 0),
                'ctav_sistema' => trim((string) ($row->anita_sistema ?? '')) ?: $comprobante['sistema'],
                'ctav_asi_mon_ref' => AnitaAsientoImportService::ASI_MON_REF_ORIGEN_ERP,
                'erp_origen_subdiario' => $esSub,
                'erp_asiento_obs' => $obsAsiento,
                'erp_asiento_id' => (int) $row->asiento_id,
                'erp_asiento_fks' => $this->fksDeFila($row, $columnasFk),
            ];
            if ($emisorPersistido !== '') {
                $filaCtamov->erp_emisor_anita = $emisorPersistido;
            }
            $ctamov[] = $filaCtamov;
        }
    }

    /**
     * Una sola lectura de subhist para todo el rango y todas las empresas.
     * No consulta por asiento ni por comprobante.
     *
     * @param  list<int>  $empresaIds
     * @param  list<int>  $cuentas
     * @param  list<string>  $errores
     * @return array<string, array{emisor: string, sistema: string}>
     */
    private function cargarEmisoresSubhistMasivo(
        array $empresaIds,
        int $fechaDesde,
        int $fechaHasta,
        int $cuentaDesde,
        int $cuentaHasta,
        array $cuentas,
        array &$errores,
    ): array {
        $empresaIds = array_values(array_unique(array_filter(array_map('intval', $empresaIds), fn (int $id) => $id > 0)));
        if ($empresaIds === []) {
            return [];
        }

        $where = ' WHERE subh_empresa IN ('.implode(',', $empresaIds).')'
            .' AND subh_fecha BETWEEN '.$fechaDesde.' AND '.$fechaHasta;
        $filtroCuenta = $this->condicionCuentasSubhist($cuentaDesde, $cuentaHasta, $cuentas);
        if ($filtroCuenta !== '') {
            $where .= ' AND ('.$filtroCuenta.')';
        }

        $t0 = microtime(true);
        $raw = $this->api->apiCall([
            'acc' => 'list',
            'sistema' => 'contab',
            'tabla' => 'subhist',
            'campos' => self::SUBHIST_EMISOR_CAMPOS,
            'whereArmado' => $where,
        ]);
        $error = ApiAnita::extraerMensajeError($raw);
        if ($error !== null) {
            $errores[] = 'subhist-emisores-erp: '.$error;

            return [];
        }

        $filas = ApiAnita::decodificarListaFilas($raw);
        $indice = [];
        foreach ($filas as $fila) {
            $emisor = trim((string) ($fila->subh_emisor ?? ''));
            if ($emisor === '') {
                continue;
            }
            $indice[$this->claveDocumentoSubhist(
                (int) ($fila->subh_empresa ?? 0),
                (int) ($fila->subh_fecha ?? 0),
                (int) ($fila->subh_nro_operacion ?? 0),
                (string) ($fila->subh_tipo ?? ''),
                (string) ($fila->subh_letra ?? ' '),
                (int) ($fila->subh_sucursal ?? 0),
                (int) ($fila->subh_nro ?? 0),
            )] = [
                'emisor' => $emisor,
                'sistema' => trim((string) ($fila->subh_sistema ?? '')),
            ];
        }

        Log::info('mayor_plano_cuenta.bridge_subhist_erp', [
            'empresas' => $empresaIds,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'filas' => count($filas),
            'emisores' => count($indice),
            'ms' => round((microtime(true) - $t0) * 1000, 1),
        ]);

        return $indice;
    }

    private function fuenteErpHastaYmd(): int
    {
        $raw = trim((string) config('contable.mayor_plano_cuenta.fuente_erp_hasta', ''));
        if (preg_match('/^\d{8}$/', $raw) === 1) {
            return (int) $raw;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            return (int) str_replace('-', '', $raw);
        }

        return 0;
    }

    /**
     * @param  list<int>  $cuentas
     */
    private function condicionCuentasSubhist(int $desde, int $hasta, array $cuentas): string
    {
        $cuentas = array_values(array_unique(array_filter(array_map('intval', $cuentas), fn (int $c) => $c > 0)));
        $porColumna = function (string $columna) use ($desde, $hasta, $cuentas): string {
            $partes = [];
            if ($cuentas !== []) {
                $partes[] = $columna.' IN ('.implode(',', $cuentas).')';
            }
            if ($desde > 0 && $hasta > 0) {
                $partes[] = $columna.' BETWEEN '.$desde.' AND '.$hasta;
            } elseif ($desde > 0) {
                $partes[] = $columna.'>='.$desde;
            } elseif ($hasta > 0) {
                $partes[] = $columna.'<='.$hasta;
            }

            return $partes === [] ? '' : '('.implode(' OR ', $partes).')';
        };

        $cuenta = $porColumna('subh_cuenta');

        return $cuenta === '' ? '' : $cuenta.' OR '.$porColumna('subh_contrapartida');
    }

    private function claveDocumentoSubhistDesdeCtamov(object $fila): string
    {
        return $this->claveDocumentoSubhist(
            (int) ($fila->ctav_empresa ?? 0),
            (int) ($fila->ctav_fecha ?? 0),
            (int) ($fila->ctav_nro_asiento ?? 0),
            (string) ($fila->ctav_tipo ?? ''),
            (string) ($fila->ctav_letra ?? ' '),
            (int) ($fila->ctav_sucursal ?? 0),
            (int) ($fila->ctav_nro ?? 0),
        );
    }

    private function claveDocumentoSubhist(
        int $empresa,
        int $fecha,
        int $nroOperacion,
        string $tipo,
        string $letra,
        int $sucursal,
        int $nro,
    ): string {
        return implode('|', [
            $empresa,
            $fecha,
            $nroOperacion,
            strtoupper(trim($tipo)),
            strtoupper(trim($letra)),
            $sucursal,
            $nro,
        ]);
    }

    /**
     * @return list<string>
     */
    private function columnasFkDisponibles(): array
    {
        $columnas = [];
        foreach (AsientoOrigenProcesoSupport::columnasFk() as $fk) {
            if (Schema::hasColumn('asiento', $fk)) {
                $columnas[] = $fk;
            }
        }

        return $columnas;
    }

    /**
     * @return list<string>
     */
    private function columnasAnitaDisponibles(): array
    {
        $columnas = [];
        foreach ([
            'anita_origen',
            'anita_sistema',
            'anita_tipo',
            'anita_letra',
            'anita_sucursal',
            'anita_nro',
            'anita_emisor',
        ] as $columna) {
            if (Schema::hasColumn('asiento', $columna)) {
                $columnas[] = $columna;
            }
        }

        return $columnas;
    }

    /**
     * Solo las FK con valor: el resultado se guarda en cache de archivo y la mayoría
     * de los asientos no tiene documento de origen.
     *
     * @param  list<string>  $columnasFk
     * @return array<string, int>
     */
    private function fksDeFila(object $row, array $columnasFk): array
    {
        $fks = [];
        foreach ($columnasFk as $fk) {
            $id = (int) ($row->{$fk} ?? 0);
            if ($id > 0) {
                $fks[$fk] = $id;
            }
        }

        return $fks;
    }

    private function ymdAIso(int $ymd): string
    {
        $s = str_pad((string) $ymd, 8, '0', STR_PAD_LEFT);

        return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
    }
}
