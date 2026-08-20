<?php

namespace App\Support\Cuentacorriente;

final class CuentacorrienteSaldosPorMoneda
{
    public const EXPRESION_ORIGEN = 'origen';

    public const EXPRESION_PESOS = 'pesos';
    /**
     * @param  iterable<int, array<string, mixed>|object>  $saldos
     * @param  iterable<int, array<string, mixed>|object>  $deudas
     * @return list<array{moneda_id: int, abreviatura: string, saldo_cc: float, deuda: float}>
     */
    public static function consolidar(iterable $saldos, iterable $deudas = []): array
    {
        $porMoneda = [];

        foreach ($saldos as $fila) {
            $item = self::normalizarFila($fila);
            $id = $item['moneda_id'];
            if (! isset($porMoneda[$id])) {
                $porMoneda[$id] = self::filaVacia($id, $item['abreviatura']);
            }
            if ($item['abreviatura'] !== '') {
                $porMoneda[$id]['abreviatura'] = $item['abreviatura'];
            }
            $porMoneda[$id]['saldo_cc'] += $item['saldo_cc'];
            $porMoneda[$id]['deuda'] += $item['deuda'];
        }

        foreach ($deudas as $fila) {
            $item = self::normalizarFila($fila);
            $id = $item['moneda_id'];
            if (! isset($porMoneda[$id])) {
                $porMoneda[$id] = self::filaVacia($id, $item['abreviatura']);
            }
            if ($item['abreviatura'] !== '') {
                $porMoneda[$id]['abreviatura'] = $item['abreviatura'];
            }
            $porMoneda[$id]['deuda'] += $item['deuda'];
        }

        ksort($porMoneda);

        return array_values($porMoneda);
    }

    /**
     * @param  iterable<int, object>  $filas
     * @return list<array{moneda_id: int, abreviatura: string, deuda: float}>
     */
    public static function deudaDesdeFilas(iterable $filas): array
    {
        $porMoneda = [];

        foreach ($filas as $fila) {
            $id = self::monedaIdDe($fila);
            if (! isset($porMoneda[$id])) {
                $porMoneda[$id] = [
                    'moneda_id' => $id,
                    'abreviatura' => self::abreviaturaDe($fila),
                    'deuda' => 0.0,
                ];
            }
            $porMoneda[$id]['deuda'] += abs((float) ($fila->total ?? 0) + (float) ($fila->aplicado ?? 0));
        }

        ksort($porMoneda);

        return array_values($porMoneda);
    }

    /**
     * @param  iterable<int, object>  $filas
     * @return list<array{moneda_id: int, abreviatura: string, total: float}>
     */
    public static function totalesEnPantalla(iterable $filas, callable $importe): array
    {
        $porMoneda = [];

        foreach ($filas as $fila) {
            $id = self::monedaIdDe($fila);
            if (! isset($porMoneda[$id])) {
                $porMoneda[$id] = [
                    'moneda_id' => $id,
                    'abreviatura' => self::abreviaturaDe($fila),
                    'total' => 0.0,
                ];
            }
            $porMoneda[$id]['total'] += (float) $importe($fila);
        }

        ksort($porMoneda);

        return array_values($porMoneda);
    }

    /**
     * @param  iterable<int, object>  $movimientos
     */
    public static function saldoAnteriorDe(iterable $movimientos, object $primerRegistro, ?int $monedaId): float
    {
        $total = 0.0;

        foreach ($movimientos as $movimiento) {
            if ($monedaId !== null && self::monedaIdDe($movimiento) !== $monedaId) {
                continue;
            }

            if (! self::esAnteriorA($movimiento, $primerRegistro)) {
                continue;
            }

            $total += (float) ($movimiento->total ?? 0);
        }

        return $total;
    }

    public static function esAnteriorA(object $movimiento, object $primerRegistro): bool
    {
        $fechaMov = (string) ($movimiento->fecha ?? '');
        $fechaPrimero = (string) ($primerRegistro->fecha ?? '');

        if ($fechaMov === '' || $fechaPrimero === '') {
            return false;
        }

        if ($fechaMov < $fechaPrimero) {
            return true;
        }

        if ($fechaMov > $fechaPrimero) {
            return false;
        }

        return (int) ($movimiento->id ?? 0) < (int) ($primerRegistro->id ?? 0);
    }

    public static function resolverMonedaId(mixed $valor): ?int
    {
        if ($valor === null || $valor === '' || $valor === 'todas' || $valor === '0' || $valor === 0) {
            return null;
        }

        $id = filter_var($valor, FILTER_VALIDATE_INT);
        if ($id === false || (int) $id <= 0) {
            return null;
        }

        return (int) $id;
    }

    public static function valorQuery(?int $monedaId): int|string
    {
        return $monedaId ?? 'todas';
    }

    public static function resolverExpresion(mixed $valor): string
    {
        return $valor === self::EXPRESION_PESOS ? self::EXPRESION_PESOS : self::EXPRESION_ORIGEN;
    }

