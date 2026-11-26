<?php

namespace App\Support\Stock;

use App\ApiAnita;
use Illuminate\Support\Facades\Log;

/**
 * Lectura Anita stkmov del día para l-stkmovint (Villafranca y/o Bierzo vía path_sistema).
 */
final class StkmovIntAnitaBridgeReader
{
    private const STKMOV_CAMPOS = 'stkv_articulo,stkv_fecha,stkv_tipo,stkv_deposito,stkv_partida,'
        .'stkv_agrupacion,stkv_cantidad,stkv_cant_unidad,stkv_precio,stkv_expreso,'
        .'stkv_descuento,stkv_dto_gral';

    /** Tipos de comprobante de venta (tcomp_tipo_comp) que el C manda a la columna Bonif. si el precio es 0. */
    private const TIPOS_COMP_BONIFICACION = ['01', '02', '03'];

    public function __construct(
        private readonly ApiAnita $api = new ApiAnita,
    ) {
    }

    /**
     * @param  array<string, true>  $articulosEnUnidades  SKU corto cuya unidad empieza con UN (el importe usa cant. unidad)
     * @return array{
     *   filas: list<array{sku: string, agrupacion: string, saldo: float, unidades: float, total: float, bonificacion: float}>,
     *   ok: bool,
     *   error: ?string
     * }
     */
    public function agregarSaldosDelDia(string $pathSistema, string $fechaIso, array $articulosEnUnidades = []): array
    {
        $ymd = (int) str_replace('-', '', $fechaIso);
        if ($ymd <= 0 || $pathSistema === '') {
            return ['filas' => [], 'ok' => false, 'error' => 'Path o fecha inválidos'];
        }

        $signos = $this->mapaSignoTcomp($pathSistema);
        $agrupaciones = StkmovIntMailSupport::CATEGORIAS_ANITA;
        $whereAgr = [];
        foreach ($agrupaciones as $agr) {
            $whereAgr[] = "stkv_agrupacion = '".$this->esc($agr)."'";
        }

        $where = ' WHERE stkv_fecha = '.$ymd
            .' AND ('.implode(' OR ', $whereAgr).')';

        try {
            $raw = $this->api->apiCall([
                'acc' => 'list',
                'sistema' => 'ventas',
                'tabla' => 'stkmov',
                'campos' => self::STKMOV_CAMPOS,
                'whereArmado' => $where,
                'orderBy' => 'stkv_articulo, stkv_fecha',
                'path_sistema' => $pathSistema,
            ]);
        } catch (\Throwable $e) {
            Log::warning('StkmovIntAnitaBridgeReader', [
                'path' => $pathSistema,
                'error' => $e->getMessage(),
            ]);

            return ['filas' => [], 'ok' => false, 'error' => $e->getMessage()];
        }

        $msg = ApiAnita::extraerMensajeError(is_string($raw) ? $raw : null);
        if ($msg !== null) {
            return ['filas' => [], 'ok' => false, 'error' => $msg];
        }

        $acum = [];
        foreach (ApiAnita::decodificarListaFilas(is_string($raw) ? $raw : '') as $fila) {
            $f = is_array($fila) ? $fila : (array) $fila;
            $tipo = strtoupper(trim((string) ($f['stkv_tipo'] ?? '')));
            if (isset($signos[$tipo])) {
                $meta = $signos[$tipo];
            } elseif ($signos === []) {
                $meta = ['signo' => $this->signoHeuristico($tipo), 'tipo_comp' => ''];
            } else {
                continue;
            }
            $signo = (int) ($meta['signo'] ?? 0);
            if ($signo === 0) {
                continue;
            }
            $sku = StkmovIntMailSupport::skuCorto((string) ($f['stkv_articulo'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $agr = StkmovIntMailSupport::normalizarAgrupacion((string) ($f['stkv_agrupacion'] ?? ''));
            $cant = abs((float) ($f['stkv_cantidad'] ?? 0));
            $cantUn = abs((float) ($f['stkv_cant_unidad'] ?? 0));
            // Mismo sentido que tot_saldo del C: entrada resta, salida suma al “saldo de movimiento del día”.
            $deltaSaldo = $signo < 0 ? $cant : -$cant;
            $deltaUn = $signo < 0 ? ($cantUn > 0 ? $cantUn : $cant) : -($cantUn > 0 ? $cantUn : $cant);

            $precio = (float) ($f['stkv_precio'] ?? 0);
            $precioNeto = $precio
                * (1 - ((float) ($f['stkv_descuento'] ?? 0) / 100))
                * (1 - ((float) ($f['stkv_dto_gral'] ?? 0) / 100));
            $esUn = isset($articulosEnUnidades[$sku]);
            $baseImporte = $esUn ? $cantUn : $cant;
            $deltaTotal = ($signo < 0 ? $baseImporte : -$baseImporte) * $precioNeto;
            $esBonif = abs($precio) < 0.0000001
                && in_array((string) ($meta['tipo_comp'] ?? ''), self::TIPOS_COMP_BONIFICACION, true);
            $deltaBonif = $esBonif ? $deltaSaldo : 0.0;

            if (! isset($acum[$sku])) {
                $acum[$sku] = [
                    'sku' => $sku,
                    'agrupacion' => $agr,
                    'saldo' => 0.0,
                    'unidades' => 0.0,
                    'total' => 0.0,
                    'bonificacion' => 0.0,
                ];
            }
            $acum[$sku]['saldo'] += $deltaSaldo;
            $acum[$sku]['unidades'] += $deltaUn;
            $acum[$sku]['total'] += $deltaTotal;
            $acum[$sku]['bonificacion'] += $deltaBonif;
            if ($acum[$sku]['agrupacion'] === '' && $agr !== '') {
                $acum[$sku]['agrupacion'] = $agr;
            }
        }

        return [
            'filas' => array_values($acum),
            'ok' => true,
            'error' => null,
        ];
    }

    /**
     * @return array<string, array{signo: int, tipo_comp: string}>
     */
    private function mapaSignoTcomp(string $pathSistema): array
    {
        try {
            $raw = $this->api->apiCall([
                'acc' => 'list',
                'sistema' => 'ventas',
                'tabla' => 't_comp',
                'campos' => 'tcomp_clave,tcomp_oper_stk,tcomp_tipo_comp',
                'whereArmado' => ' WHERE 1=1',
                'orderBy' => 'tcomp_clave',
                'path_sistema' => $pathSistema,
            ]);
        } catch (\Throwable $e) {
            return [];
        }
        $msg = ApiAnita::extraerMensajeError(is_string($raw) ? $raw : null);
        if ($msg !== null) {
            return [];
        }
        $mapa = [];
        foreach (ApiAnita::decodificarListaFilas(is_string($raw) ? $raw : '') as $fila) {
            $f = is_array($fila) ? $fila : (array) $fila;
            $tipo = strtoupper(trim((string) ($f['tcomp_clave'] ?? '')));
            if ($tipo === '') {
                continue;
            }
            $oper = trim((string) ($f['tcomp_oper_stk'] ?? ''));
            // l-stkmovint -b deja fl_comp_en_part = 1: solo oper_stk 2 (entrada) o 3 (salida).
            // AP (5) y COM (7) no entran al listado ni al cuadro de porcentajes.
            if ($oper === '2') {
                $signo = 1;
            } elseif ($oper === '3') {
                $signo = -1;
            } else {
                $signo = 0;
            }
            $digitos = preg_replace('/\D+/', '', (string) ($f['tcomp_tipo_comp'] ?? '')) ?? '';
            $mapa[$tipo] = [
                'signo' => $signo,
                'tipo_comp' => $digitos === '' ? '' : str_pad(substr($digitos, -2), 2, '0', STR_PAD_LEFT),
            ];
        }

        return $mapa;
    }

    private function signoHeuristico(string $tipo): int
    {
        $tipo = strtoupper(trim($tipo));
        if ($tipo === '') {
            return 0;
        }
        if (str_starts_with($tipo, 'FAC') || str_starts_with($tipo, 'TKT') || str_starts_with($tipo, 'NCD')
            || in_array($tipo, ['EGR', 'AJ-', 'NCR', 'REM'], true)) {
            return -1;
        }
        if (in_array($tipo, ['ING', 'AJ+', 'TRA', 'REC', 'COM', 'DES'], true) || str_starts_with($tipo, 'NC')) {
            return 1;
        }

        return -1;
    }

    private function esc(string $valor): string
    {
        return str_replace("'", "''", $valor);
    }
}
