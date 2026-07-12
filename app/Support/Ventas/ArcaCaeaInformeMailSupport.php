<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\ArcaCaea;
use Illuminate\Support\Facades\DB;

/**
 * Arma el texto del mail de presentación CAEA: dónde se frenó y por qué.
 */
final class ArcaCaeaInformeMailSupport
{
    /**
     * @param  array<string, mixed>  $detalle
     * @param  array<string, mixed>  $resumen
     * @return array{
     *   pto_vta:?int,
     *   numero:?int,
     *   codigo_error:?string,
     *   mensaje:string,
     *   etiqueta:string
     * }|null
     */
    public static function resolverFreno(array $detalle, array $resumen = [], string $mensajeGeneral = ''): ?array
    {
        // Solo hay "freno" con error real del lote/worker. Observaciones ARCA no son freno.
        $detenido = (bool) ($detalle['detenido_por_error'] ?? false)
            || (bool) ($detalle['fallo_worker'] ?? false)
            || (int) ($detalle['errores_lote'] ?? 0) > 0;
        $muestra = is_array($detalle['errores_muestra'] ?? null) ? $detalle['errores_muestra'] : [];
        if (! $detenido) {
            return null;
        }

        $primero = is_array($muestra[0] ?? null) ? $muestra[0] : null;

        $pto = isset($primero['pto_vta']) ? (int) $primero['pto_vta'] : null;
        $numero = isset($primero['numero']) ? (int) $primero['numero'] : null;
        $codigo = isset($primero['codigo']) && $primero['codigo'] !== null && $primero['codigo'] !== ''
            ? (string) $primero['codigo']
            : null;
        $mensaje = trim((string) ($primero['mensaje'] ?? ''));

        if ($mensaje === '') {
            $mensaje = trim($mensajeGeneral);
        }

        if ($numero === null || $numero <= 0) {
            if (preg_match('/#\s*(\d{4,8})\b/', $mensaje, $m)) {
                $numero = (int) $m[1];
            }
        }

        if ($pto === null || $pto <= 0) {
            $cola = is_array($resumen['cola_informe'] ?? null) ? $resumen['cola_informe'] : [];
            $primeraCola = is_array($cola[0] ?? null) ? $cola[0] : null;
            if ($primeraCola !== null) {
                $pto = isset($primeraCola['pto_vta']) ? (int) $primeraCola['pto_vta'] : $pto;
                if (($numero === null || $numero <= 0) && isset($primeraCola['proximo_numero'])) {
                    $numero = (int) $primeraCola['proximo_numero'];
                }
            }
        }

        $partes = [];
        if ($pto !== null && $pto > 0) {
            $partes[] = 'PV '.str_pad((string) $pto, 5, '0', STR_PAD_LEFT);
        }
        if ($numero !== null && $numero > 0) {
            $partes[] = '#'.$numero;
        }
        $etiqueta = $partes !== []
            ? implode(' ', $partes)
            : 'error ARCA (sin PV/número en el detalle)';

        return [
            'pto_vta' => $pto,
            'numero' => $numero,
            'codigo_error' => $codigo,
            'mensaje' => $mensaje !== '' ? $mensaje : 'Sin detalle del error.',
            'etiqueta' => $etiqueta,
        ];
    }

    /**
     * Texto de avance para el mail: ERP y ARCA por separado (evita “ERP #X (ARCA #Y)” confuso).
     *
     * @param  array<string, mixed>  $resumen
     */
    public static function ultimoInformadoResumen(array $resumen): ?string
    {
        $porTipo = is_array($resumen['por_tipo_pv'] ?? null) ? $resumen['por_tipo_pv'] : [];
        if ($porTipo === []) {
            return null;
        }

        $partes = [];
        foreach ($porTipo as $fila) {
            if (! is_array($fila)) {
                continue;
            }
            $pto = (int) ($fila['pto_vta'] ?? 0);
            $ultimo = (int) ($fila['ultimo_numero'] ?? 0);
            $arca = (int) ($fila['ultimo_arca'] ?? 0);
            if ($pto <= 0) {
                continue;
            }
            $pv = 'PV '.str_pad((string) $pto, 5, '0', STR_PAD_LEFT);
            $bits = [];
            if ($ultimo > 0) {
                $bits[] = 'último presentado desde ERP #'.$ultimo;
            }
            if ($arca > 0) {
                $bits[] = 'último autorizado en ARCA #'.$arca;
            }
            if ($bits === []) {
                continue;
            }
            $partes[] = $pv.': '.implode(' · ', $bits);
        }

        return $partes !== [] ? implode('; ', $partes) : null;
    }