    public static function esExpresionPesos(string $expresion): bool
    {
        return $expresion === self::EXPRESION_PESOS;
    }

    public static function monedaLocalId(): int
    {
        return (int) config('cotizacion.ID_MONEDA_DEFAULT', 1);
    }

    public static function abreviaturaLocal(): string
    {
        return (string) config('cotizacion.ABREVIATURA_LOCAL', 'ARS');
    }

    public static function cotizacionDe(object $fila): float
    {
        $cotizacion = $fila->cotizacion ?? 1;
        if (is_array($cotizacion)) {
            $cotizacion = $cotizacion['cotizacionventa'] ?? 1;
        }
        $cotizacion = (float) $cotizacion;

        return $cotizacion > 0 ? $cotizacion : 1.0;
    }

    public static function importeEnPesos(object $fila, ?float $importe = null): float
    {
        $monto = $importe ?? (float) ($fila->total ?? 0);
        $monedaId = self::monedaIdDe($fila);
        $localId = self::monedaLocalId();

        if ($monedaId === $localId || $monedaId === 0) {
            return round($monto, 2);
        }

        return round(
            $monto * (float) calculaCoeficienteMoneda($localId, $monedaId, self::cotizacionDe($fila)),
            2
        );
    }

    /**
     * @return array{saldo_cc: float, deuda: float, abreviatura: string}
     */
    public static function equivalenteDesdeFilas(iterable $movimientos, iterable $deudas = []): array
    {
        $saldo = 0.0;
        foreach ($movimientos as $fila) {
            $saldo += self::importeEnPesos($fila);
        }

        $deuda = 0.0;
        foreach ($deudas as $fila) {
            $residual = (float) ($fila->total ?? 0) + (float) ($fila->aplicado ?? 0);
            $deuda += abs(self::importeEnPesos($fila, $residual));
        }

        return [
            'saldo_cc' => $saldo,
            'deuda' => $deuda,
            'abreviatura' => self::abreviaturaLocal(),
        ];
    }

    public static function saldoAnteriorEnPesosDe(iterable $movimientos, object $primerRegistro, ?int $monedaId = null): float
    {
        $total = 0.0;
        foreach ($movimientos as $movimiento) {
            if ($monedaId !== null && self::monedaIdDe($movimiento) !== $monedaId) {
                continue;
            }
            if (! self::esAnteriorA($movimiento, $primerRegistro)) {
                continue;
            }
            $total += self::importeEnPesos($movimiento);
        }

        return $total;
    }

    public static function etiquetaMonedaFila(object $fila, bool $enPesos): string
    {
        $abreviatura = self::abreviaturaDe($fila);
        if (! $enPesos) {
            return $abreviatura;
        }

        $local = self::abreviaturaLocal();
        if (self::monedaIdDe($fila) === self::monedaLocalId()) {
            return $local;
        }

        $origen = $abreviatura !== '' ? $abreviatura : 'ME';

        return $origen.' → '.$local.' · TC '.number_format(self::cotizacionDe($fila), 2, ',', '.');
    }

    /**
     * @return array{total: float, aplicado: float, saldo_pendiente: float, saldo_pendiente_origen: float, saldo_pendiente_pesos: float, abreviatura: string, etiqueta_moneda: string, moneda_id: int}
     */
    public static function importesParaGrilla(object $fila, bool $enPesos, callable $saldoPendienteAbsoluto): array
    {
        $totalOrigen = (float) ($fila->total ?? 0);
        $aplicadoOrigen = (float) ($fila->aplicado ?? 0);
        $pendienteOrigen = (float) $saldoPendienteAbsoluto($totalOrigen, $aplicadoOrigen);
        $pendientePesos = abs(self::importeEnPesos($fila, $pendienteOrigen));

        return [
            'total' => $enPesos ? self::importeEnPesos($fila, $totalOrigen) : $totalOrigen,
            'aplicado' => $enPesos ? self::importeEnPesos($fila, $aplicadoOrigen) : $aplicadoOrigen,
            'saldo_pendiente' => $enPesos ? $pendientePesos : $pendienteOrigen,
            'saldo_pendiente_origen' => $pendienteOrigen,
            'saldo_pendiente_pesos' => $pendientePesos,
            'abreviatura' => $enPesos ? self::abreviaturaLocal() : self::abreviaturaDe($fila),
            'etiqueta_moneda' => self::etiquetaMonedaFila($fila, $enPesos),
            'moneda_id' => self::monedaIdDe($fila),
        ];
    }

    /**
     * @param  iterable<int, object>  $filas
     */
    public static function deudaPantallaEnPesos(iterable $filas, callable $saldoPendienteAbsoluto): float
    {
        $total = 0.0;
        foreach ($filas as $fila) {
            $pendiente = (float) $saldoPendienteAbsoluto((float) ($fila->total ?? 0), $fila->aplicado ?? null);
            $total += abs(self::importeEnPesos($fila, $pendiente));
        }

        return $total;
    }

