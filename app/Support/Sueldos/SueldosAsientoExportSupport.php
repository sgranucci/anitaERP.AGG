<?php

namespace App\Support\Sueldos;

use App\Models\Contable\Asiento;
use App\Models\Sueldos\Liquidacion_Sueldos;

/**
 * Arma el payload unificado del asiento de liquidación para PDF/Excel/CSV.
 * Preview (sin N°) o grabado (con numeroasiento real).
 */
final class SueldosAsientoExportSupport
{
    /**
     * @param  array<string, mixed>|null  $preview  Resultado de LiquidacionAsientoService::preview / CalidadCierre
     * @return array<string, mixed>
     */
    public static function armar(Liquidacion_Sueldos $liq, ?array $preview = null): array
    {
        $liq->loadMissing([
            'empresa:id,nombre',
            'asiento',
            'asientosSueldos.asiento.asiento_movimientos.cuentacontables',
            'asientosSueldos.asiento.asiento_movimientos.centrocostos',
            'asientosSueldos.centrocosto:id,codigo,nombre',
        ]);

        if (self::tieneAsientosGrabados($liq)) {
            return self::desdeAsientosGrabados($liq);
        }

        if ($preview === null) {
            $preview = SueldosAsientoCalidadCierreSupport::evaluar($liq);
        }

        return self::desdePreview($liq, $preview);
    }

    public static function tieneAsientosGrabados(Liquidacion_Sueldos $liq): bool
    {
        if ($liq->asientosSueldos !== null && $liq->asientosSueldos->isNotEmpty()) {
            return true;
        }

        return (int) ($liq->asiento_id ?? 0) > 0 && $liq->asiento !== null;
    }

    /**
     * @param  array<string, mixed>  $preview
     * @return array<string, mixed>
     */
    public static function desdePreview(Liquidacion_Sueldos $liq, array $preview): array
    {
        $modo = SueldosAsientoModoSupport::normalizar((string) ($preview['modo'] ?? ''));
        $gruposSrc = $preview['grupos'] ?? [];
        if ($gruposSrc === [] || $modo !== SueldosAsientoModoSupport::ANITA) {
            $gruposSrc = [[
                'etiqueta' => null,
                'lineas' => $preview['lineas'] ?? [],
                'total_debe' => $preview['total_debe'] ?? 0,
                'total_haber' => $preview['total_haber'] ?? 0,
            ]];
        }

        $grupos = [];
        foreach ($gruposSrc as $g) {
            $lineas = self::normalizarLineas($g['lineas'] ?? []);
            $grupos[] = [
                'etiqueta' => trim((string) ($g['etiqueta'] ?? '')) ?: null,
                'numeroasiento' => null,
                'asiento_id' => null,
                'lineas' => $lineas,
                'total_debe' => round((float) ($g['total_debe'] ?? self::sumDebe($lineas)), 2),
                'total_haber' => round((float) ($g['total_haber'] ?? self::sumHaber($lineas)), 2),
            ];
        }

        return self::empaquetar($liq, true, $modo, $grupos, $preview);
    }

