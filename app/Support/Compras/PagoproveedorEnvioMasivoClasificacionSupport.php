<?php

declare(strict_types=1);

namespace App\Support\Compras;

/**
 * Clasifica una OP como cheque, transferencia o ambas, y dice si la
 * transferencia persistida de Interbanking quedó asociada con la OP.
 */
final class PagoproveedorEnvioMasivoClasificacionSupport
{
    public const TOLERANCIA_IMPORTE = 1.0;

    /** @var list<string> */
    private const CODIGOS_TRANSFERENCIA = [
        'ATE', 'TMB', 'TMK', 'TMR', 'GPB', 'MEP', 'TC1', 'TCM',
        'BBB', 'CO1', 'CO2', 'CO3', 'CQR', 'CTG', 'IBP',
    ];

    public static function medio(bool $tieneCheque, bool $senialTransferencia, bool $cuentaBancaria): ?string
    {
        if ($senialTransferencia && $tieneCheque) {
            return 'mixto';
        }
        if ($senialTransferencia) {
            return 'transferencia';
        }
        if ($tieneCheque) {
            return 'cheque';
        }
        if ($cuentaBancaria) {
            return 'transferencia';
        }

        return null;
    }

    public static function etiquetaMedio(string $medio): string
    {
        return match ($medio) {
            'cheque' => 'Cheque',
            'transferencia' => 'Transferencia',
            'mixto' => 'Transferencia y cheque',
            default => '',
        };
    }

    public static function filtroIncluye(string $filtro, string $medio): bool
    {
        return match ($filtro) {
            'cheque' => in_array($medio, ['cheque', 'mixto'], true),
            'transferencia' => in_array($medio, ['transferencia', 'mixto'], true),
            default => true,
        };
    }

    /**
     * @param  array<string, mixed>|null  $snap
     */
    public static function snapshotTieneCheques(?array $snap): bool
    {
        if ($snap === null) {
            return false;
        }
        $cheques = $snap['cheques'] ?? null;

        return is_array($cheques) && $cheques !== [];
    }

    /**
     * @param  array<string, mixed>|null  $snap
     */
    public static function snapshotTieneTransferencia(?array $snap): bool
    {
        if ($snap === null) {
            return false;
        }
        $medios = $snap['medios_caja'] ?? null;
        if (! is_array($medios)) {
            return false;
        }
        foreach ($medios as $medio) {
            $texto = is_array($medio) ? (string) ($medio['cuenta'] ?? '') : (string) $medio;
            if (self::textoIndicaTransferencia($texto)) {
                return true;
            }
        }

        return false;
    }