    /**
     * @return array<int, float>
     */
    public static function saldosAnterioresPorMonedaDe(iterable $movimientos, object $primerRegistro, ?int $monedaId = null): array
    {
        $porMoneda = [];

        foreach ($movimientos as $movimiento) {
            $id = self::monedaIdDe($movimiento);
            if ($monedaId !== null && $id !== $monedaId) {
                continue;
            }
            if (! self::esAnteriorA($movimiento, $primerRegistro)) {
                continue;
            }
            $porMoneda[$id] = ($porMoneda[$id] ?? 0.0) + (float) ($movimiento->total ?? 0);
        }

        return $porMoneda;
    }

    /**
     * @param  array<int, float>  $saldos
     * @return array<int, float>
     */
    public static function acumularSaldoCorrido(array $saldos, int $monedaId, float $total): array
    {
        $saldos[$monedaId] = ($saldos[$monedaId] ?? 0.0) + $total;

        return $saldos;
    }

    public static function acumularSaldoCorridoPesos(float $saldoPesos, object $fila, ?float $totalOrigen = null): float
    {
        return $saldoPesos + self::importeEnPesos($fila, $totalOrigen);
    }

    public static function etiquetaColumnaSaldoPesos(): string
    {
        return 'Saldo '.self::abreviaturaLocal().' (TC)';
    }

    public static function etiquetaColumnaSaldoPendientePesos(): string
    {
        return 'Saldo pend. '.self::abreviaturaLocal().' (TC)';
    }

    public static function formatearMonto(float $monto, string $abreviatura = ''): string
    {
        $abreviatura = trim($abreviatura);
        $numero = number_format($monto, 2, ',', '.');

        return $abreviatura !== '' ? $abreviatura.' '.$numero : $numero;
    }

    /**
     * @param  list<array{moneda_id: int, abreviatura?: string, saldo_cc?: float, deuda?: float}>  $saldosPorMoneda
     */
    public static function mostrarSaldoCorrido(?int $monedaId, array $saldosPorMoneda): bool
    {
        if ($monedaId !== null) {
            return true;
        }

        return count($saldosPorMoneda) <= 1;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public static function formatearResumen(array $items, string $campo = 'saldo_cc'): string
    {
        if ($items === []) {
            return number_format(0, 2, ',', '.');
        }

        $partes = [];
        foreach ($items as $item) {
            $abreviatura = trim((string) ($item['abreviatura'] ?? ''));
            $prefijo = $abreviatura !== '' ? $abreviatura.' ' : '';
            $partes[] = $prefijo.number_format((float) ($item[$campo] ?? 0), 2, ',', '.');
        }

        return implode(' | ', $partes);
    }

    public static function monedaIdDe(object $fila): int
    {
        if (isset($fila->moneda_id)) {
            return (int) $fila->moneda_id;
        }

        if (isset($fila->monedas) && is_object($fila->monedas) && isset($fila->monedas->id)) {
            return (int) $fila->monedas->id;
        }

        return 0;
    }

    public static function abreviaturaDe(object $fila): string
    {
        if (isset($fila->abreviatura) && (string) $fila->abreviatura !== '') {
            return (string) $fila->abreviatura;
        }

        if (isset($fila->abreviaturamoneda) && (string) $fila->abreviaturamoneda !== '') {
            return (string) $fila->abreviaturamoneda;
        }

        if (isset($fila->monedas) && is_object($fila->monedas) && isset($fila->monedas->abreviatura)) {
            return (string) $fila->monedas->abreviatura;
        }

        return '';
    }

    /**
     * @return array{moneda_id: int, abreviatura: string, saldo_cc: float, deuda: float}
     */
    private static function filaVacia(int $monedaId, string $abreviatura): array
    {
        return [
            'moneda_id' => $monedaId,
            'abreviatura' => $abreviatura,
            'saldo_cc' => 0.0,
            'deuda' => 0.0,
        ];
    }

    /**
     * @param  array<string, mixed>|object  $fila
     * @return array{moneda_id: int, abreviatura: string, saldo_cc: float, deuda: float}
     */
    private static function normalizarFila(array|object $fila): array
    {
        if (is_object($fila)) {
            $fila = [
                'moneda_id' => $fila->moneda_id ?? 0,
                'abreviatura' => $fila->abreviatura ?? self::abreviaturaDe($fila),
                'saldo_cc' => $fila->saldo_cc ?? 0,
                'deuda' => $fila->deuda ?? 0,
            ];
        }

        return [
            'moneda_id' => (int) ($fila['moneda_id'] ?? 0),
            'abreviatura' => (string) ($fila['abreviatura'] ?? ''),
            'saldo_cc' => (float) ($fila['saldo_cc'] ?? 0),
            'deuda' => (float) ($fila['deuda'] ?? 0),
        ];
    }
}
