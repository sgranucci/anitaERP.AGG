<?php

declare(strict_types=1);

namespace App\Support\Tesoreria\PosicionBancaria;

use App\Models\Caja\Cuentacaja;
use App\Models\Finanzas\FinanzaMovimientoPrecarga;
use App\Support\Configuracion\CotizacionVigenteSupport;
use App\Support\Finanzas\FinanzaMovimientoPrecargaRubro;
use App\Support\Finanzas\FinanzaPosicionHojaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Vuelca las precargas del día a las filas RRHH / SUSS / Descubierto /
 * TRF desde otros bancos / Otras operaciones / TRF intercompany.
 */
final class PosicionBancariaPrecargaVolcadoSupport
{
    /**
     * @return array<string, list<array{
     *   etiqueta: string,
     *   empresa_id: int,
     *   importe: float,
     *   detalle: string,
     *   tono: string
     * }>>
     */
    public function impactosPorHoja(Carbon $fecha): array
    {
        $porHoja = [];
        foreach ($this->impactos($fecha) as $item) {
            $hoja = (string) ($item['hoja'] ?? '');
            if ($hoja === '') {
                continue;
            }
            unset($item['hoja']);
            $porHoja[$hoja][] = $item;
        }

        return $porHoja;
    }

    /**
     * Tablero del día para la pantalla partida (incluye filas vacías del bloque).
     *
     * @return array{
     *   hojas: list<array{nombre: string, filas: list<array<string, mixed>>}>,
     *   movimientos: list<array<string, mixed>>,
     *   sin_hoja: int
     * }
     */
    public function tablero(Carbon $fecha): array
    {
        if (! Schema::hasTable('finanza_movimiento_precarga')) {
            return ['hojas' => [], 'movimientos' => [], 'sin_hoja' => 0];
        }

        $precargas = $this->precargasDelDia($fecha);
        $impactos = $this->impactos($fecha);
        $sinHoja = 0;
        foreach ($precargas as $precarga) {
            if ($this->piernas($precarga) === []) {
                $sinHoja++;
            }
        }

        $hojasNombre = ['Macro', 'Macro (BMA)', 'BAPRO', 'Bi Bank', 'Bind'];
        $hojas = [];
        foreach ($hojasNombre as $nombre) {
            $deHoja = array_values(array_filter(
                $impactos,
                static fn (array $item): bool => ($item['hoja'] ?? '') === $nombre
            ));
            $hojas[] = [
                'nombre' => $nombre,
                'filas' => $this->filasTablero($deHoja),
            ];
        }

        $movimientos = [];
        foreach ($precargas as $precarga) {
            $movimientos[] = [
                'id' => (int) $precarga->id,
                'detalle' => (string) $precarga->detalle,
                'tipo' => $precarga->etiquetaTipo(),
                'rubro' => $precarga->etiquetaRubro(),
                'monto' => (float) $precarga->monto,
                'empresa' => (string) ($precarga->empresa->nombre ?? ''),
                'cuenta' => $precarga->etiquetaCuenta(),
                'estado' => $precarga->etiquetaEstado(),
            ];
        }

        return [
            'hojas' => $hojas,
            'movimientos' => $movimientos,
            'sin_hoja' => $sinHoja,
        ];
    }

    /**
     * @param  list<string>  $conceptos
     * @param  list<array{etiqueta: string, empresa_id: int, importe: float, detalle: string, tono: string}>  $impactos
     * @return list<array{B: ?float, C: ?float, D: ?float, nota: string, tono: string}|null>
     */
    public function asignarAConceptos(array $conceptos, array $impactos): array
    {
        $colas = [];
        foreach ($impactos as $item) {
            $etiqueta = (string) ($item['etiqueta'] ?? '');
            if ($etiqueta === '') {
                continue;
            }
            $colas[$etiqueta][] = $item;
        }

        $restantes = [];
        foreach ($conceptos as $concepto) {
            if ($concepto === 'MOVIMIENTOS DEL DÍA') {
                continue;
            }
            $restantes[$concepto] = ($restantes[$concepto] ?? 0) + 1;
        }

        $out = [];
        foreach ($conceptos as $concepto) {
            if ($concepto === 'MOVIMIENTOS DEL DÍA' || ! isset($colas[$concepto])) {
                $out[] = null;

                continue;
            }
            $restantes[$concepto]--;
            $esUltima = ($restantes[$concepto] ?? 0) <= 0;
            $fila = ['B' => null, 'C' => null, 'D' => null, 'nota' => '', 'tono' => ''];
            $quedan = [];
            foreach ($colas[$concepto] as $item) {
                $col = FinanzaPosicionHojaSupport::COLUMNA_POR_EMPRESA[(int) ($item['empresa_id'] ?? 0)] ?? null;
                if ($col === null) {
                    continue;
                }
                $fila['tono'] = (string) ($item['tono'] ?? $fila['tono']);
                $ocupada = $fila[$col] !== null;
                if ($ocupada && ! $esUltima) {
                    $quedan[] = $item;

                    continue;
                }
                $importe = (float) $item['importe'];
                $fila[$col] = $ocupada ? round((float) $fila[$col] + $importe, 2) : $importe;
                $nota = trim((string) ($item['detalle'] ?? ''));
                if ($nota !== '' && ! str_contains($fila['nota'], $nota)) {
                    $fila['nota'] = trim($fila['nota'].($fila['nota'] !== '' ? ' · ' : '').$nota);
                }
            }
            $colas[$concepto] = $quedan;
            $vacia = $fila['B'] === null && $fila['C'] === null && $fila['D'] === null;
            $out[] = $vacia ? null : $fila;
        }

        return $out;
    }