    public static function textoIndicaTransferencia(string $texto): bool
    {
        $upper = strtoupper($texto);
        if (str_contains($upper, 'TRANSFERENCIA')) {
            return true;
        }
        foreach (self::CODIGOS_TRANSFERENCIA as $codigo) {
            if (preg_match('/\b'.preg_quote($codigo, '/').'\b/', $upper) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{estado: string, etiqueta: string}
     */
    public static function evaluarAsociacion(
        string $medio,
        ?int $transferenciaId,
        bool $transferenciaExiste,
        int $empresaOpId,
        int $empresaTransferenciaId,
        float $importeTransferencia,
        float $netoOp,
        float $brutoOp,
        string $cbuOp,
        string $cbuCredito,
    ): array {
        if ($medio === 'cheque') {
            return self::estado('no_aplica');
        }

        if ($transferenciaId === null || $transferenciaId <= 0) {
            return self::estado('sin_vincular');
        }
        if (! $transferenciaExiste) {
            return self::estado('no_encontrada');
        }
        if ($empresaTransferenciaId !== $empresaOpId) {
            return self::estado('otra_empresa');
        }

        $importeOk = self::importesCoinciden($importeTransferencia, $netoOp, $brutoOp);
        $cbuOk = self::cbusCoinciden($cbuOp, $cbuCredito);
        if (! $importeOk && ! $cbuOk) {
            return self::estado('importe_y_cbu');
        }
        if (! $importeOk) {
            return self::estado('importe_distinto');
        }
        if (! $cbuOk) {
            return self::estado('cbu_distinto');
        }

        return self::estado('ok');
    }

    /**
     * Busca la transferencia en las persistidas de Interbanking cuando la OP
     * no tiene el id cargado. Coincide importe (neto o bruto) y CBU o CUIT.
     *
     * @param  array{neto: float, bruto: float, cbu: string, cuit: string, fecha: string}  $op
     * @param  list<array{id: int, amount: float, cbu: string, cuit: string, fecha: string}>  $candidatos
     * @param  array<int, true>  $usadas
     * @return array{estado: string, etiqueta: string, transferencia_id: int|null}
     */
    public static function elegirPersistida(array $op, array $candidatos, array $usadas): array
    {
        $porImporte = [];
        foreach ($candidatos as $candidato) {
            $id = (int) $candidato['id'];
            if (isset($usadas[$id])) {
                continue;
            }
            if (! self::importesCoinciden((float) $candidato['amount'], (float) $op['neto'], (float) $op['bruto'])) {
                continue;
            }
            $porImporte[] = $candidato;
        }

        $conIdentidad = array_values(array_filter(
            $porImporte,
            fn (array $candidato) => self::identidadCoincide(
                (string) $op['cbu'],
                (string) $op['cuit'],
                (string) $candidato['cbu'],
                (string) $candidato['cuit']
            )
        ));

        if ($conIdentidad !== []) {
            return self::unicoMasCercano((string) $op['fecha'], $conIdentidad);
        }

        if (count($porImporte) === 1) {
            $estado = self::estado('solo_importe');

            return $estado + ['transferencia_id' => (int) $porImporte[0]['id']];
        }

        return self::estado('sin_vincular') + ['transferencia_id' => null];
    }

    public static function adjuntaComprobante(string $estado): bool
    {
        return in_array($estado, ['ok', 'importe_distinto', 'cbu_distinto', 'importe_y_cbu'], true);
    }

    public static function seleccionadaPorDefecto(bool $tieneEmail, string $medio, string $estado): bool
    {
        if (! $tieneEmail) {
            return false;
        }
        if ($medio === 'cheque') {
            return true;
        }

        return $estado === 'ok';
    }

    public static function claseBadge(string $estado): string
    {
        return match ($estado) {
            'ok' => 'success',
            'no_aplica' => 'secondary',
            'importe_distinto', 'cbu_distinto', 'importe_y_cbu', 'ambigua', 'solo_importe' => 'warning',
            default => 'danger',
        };
    }

    /**
     * @return array{estado: string, etiqueta: string}
     */
    private static function estado(string $codigo): array
    {
        $etiquetas = [
            'ok' => 'Asociada',
            'sin_vincular' => 'Sin vincular',
            'no_encontrada' => 'No encontrada',
            'otra_empresa' => 'Otra empresa',
            'importe_distinto' => 'Importe distinto',
            'cbu_distinto' => 'CBU distinto',
            'importe_y_cbu' => 'Importe y CBU distintos',
            'ambigua' => 'Varias coincidencias',
            'solo_importe' => 'Solo importe',
            'no_aplica' => 'No aplica',
        ];

        return [
            'estado' => $codigo,
            'etiqueta' => $etiquetas[$codigo] ?? $codigo,
        ];
    }

    private static function importesCoinciden(float $transferencia, float $neto, float $bruto): bool
    {
        return abs($transferencia - $neto) <= self::TOLERANCIA_IMPORTE
            || abs($transferencia - $bruto) <= self::TOLERANCIA_IMPORTE;
    }

    private static function cbusCoinciden(string $cbuOp, string $cbuCredito): bool
    {
        $op = CbuSupport::normalizar($cbuOp);
        $credito = CbuSupport::normalizar($cbuCredito);
        if (strlen($op) !== 22 || strlen($credito) !== 22) {
            return true;
        }

        return $op === $credito;
    }

    private static function identidadCoincide(string $cbuOp, string $cuitOp, string $cbuBanco, string $cuitBanco): bool
    {
        $cbuOp = CbuSupport::normalizar($cbuOp);
        $cbuBanco = CbuSupport::normalizar($cbuBanco);
        if (strlen($cbuOp) === 22 && strlen($cbuBanco) === 22 && $cbuOp === $cbuBanco) {
            return true;
        }

        $cuitOp = preg_replace('/\D+/', '', $cuitOp) ?? '';
        $cuitBanco = preg_replace('/\D+/', '', $cuitBanco) ?? '';

        return strlen($cuitOp) >= 11 && $cuitOp === $cuitBanco;
    }

    /**
     * @param  list<array{id: int, amount: float, cbu: string, cuit: string, fecha: string}>  $candidatos
     * @return array{estado: string, etiqueta: string, transferencia_id: int|null}
     */
    private static function unicoMasCercano(string $fechaOp, array $candidatos): array
    {
        $distancia = [];
        foreach ($candidatos as $candidato) {
            $id = (int) $candidato['id'];
            $distancia[$id] = self::diasEntre($fechaOp, (string) $candidato['fecha']);
        }
        $minima = min($distancia);
        $mejores = array_values(array_filter(
            $candidatos,
            fn (array $candidato) => $distancia[(int) $candidato['id']] === $minima
        ));
        if (count($mejores) === 1) {
            return self::estado('ok') + ['transferencia_id' => (int) $mejores[0]['id']];
        }

        return self::estado('ambigua') + ['transferencia_id' => null];
    }

    private static function diasEntre(string $desde, string $hasta): int
    {
        $a = strtotime($desde.' 00:00:00');
        $b = strtotime($hasta.' 00:00:00');
        if ($a === false || $b === false) {
            return 99;
        }

        return (int) abs(round(($a - $b) / 86400));
    }
}
