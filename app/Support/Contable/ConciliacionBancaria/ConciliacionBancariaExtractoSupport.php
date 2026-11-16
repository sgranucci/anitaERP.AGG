<?php

declare(strict_types=1);

namespace App\Support\Contable\ConciliacionBancaria;

use App\Models\Contable\ConciliacionBancariaExtracto;
use App\Models\Contable\ConciliacionBancariaExtractoLinea;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Registro formal de extracto (cabecera + líneas) al importar archivo.
 * Las líneas siguen espejándose en interbanking_movimiento para el matcher.
 */
final class ConciliacionBancariaExtractoSupport
{
    /**
     * @param  list<array<string, mixed>>  $movimientos  Formato persistencia IB
     * @return array{extracto: ConciliacionBancariaExtracto, creado: bool, omitido_duplicado: bool}
     */
    public static function registrarDesdeImportacion(
        int $empresaId,
        int $cuentacajaId,
        string $accountNumber,
        string $formato,
        string $archivoOrigen,
        string $rutaArchivo,
        array $movimientos,
        ?int $usuarioId = null,
        ?float $saldoInicial = null,
        ?float $saldoFinal = null,
        bool $reemplazarSiExiste = false,
    ): array {
        $hash = self::hashArchivo($rutaArchivo, $empresaId, $cuentacajaId);

        $existente = ConciliacionBancariaExtracto::query()
            ->where('empresa_id', $empresaId)
            ->where('cuentacaja_id', $cuentacajaId)
            ->where('hash_archivo', $hash)
            ->first();

        if ($existente !== null && ! $reemplazarSiExiste) {
            return [
                'extracto' => $existente,
                'creado' => false,
                'omitido_duplicado' => true,
            ];
        }

        $controles = self::controlesDesdeMovimientos($movimientos);
        $fechaDesde = $controles['fecha_desde'];
        $fechaHasta = $controles['fecha_hasta'];

        return DB::transaction(function () use (
            $existente,
            $empresaId,
            $cuentacajaId,
            $accountNumber,
            $formato,
            $archivoOrigen,
            $hash,
            $movimientos,
            $usuarioId,
            $saldoInicial,
            $saldoFinal,
            $controles,
            $fechaDesde,
            $fechaHasta,
        ) {
            if ($existente !== null) {
                EloquentAuditDeleteSupport::each(
                    ConciliacionBancariaExtractoLinea::query()->where('extracto_id', $existente->id)
                );
                $extracto = $existente;
                $extracto->fill([
                    'account_number' => $accountNumber,
                    'numero_extracto' => self::numeroSugerido($formato, $fechaHasta, $archivoOrigen),
                    'fecha_extracto' => $fechaHasta,
                    'fecha_desde' => $fechaDesde,
                    'fecha_hasta' => $fechaHasta,
                    'saldo_inicial' => $saldoInicial,
                    'saldo_final' => $saldoFinal,
                    'control_debitos' => $controles['debitos'],
                    'control_creditos' => $controles['creditos'],
                    'control_cantidad' => $controles['cantidad'],
                    'formato' => $formato,
                    'archivo_origen' => mb_substr($archivoOrigen, 0, 255),
                    'estado' => 'importado',
                    'usuario_id' => $usuarioId,
                    'meta_json' => ['reimportado_at' => now()->toDateTimeString()],
                ]);
                $extracto->save();
                $creado = false;
            } else {
                $extracto = ConciliacionBancariaExtracto::query()->create([
                    'empresa_id' => $empresaId,
                    'cuentacaja_id' => $cuentacajaId,
                    'account_number' => $accountNumber,
                    'numero_extracto' => self::numeroSugerido($formato, $fechaHasta, $archivoOrigen),
                    'fecha_extracto' => $fechaHasta,
                    'fecha_desde' => $fechaDesde,
                    'fecha_hasta' => $fechaHasta,
                    'saldo_inicial' => $saldoInicial,
                    'saldo_final' => $saldoFinal,
                    'control_debitos' => $controles['debitos'],
                    'control_creditos' => $controles['creditos'],
                    'control_cantidad' => $controles['cantidad'],
                    'moneda' => 'ARS',
                    'formato' => $formato,
                    'archivo_origen' => mb_substr($archivoOrigen, 0, 255),
                    'hash_archivo' => $hash,
                    'estado' => 'importado',
                    'usuario_id' => $usuarioId,
                    'meta_json' => null,
                ]);
                $creado = true;
            }

            $orden = 0;
            foreach ($movimientos as $m) {
                $orden++;
                $dh = strtoupper(substr((string) ($m['debit_credit_type'] ?? ''), 0, 1));
                $importe = abs((float) ($m['amount'] ?? 0));
                if ($importe < 0.00001) {
                    continue;
                }
                $fecha = null;
                if (! empty($m['process_date'])) {
                    try {
                        $fecha = \Carbon\Carbon::parse((string) $m['process_date'])->toDateString();
                    } catch (\Throwable) {
                        $fecha = null;
                    }
                }
                ConciliacionBancariaExtractoLinea::query()->create([
                    'extracto_id' => $extracto->id,
                    'orden' => $orden,
                    'fecha' => $fecha,
                    'dh' => in_array($dh, ['D', 'C'], true) ? $dh : null,
                    'importe' => $importe,
                    'concepto' => mb_substr((string) ($m['code_description_ib'] ?? $m['code_description_bank'] ?? ''), 0, 255) ?: null,
                    'comprobante' => isset($m['voucher_number']) ? mb_substr((string) $m['voucher_number'], 0, 80) : null,
                    'referencia' => isset($m['operation_code_ib']) ? mb_substr((string) $m['operation_code_ib'], 0, 120) : null,
                    'hash_banco' => null,
                    'interbanking_movimiento_id' => null,
                    'raw_json' => $m,
                ]);
            }

            return [
                'extracto' => $extracto->fresh(['lineas']),
                'creado' => $creado,
                'omitido_duplicado' => false,
            ];
        });
    }