    /**
     * @return list<array{hoja: string, etiqueta: string, empresa_id: int, importe: float, detalle: string, tono: string}>
     */
    private function impactos(Carbon $fecha): array
    {
        if (! Schema::hasTable('finanza_movimiento_precarga')) {
            return [];
        }

        $out = [];
        foreach ($this->precargasDelDia($fecha) as $precarga) {
            foreach ($this->piernas($precarga) as $pierna) {
                $out[] = $pierna;
            }
        }

        return $out;
    }

    /**
     * @return list<FinanzaMovimientoPrecarga>
     */
    private function precargasDelDia(Carbon $fecha): array
    {
        return FinanzaMovimientoPrecarga::query()
            ->with([
                'empresa:id,nombre',
                'cuentacaja.bancos',
                'cuentacajaDesde.bancos',
                'cuentacajaHasta.bancos',
                'cajaMovimiento:id,numerotransaccion,caja_movimiento_revertido_por_id',
            ])
            ->whereDate('fecha', $fecha->toDateString())
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * @return list<array{hoja: string, etiqueta: string, empresa_id: int, importe: float, detalle: string, tono: string}>
     */
    private function piernas(FinanzaMovimientoPrecarga $precarga): array
    {
        $etiqueta = FinanzaMovimientoPrecargaRubro::etiquetaHoja((string) $precarga->rubro);
        if ($etiqueta === '') {
            return [];
        }
        $pesos = $this->pesos($precarga);
        if (abs($pesos) < 0.00001) {
            return [];
        }
        $tono = (string) $precarga->rubro;
        $detalle = trim((string) $precarga->detalle);

        if ($precarga->tipo === 'transferencia') {
            $out = [];
            $desde = $this->piernaCuenta($precarga->cuentacajaDesde, (int) $precarga->empresa_id, -1 * $pesos, $etiqueta, $detalle, $tono);
            $hasta = $this->piernaCuenta($precarga->cuentacajaHasta, (int) $precarga->empresa_id, $pesos, $etiqueta, $detalle, $tono);
            if ($desde !== null) {
                $out[] = $desde;
            }
            if ($hasta !== null) {
                $out[] = $hasta;
            }

            return $out;
        }

        $signo = $precarga->tipo === 'egreso' ? -1 : 1;
        $pierna = $this->piernaCuenta(
            $precarga->cuentacaja,
            (int) $precarga->empresa_id,
            $signo * $pesos,
            $etiqueta,
            $detalle,
            $tono
        );

        return $pierna === null ? [] : [$pierna];
    }

    /**
     * @return array{hoja: string, etiqueta: string, empresa_id: int, importe: float, detalle: string, tono: string}|null
     */
    private function piernaCuenta(
        ?Cuentacaja $cuenta,
        int $empresaPrecarga,
        float $importe,
        string $etiqueta,
        string $detalle,
        string $tono
    ): ?array {
        $hoja = FinanzaPosicionHojaSupport::hojaDesdeCuenta($cuenta);
        $empresaId = FinanzaPosicionHojaSupport::empresaColumna($cuenta, $empresaPrecarga);
        if ($hoja === null || $empresaId === null) {
            return null;
        }

        return [
            'hoja' => $hoja,
            'etiqueta' => $etiqueta,
            'empresa_id' => $empresaId,
            'importe' => round($importe, 2),
            'detalle' => $detalle,
            'tono' => $tono,
        ];
    }

    private function pesos(FinanzaMovimientoPrecarga $precarga): float
    {
        $monto = abs((float) $precarga->monto);
        if ((int) $precarga->moneda_id <= 1) {
            return round($monto, 2);
        }
        $cot = (float) $precarga->cotizacion;
        if ($cot <= 0) {
            $cot = CotizacionVigenteSupport::ventaValor(
                $precarga->fecha?->format('Y-m-d') ?? date('Y-m-d'),
                (int) $precarga->moneda_id
            );
        }

        return round($monto * ($cot > 0 ? $cot : 0), 2);
    }

    /**
     * @param  list<array{etiqueta: string, empresa_id: int, importe: float, detalle: string, tono: string}>  $impactos
     * @return list<array{etiqueta: string, tono: string, biy: ?float, kan: ?float, reb: ?float, nota: string}>
     */
    private function filasTablero(array $impactos): array
    {
        $conceptos = array_values(FinanzaMovimientoPrecargaRubro::ETIQUETA_HOJA);
        $asignadas = $this->asignarAConceptos($conceptos, $impactos);
        $filas = [];
        foreach ($conceptos as $i => $etiqueta) {
            $asig = $asignadas[$i] ?? null;
            $filas[] = [
                'etiqueta' => $etiqueta,
                'tono' => (string) ($asig['tono'] ?? (FinanzaMovimientoPrecargaRubro::rubroPorEtiquetaHoja()[$etiqueta] ?? '')),
                'biy' => $asig['B'] ?? null,
                'kan' => $asig['C'] ?? null,
                'reb' => $asig['D'] ?? null,
                'nota' => (string) ($asig['nota'] ?? ''),
            ];
        }

        return $filas;
    }
}
