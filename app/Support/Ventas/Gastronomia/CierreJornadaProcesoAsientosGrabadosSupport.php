<?php

namespace App\Support\Ventas\Gastronomia;

use App\Models\Contable\Asiento;

/**
 * Reconstruye el preview de asientos del proceso desde lo ya grabado en ERP.
 *
 * Cuando el proceso está cerrado, la UI no debe regenerar un preview en vivo
 * (post-emisión el asiento 1 desaparece del recálculo): debe mostrar los asientos reales.
 */
final class CierreJornadaProcesoAsientosGrabadosSupport
{
    /**
     * @param  array<string, mixed>  $grabacion  Payload `asientos_proceso_grabacion` del snapshot
     * @return array{
     *   asientos: list<array<string, mixed>>,
     *   advertencias: list<string>,
     *   faltantes: list<array<string, mixed>>,
     *   cuentas_requeridas: list<array<string, mixed>>,
     *   resumen_debe: float,
     *   resumen_haber: float,
     *   cuadre: array<string, mixed>,
     *   desde_grabacion: bool,
     *   grabado_en: string|null
     * }
     */
    public static function armarPreviewCompleto(array $grabacion): array
    {
        $registros = is_array($grabacion['asientos'] ?? null) ? $grabacion['asientos'] : [];
        $ids = [];
        foreach ($registros as $reg) {
            if (! is_array($reg)) {
                continue;
            }
            $id = (int) ($reg['asiento_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $reg;
            }
        }

        $asientosErp = $ids === []
            ? collect()
            : Asiento::query()
                ->with('asiento_movimientos.cuentacontables')
                ->whereIn('id', array_keys($ids))
                ->get()
                ->keyBy('id');

        $asientos = [];
        $numero = 0;
        foreach ($registros as $reg) {
            if (! is_array($reg)) {
                continue;
            }
            $id = (int) ($reg['asiento_id'] ?? 0);
            $modelo = $id > 0 ? $asientosErp->get($id) : null;
            if (! $modelo instanceof Asiento) {
                continue;
            }
            $numero++;
            $asientos[] = self::asientoDesdeModelo($modelo, $reg, $numero);
        }

        $debe = 0.;
        $haber = 0.;
        foreach ($asientos as $asiento) {
            $debe += (float) ($asiento['resumen_debe'] ?? 0);
            $haber += (float) ($asiento['resumen_haber'] ?? 0);
        }

        $grabadoEn = isset($grabacion['grabado_en']) ? (string) $grabacion['grabado_en'] : null;

        return [
            'asientos' => $asientos,
            'advertencias' => [
                'Mostrando asientos contables ya grabados'
                    .($grabadoEn !== null && $grabadoEn !== '' ? ' ('.$grabadoEn.')' : '')
                    .'. No es un preview recalculado.',
            ],
            'faltantes' => [],
            'cuentas_requeridas' => [],
            'resumen_debe' => round($debe, 2),
            'resumen_haber' => round($haber, 2),
            'cuadre' => self::armarCuadreDesdeGrabados($asientos),
            'desde_grabacion' => true,
            'grabado_en' => $grabadoEn,
        ];
    }

    /**
     * @param  array<string, mixed>  $metaGrabacion
     * @return array<string, mixed>
     */
    public static function asientoDesdeModelo(Asiento $asiento, array $metaGrabacion, int $numeroOrden): array
    {
        $codigo = (string) ($metaGrabacion['codigo'] ?? '');
        $numeroasiento = trim((string) ($asiento->numeroasiento ?? $metaGrabacion['numeroasiento'] ?? ''));
        $tituloBase = trim((string) ($metaGrabacion['titulo'] ?? ''));
        if ($tituloBase === '') {
            $tituloBase = $numeroOrden.' — Asiento grabado';
        }
        // Si el título del snapshot aún tiene numeración vieja "N — …", la alineamos al orden actual.
        if (! preg_match('/^\d+\s*—/', $tituloBase)) {
            $tituloBase = $numeroOrden.' — '.$tituloBase;
        } else {
            $tituloBase = preg_replace('/^\d+\s*—/', $numeroOrden.' —', $tituloBase) ?? $tituloBase;
        }

        $titulo = $tituloBase;
        if ($numeroasiento !== '') {
            $titulo .= ' · N° '.$numeroasiento;
        }

        $lineas = self::lineasDesdeMovimientos($asiento->asiento_movimientos ?? []);
        $debe = 0.;
        $haber = 0.;
        foreach ($lineas as $ln) {
            $debe += (float) ($ln['debe'] ?? 0);
            $haber += (float) ($ln['haber'] ?? 0);
        }

        $resumenDebeMeta = round((float) ($metaGrabacion['resumen_debe'] ?? 0), 2);
        $resumenHaberMeta = round((float) ($metaGrabacion['resumen_haber'] ?? 0), 2);

        return [
            'numero' => $numeroOrden,
            'codigo' => $codigo,
            'titulo' => $titulo,
            'numeroasiento' => $numeroasiento,
            'asiento_id' => (int) $asiento->id,
            'pendiente_ejecucion' => false,
            'desde_grabacion' => true,
            'lineas' => $lineas,
            'resumen_debe' => $debe > 0.0001 ? round($debe, 2) : $resumenDebeMeta,
            'resumen_haber' => $haber > 0.0001 ? round($haber, 2) : $resumenHaberMeta,
            'total' => round(max($debe, $haber, $resumenDebeMeta, $resumenHaberMeta), 2),
        ];
    }

    /**
     * @param  iterable<int, object>  $movimientos
     * @return list<array<string, mixed>>
     */
    public static function lineasDesdeMovimientos(iterable $movimientos): array
    {
        $lineas = [];
        foreach ($movimientos as $mov) {
            $monto = round((float) ($mov->monto ?? 0), 2);
            if (abs($monto) <= 0.0001) {
                continue;
            }
            $cuenta = $mov->cuentacontables ?? null;
            $cuentaId = (int) ($mov->cuentacontable_id ?? 0);
            $codigo = trim((string) ($cuenta->codigo ?? ''));
            $nombre = trim((string) ($cuenta->nombre ?? ''));
            $concepto = trim((string) ($mov->observacion ?? ''));
            if ($concepto === '') {
                $concepto = $nombre !== '' ? $nombre : 'Movimiento contable';
            }

            $debe = $monto > 0 ? $monto : 0.;
            $haber = $monto < 0 ? abs($monto) : 0.;

            $lineas[] = [
                'concepto' => $concepto,
                'cuenta_id' => $cuentaId > 0 ? $cuentaId : null,
                'cuenta_codigo' => $codigo,
                'cuenta_nombre' => $nombre,
                'cuenta_label' => trim($codigo.($nombre !== '' ? ' — '.$nombre : '')),
                'debe' => $debe,
                'haber' => $haber,
            ];
        }

        return $lineas;
    }

    /**
     * Cuadre solo de debe/haber de lo grabado (sin comparar contra Waitry vivo post-emisión).
     *
     * @param  list<array<string, mixed>>  $asientos
     * @return array<string, mixed>
     */
    public static function armarCuadreDesdeGrabados(array $asientos): array
    {
        $tolerancia = 0.02;
        $filas = [];
        $todasCuadran = true;

        foreach ($asientos as $asiento) {
            $debe = round((float) ($asiento['resumen_debe'] ?? 0), 2);
            $haber = round((float) ($asiento['resumen_haber'] ?? 0), 2);
            $total = round((float) ($asiento['total'] ?? max($debe, $haber)), 2);
            $cuadra = abs($debe - $haber) <= $tolerancia;
            if (! $cuadra) {
                $todasCuadran = false;
            }

            $titulo = (string) ($asiento['titulo'] ?? '');
            $nro = trim((string) ($asiento['numeroasiento'] ?? ''));
            if ($nro !== '' && ! str_contains($titulo, 'N° '.$nro)) {
                $titulo .= ' · N° '.$nro;
            }

            $filas[] = [
                'asiento_numero' => (int) ($asiento['numero'] ?? 0),
                'asiento_codigo' => (string) ($asiento['codigo'] ?? ''),
                'asiento_titulo' => $titulo,
                'total_asiento' => $total,
                'debe' => $debe,
                'haber' => $haber,
                'debe_haber_cuadra' => $cuadra,
                'referencia_total' => $total,
                'referencia_etiqueta' => 'Asiento grabado en ERP'
                    .($nro !== '' ? ' (N° '.$nro.')' : ''),
                'diferencia' => 0.,
                'referencia_cuadra' => true,
            ];
        }

        return [
            'filas' => $filas,
            'validaciones' => [],
            'cuadre_global_ok' => $todasCuadran,
            'desde_grabacion' => true,
        ];
    }
}