    /**
     * @return array<string, mixed>
     */
    public static function desdeAsientosGrabados(Liquidacion_Sueldos $liq): array
    {
        $asientos = [];
        foreach ($liq->asientosSueldos ?? [] as $hijo) {
            if ($hijo->asiento) {
                $asientos[] = [
                    'asiento' => $hijo->asiento,
                    'etiqueta' => optional($hijo->centrocosto)->codigo
                        ? trim((string) $hijo->centrocosto->codigo.' '.(string) ($hijo->centrocosto->nombre ?? ''))
                        : null,
                ];
            }
        }

        if ($asientos === [] && $liq->asiento) {
            $asientos[] = ['asiento' => $liq->asiento, 'etiqueta' => null];
        }

        $modo = count($asientos) > 1
            ? SueldosAsientoModoSupport::ANITA
            : SueldosAsientoModoSupport::resolver((int) $liq->empresa_id);

        $grupos = [];
        foreach ($asientos as $item) {
            /** @var Asiento $asiento */
            $asiento = $item['asiento'];
            $asiento->loadMissing([
                'asiento_movimientos.cuentacontables',
                'asiento_movimientos.centrocostos',
            ]);
            $lineas = [];
            foreach ($asiento->asiento_movimientos as $mov) {
                $monto = (float) $mov->monto;
                $debe = $monto >= 0 ? round($monto, 2) : 0.0;
                $haber = $monto < 0 ? round(abs($monto), 2) : 0.0;
                $cc = $mov->centrocostos;
                $cuenta = $mov->cuentacontables;
                $lineas[] = [
                    'cuenta_codigo' => (string) (optional($cuenta)->codigo ?? ''),
                    'cuenta_nombre' => (string) (optional($cuenta)->nombre ?? ''),
                    'centrocosto_codigo' => (string) (optional($cc)->codigo ?? ''),
                    'centrocosto_nombre' => (string) (optional($cc)->nombre ?? ''),
                    'debe' => $debe,
                    'haber' => $haber,
                    'observacion' => (string) ($mov->observacion ?? ''),
                ];
            }
            $grupos[] = [
                'etiqueta' => $item['etiqueta'],
                'numeroasiento' => (string) ($asiento->numeroasiento ?? ''),
                'asiento_id' => (int) $asiento->id,
                'lineas' => $lineas,
                'total_debe' => self::sumDebe($lineas),
                'total_haber' => self::sumHaber($lineas),
            ];
        }

        return self::empaquetar($liq, false, $modo, $grupos, null);
    }