    public static function hashArchivo(string $ruta, int $empresaId, int $cuentacajaId): string
    {
        if (! is_file($ruta)) {
            throw new RuntimeException('No se puede hashear el archivo de extracto.');
        }
        $contenido = hash_file('sha256', $ruta);
        if ($contenido === false) {
            throw new RuntimeException('No se pudo leer el archivo de extracto para control de duplicados.');
        }

        return hash('sha256', $empresaId.'|'.$cuentacajaId.'|'.$contenido);
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return array{debitos: float, creditos: float, cantidad: int, fecha_desde: ?string, fecha_hasta: ?string}
     */
    public static function controlesDesdeMovimientos(array $movimientos): array
    {
        $debitos = 0.0;
        $creditos = 0.0;
        $cantidad = 0;
        $min = null;
        $max = null;
        foreach ($movimientos as $m) {
            $importe = abs((float) ($m['amount'] ?? 0));
            if ($importe < 0.00001) {
                continue;
            }
            $cantidad++;
            $dh = strtoupper(substr((string) ($m['debit_credit_type'] ?? ''), 0, 1));
            if ($dh === 'D') {
                $debitos += $importe;
            } else {
                $creditos += $importe;
            }
            if (! empty($m['process_date'])) {
                try {
                    $f = \Carbon\Carbon::parse((string) $m['process_date'])->toDateString();
                    if ($min === null || $f < $min) {
                        $min = $f;
                    }
                    if ($max === null || $f > $max) {
                        $max = $f;
                    }
                } catch (\Throwable) {
                }
            }
        }

        return [
            'debitos' => round($debitos, 2),
            'creditos' => round($creditos, 2),
            'cantidad' => $cantidad,
            'fecha_desde' => $min,
            'fecha_hasta' => $max,
        ];
    }

    private static function numeroSugerido(string $formato, ?string $fechaHasta, string $archivo): string
    {
        $base = $fechaHasta !== null ? str_replace('-', '', $fechaHasta) : date('Ymd');

        return strtoupper($formato).'-'.$base.'-'.substr(md5($archivo), 0, 6);
    }
}