    /**
     * Cuando la quincena no tiene pendientes/errores: mensaje de cierre + próximo a informar (si existe).
     *
     * @param  array<string, mixed>  $detalle
     * @param  array<string, mixed>  $resumen
     * @return array{
     *   completa: bool,
     *   mensaje: string,
     *   proximo: ?array{
     *     pto_vta: int,
     *     numero: int,
     *     fecha: ?string,
     *     fecha_fmt: ?string,
     *     codigo: ?string,
     *     caea: ?string,
     *     texto: string
     *   }
     * }|null
     */
    public static function resolverCierreQuincena(ArcaCaea $registro, array $detalle, array $resumen): ?array
    {
        if ((bool) ($detalle['fallo_worker'] ?? false)) {
            return null;
        }
        if ((bool) ($detalle['detenido_por_error'] ?? false) || (int) ($detalle['errores_lote'] ?? 0) > 0) {
            return null;
        }

        $pendientes = (int) ($detalle['pendientes_restantes'] ?? $resumen['pendientes'] ?? 0);
        $errores = (int) ($detalle['errores_total'] ?? $resumen['errores'] ?? 0);
        if ($pendientes > 0 || $errores > 0) {
            return null;
        }

        $proximo = self::buscarProximoComprobanteAInformar($registro, $resumen);

        return [
            'completa' => true,
            'mensaje' => 'No queda nada por presentar en esta quincena.',
            'proximo' => $proximo,
        ];
    }

    /**
     * @param  array<string, mixed>  $resumen
     * @return array{
     *   pto_vta: int,
     *   numero: int,
     *   fecha: ?string,
     *   fecha_fmt: ?string,
     *   codigo: ?string,
     *   caea: ?string,
     *   texto: string
     * }|null
     */
    public static function buscarProximoComprobanteAInformar(ArcaCaea $registro, array $resumen): ?array
    {
        $porTipo = is_array($resumen['por_tipo_pv'] ?? null) ? $resumen['por_tipo_pv'] : [];
        $empresaId = (int) $registro->empresa_id;
        if ($empresaId <= 0) {
            return null;
        }

        $candidatos = [];
        foreach ($porTipo as $fila) {
            if (! is_array($fila)) {
                continue;
            }
            $pto = (int) ($fila['pto_vta'] ?? 0);
            if ($pto <= 0) {
                continue;
            }
            $ultimoErp = (int) ($fila['ultimo_numero'] ?? 0);
            $ultimoArca = (int) ($fila['ultimo_arca'] ?? 0);
            $desde = max($ultimoErp, $ultimoArca) + 1;
            if ($desde <= 1) {
                continue;
            }

            $row = DB::table('venta')
                ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
                ->where('puntoventa.empresa_id', $empresaId)
                ->where('puntoventa.modofacturacion', 'A')
                ->whereIn('puntoventa.webservice', ['wsfev1', 'wsmtxca'])
                ->whereRaw('CAST(puntoventa.codigo AS UNSIGNED) = ?', [$pto])
                ->where('venta.numerocomprobante', '>=', $desde)
                ->whereNotNull('venta.cae')
                ->where('venta.cae', '!=', '')
                ->whereNull('venta.deleted_at')
                ->orderBy('venta.numerocomprobante')
                ->first([
                    'venta.numerocomprobante',
                    'venta.fecha',
                    'venta.codigo',
                    'venta.cae',
                    'venta.caea_informado_estado',
                ]);

            if ($row === null) {
                continue;
            }

            $numero = (int) $row->numerocomprobante;
            $fecha = $row->fecha !== null ? (string) $row->fecha : null;
            $fechaFmt = null;
            if ($fecha !== null && $fecha !== '') {
                try {
                    $fechaFmt = \Carbon\Carbon::parse($fecha)->format('d/m/Y');
                } catch (\Throwable) {
                    $fechaFmt = $fecha;
                }
            }

            $pvTxt = 'PV '.str_pad((string) $pto, 5, '0', STR_PAD_LEFT);
            $texto = $pvTxt.' #'.$numero;
            if ($fechaFmt !== null) {
                $texto .= ' del '.$fechaFmt;
            }
            if (! empty($row->cae)) {
                $texto .= ' (CAEA '.substr((string) $row->cae, -6).')';
            }

            $candidatos[] = [
                'pto_vta' => $pto,
                'numero' => $numero,
                'fecha' => $fecha,
                'fecha_fmt' => $fechaFmt,
                'codigo' => $row->codigo !== null ? (string) $row->codigo : null,
                'caea' => $row->cae !== null ? (string) $row->cae : null,
                'texto' => $texto,
            ];
        }

        if ($candidatos === []) {
            return null;
        }

        usort($candidatos, fn (array $a, array $b): int => [$a['fecha'] ?? '', $a['numero']] <=> [$b['fecha'] ?? '', $b['numero']]);

        return $candidatos[0];
    }
}