    /**
     * @param  list<array<string, mixed>>  $grupos
     * @param  array<string, mixed>|null  $preview
     * @return array<string, mixed>
     */
    private static function empaquetar(
        Liquidacion_Sueldos $liq,
        bool $esPreview,
        string $modo,
        array $grupos,
        ?array $preview
    ): array {
        $periodoLabel = $liq->periodo_mes
            ? sprintf('%02d/%04d', (int) $liq->periodo_mes, (int) $liq->periodo_anio)
            : (string) ($liq->periodo ?? '');

        $numeros = [];
        foreach ($grupos as $g) {
            $n = trim((string) ($g['numeroasiento'] ?? ''));
            if ($n !== '') {
                $numeros[] = $n;
            }
        }

        $totalDebe = round(array_sum(array_map(fn ($g) => (float) ($g['total_debe'] ?? 0), $grupos)), 2);
        $totalHaber = round(array_sum(array_map(fn ($g) => (float) ($g['total_haber'] ?? 0), $grupos)), 2);

        $titulo = $esPreview
            ? 'Asiento de liquidación — PREVIEW'
            : (
                count($numeros) === 1
                    ? 'Asiento de liquidación N° '.$numeros[0]
                    : 'Asientos de liquidación'
            );

        return [
            'es_preview' => $esPreview,
            'titulo' => $titulo,
            'empresa_nombre' => (string) (optional($liq->empresa)->nombre ?? ''),
            'empresa_id' => (int) $liq->empresa_id,
            'liquidacion_id' => (int) $liq->id,
            'liquidacion_numero' => (int) $liq->numero,
            'descripcion' => (string) ($liq->descripcion ?? ''),
            'periodo_label' => $periodoLabel,
            'estado' => (string) $liq->estado,
            'estado_label' => $liq->estadoLabel(),
            'modo' => $modo,
            'modo_label' => $modo === SueldosAsientoModoSupport::ANITA ? 'Anita' : 'ERP',
            'numeros_asiento' => $numeros,
            'numeros_asiento_label' => $esPreview
                ? 'PREVIEW (sin número)'
                : (count($numeros) > 0 ? implode(', ', $numeros) : '—'),
            'fecha_fmt' => $liq->periodo_hasta
                ? $liq->periodo_hasta->format('d/m/Y')
                : ($liq->fecha_liquidacion ? $liq->fecha_liquidacion->format('d/m/Y') : ''),
            'grupos' => $grupos,
            'total_debe' => $totalDebe,
            'total_haber' => $totalHaber,
            'filas' => self::aplanarFilas($grupos, $esPreview),
            'errores' => $preview['errores'] ?? [],
            'advertencias' => $preview['advertencias'] ?? [],
            'haber_a_pagar' => (float) ($preview['haber_a_pagar'] ?? 0),
            'total_neto_cabecera' => (float) ($preview['total_neto_cabecera'] ?? 0),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private static function normalizarLineas(array $lineas): array
    {
        $out = [];
        foreach ($lineas as $linea) {
            $out[] = [
                'cuenta_codigo' => (string) ($linea['cuenta_codigo'] ?? ''),
                'cuenta_nombre' => (string) ($linea['cuenta_nombre'] ?? ''),
                'centrocosto_codigo' => (string) ($linea['centrocosto_codigo'] ?? ''),
                'centrocosto_nombre' => (string) ($linea['centrocosto_nombre'] ?? ''),
                'debe' => round((float) ($linea['debe'] ?? 0), 2),
                'haber' => round((float) ($linea['haber'] ?? 0), 2),
                'observacion' => (string) ($linea['observacion'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $grupos
     * @return list<array<string, mixed>>
     */
    public static function aplanarFilas(array $grupos, bool $esPreview): array
    {
        $filas = [];
        foreach ($grupos as $grupo) {
            $numero = $esPreview
                ? 'PREVIEW'
                : (string) ($grupo['numeroasiento'] ?? '');
            $etiqueta = (string) ($grupo['etiqueta'] ?? '');
            $lineas = $grupo['lineas'] ?? [];
            if ($lineas === []) {
                $filas[] = [
                    'numeroasiento' => $numero,
                    'grupo' => $etiqueta,
                    'cuenta_codigo' => '',
                    'cuenta_nombre' => '',
                    'centrocosto' => '',
                    'debe' => (float) ($grupo['total_debe'] ?? 0),
                    'haber' => (float) ($grupo['total_haber'] ?? 0),
                    'observacion' => '',
                ];
                continue;
            }
            foreach ($lineas as $linea) {
                $ccCod = trim((string) ($linea['centrocosto_codigo'] ?? ''));
                $ccNom = trim((string) ($linea['centrocosto_nombre'] ?? ''));
                $filas[] = [
                    'numeroasiento' => $numero,
                    'grupo' => $etiqueta,
                    'cuenta_codigo' => (string) ($linea['cuenta_codigo'] ?? ''),
                    'cuenta_nombre' => (string) ($linea['cuenta_nombre'] ?? ''),
                    'centrocosto' => trim($ccCod.' '.$ccNom),
                    'debe' => (float) ($linea['debe'] ?? 0),
                    'haber' => (float) ($linea['haber'] ?? 0),
                    'observacion' => (string) ($linea['observacion'] ?? ''),
                ];
            }
        }

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private static function sumDebe(array $lineas): float
    {
        return round(array_sum(array_map(fn ($l) => (float) ($l['debe'] ?? 0), $lineas)), 2);
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private static function sumHaber(array $lineas): float
    {
        return round(array_sum(array_map(fn ($l) => (float) ($l['haber'] ?? 0), $lineas)), 2);
    }

    public static function nombreArchivo(array $payload, string $ext): string
    {
        $nro = (int) ($payload['liquidacion_numero'] ?? 0);
        $base = $payload['es_preview'] ?? true
            ? 'asiento_liquidacion_'.$nro.'_preview'
            : 'asiento_liquidacion_'.$nro;
        $nums = $payload['numeros_asiento'] ?? [];
        if (! ($payload['es_preview'] ?? true) && count($nums) === 1) {
            $safe = preg_replace('/[^\w\-]+/', '_', (string) $nums[0]);
            $base = 'asiento_liquidacion_'.$nro.'_'.$safe;
        }

        return $base.'.'.$ext;
    }
}
