<?php

declare(strict_types=1);

namespace App\Support\Ventas\TransferenciaCrown;

use App\Models\Contable\Asiento;
use App\Support\Contable\AsientoBalanceSupport;
use App\Support\Contable\AsientoCentrocostoObligatorioSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Control de incongruencias de las transferencias Crown (config + importación).
 */
final class TransferenciaCrownControlSupport
{
    public const TIPOS = [
        'pv_sin_centro' => 'Punto de venta sin centro de costo',
        'pv_sin_cuenta' => 'Punto de venta sin cuenta automática',
        'asiento_desbalanceado' => 'Asiento desbalanceado',
        'movimiento_sin_cc' => 'Cuenta con CC obligatorio sin centro',
        'venta_sin_asiento' => 'Factura transferida sin asiento',
        'asiento_sin_venta' => 'Asiento de transferencia sin factura',
    ];

    /**
     * Control automático luego de generar/enganchar (gastro o ARCA).
     *
     * @param  list<array<string, mixed>>  $filasImportadas
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   resumen: array<string, int>,
     *   filtros: array<string, mixed>
     * }
     */
    public static function analizarLuegoDeImportar(
        string $origen,
        string $desde,
        string $hasta,
        int $empresaId = 1,
        array $filasImportadas = [],
    ): array {
        if ($desde === '' || $hasta === '') {
            [$desde, $hasta] = self::rangoFechasDesdeFilas($filasImportadas);
        }
        $origen = match ($origen) {
            'arca', 'pxsol' => 'arca',
            'gastro', 'gastronomia' => 'gastro',
            default => 'todos',
        };

        return self::analizar([
            'empresa_id' => $empresaId,
            'desde' => $desde,
            'hasta' => $hasta,
            'alcance' => 'todo',
            'origen' => $origen,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{0: string, 1: string}
     */
    public static function rangoFechasDesdeFilas(array $filas): array
    {
        $fechas = [];
        foreach ($filas as $fila) {
            $fecha = trim((string) ($fila['fecha'] ?? ''));
            if ($fecha !== '') {
                $fechas[] = $fecha;
            }
        }
        sort($fechas);
        $hoy = now()->format('Y-m-d');
        if ($fechas === []) {
            return [$hoy, $hoy];
        }

        return [$fechas[0], $fechas[array_key_last($fechas)]];
    }

    /**
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   resumen: array<string, int>,
     *   filtros: array<string, mixed>
     * }
     */
    public static function analizar(array $filtros): array
    {
        $empresaId = max(1, (int) ($filtros['empresa_id'] ?? 1));
        $alcance = (string) ($filtros['alcance'] ?? 'todo');
        $origen = (string) ($filtros['origen'] ?? 'todos');
        $desde = (string) ($filtros['desde'] ?? '');
        $hasta = (string) ($filtros['hasta'] ?? '');

        $filas = [];
        if (in_array($alcance, ['todo', 'config'], true)) {
            $filas = array_merge($filas, self::incongruenciasConfig($empresaId, $origen));
        }
        if (in_array($alcance, ['todo', 'importacion'], true) && $desde !== '' && $hasta !== '') {
            $filas = array_merge($filas, self::incongruenciasImportacion($empresaId, $desde, $hasta, $origen));
        }

        usort($filas, static function (array $a, array $b): int {
            return [$a['severidad'] === 'error' ? 0 : 1, $a['tipo'], $a['referencia']]
                <=> [$b['severidad'] === 'error' ? 0 : 1, $b['tipo'], $b['referencia']];
        });

        $resumen = [
            'total' => count($filas),
            'errores' => 0,
            'avisos' => 0,
        ];
        foreach (array_keys(self::TIPOS) as $tipo) {
            $resumen[$tipo] = 0;
        }
        foreach ($filas as $fila) {
            $resumen[$fila['tipo']] = (int) ($resumen[$fila['tipo']] ?? 0) + 1;
            if (($fila['severidad'] ?? '') === 'error') {
                $resumen['errores']++;
            } else {
                $resumen['avisos']++;
            }
        }

        return [
            'filas' => $filas,
            'resumen' => $resumen,
            'filtros' => [
                'empresa_id' => $empresaId,
                'alcance' => $alcance,
                'origen' => $origen,
                'desde' => $desde,
                'hasta' => $hasta,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function incongruenciasConfig(int $empresaId, string $origen): array
    {
        $out = [];
        $imputaciones = TransferenciaCrownImputacionSupport::listar($empresaId);
        $perfiles = match ($origen) {
            'gastro' => ['gastro'],
            'arca' => ['arca'],
            default => ['gastro', 'arca'],
        };

        foreach ($imputaciones as $fila) {
            $ref = $fila['codigo'].' — '.$fila['nombre'];
            $tieneCc = (int) ($fila['centrocosto']['id'] ?? 0) > 0;
            $perfilesConCuenta = [];
            foreach ($perfiles as $perfil) {
                foreach (array_keys(TransferenciaCrownCuentaClaves::ROLES[$perfil] ?? []) as $rol) {
                    $input = TransferenciaCrownCuentaClaves::inputRol($perfil, $rol);
                    if ((int) ($fila['cuentas'][$input]['id'] ?? 0) > 0) {
                        $perfilesConCuenta[$perfil] = true;
                        break;
                    }
                }
            }
            if ($perfilesConCuenta === [] && ! $tieneCc) {
                continue;
            }

            if ($perfilesConCuenta !== [] && ! $tieneCc) {
                $out[] = self::fila(
                    'pv_sin_centro',
                    'aviso',
                    $ref,
                    'El punto de venta no tiene centro de costo. Las cuentas que manejan CC van a salir sin centro.',
                    null,
                    ['sucursal' => (int) $fila['sucursal']],
                );
            }

            // Solo perfiles que el PV ya usa (alguna cuenta cargada). No exige ARCA en una sala de gastro.
            foreach (array_keys($perfilesConCuenta) as $perfil) {
                foreach (['venta', 'efectivo'] as $rol) {
                    $input = TransferenciaCrownCuentaClaves::inputRol($perfil, $rol);
                    $cuentaId = (int) ($fila['cuentas'][$input]['id'] ?? 0);
                    if ($cuentaId > 0) {
                        continue;
                    }
                    $etiqueta = TransferenciaCrownCuentaClaves::ROLES[$perfil][$rol]['etiqueta'] ?? $rol;
                    $proceso = $perfil === 'arca' ? 'ARCA' : 'gastronomía';
                    $out[] = self::fila(
                        'pv_sin_cuenta',
                        'error',
                        $ref,
                        'Falta la cuenta de '.$etiqueta.' de '.$proceso.'.',
                        null,
                        ['sucursal' => (int) $fila['sucursal'], 'perfil' => $perfil, 'rol' => $rol],
                    );
                }
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function incongruenciasImportacion(int $empresaId, string $desde, string $hasta, string $origen): array
    {
        $out = [];
        $desde = Carbon::parse($desde)->format('Y-m-d');
        $hasta = Carbon::parse($hasta)->format('Y-m-d');

        $asientos = Asiento::query()
            ->where('empresa_id', $empresaId)
            ->whereBetween('fecha', [$desde, $hasta])
            ->where(function ($q) use ($origen): void {
                if ($origen === 'arca') {
                    $q->where('observacion', 'like', 'Importación ARCA%');
                } elseif ($origen === 'gastro') {
                    $q->where('observacion', 'like', 'Transferencia gastronomía%');
                } else {
                    $q->where('observacion', 'like', 'Transferencia gastronomía%')
                        ->orWhere('observacion', 'like', 'Importación ARCA%');
                }
            })
            ->orderBy('fecha')
            ->orderBy('id')
            ->get(['id', 'numeroasiento', 'fecha', 'venta_id', 'observacion', 'anita_origen', 'anita_sucursal', 'anita_tipo', 'anita_letra', 'anita_nro']);

        $asientoIds = $asientos->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $saldos = [];
        $movimientos = collect();
        if ($asientoIds !== []) {
            $saldos = DB::table('asiento_movimiento')
                ->selectRaw('asiento_id, ROUND(SUM(monto), 2) as saldo, COUNT(*) as lineas')
                ->whereIn('asiento_id', $asientoIds)
                ->groupBy('asiento_id')
                ->get()
                ->keyBy('asiento_id');

            $movimientos = DB::table('asiento_movimiento as m')
                ->join('cuentacontable as c', 'c.id', '=', 'm.cuentacontable_id')
                ->whereIn('m.asiento_id', $asientoIds)
                ->where(function ($q): void {
                    $q->where('c.manejaccosto', 'S')->orWhere('c.manejaccosto', '1');
                })
                ->where(function ($q): void {
                    $q->whereNull('m.centrocosto_id')->orWhere('m.centrocosto_id', 0);
                })
                ->whereRaw('ABS(m.monto) > 0')
                ->get([
                    'm.asiento_id',
                    'm.id as movimiento_id',
                    'm.monto',
                    'c.codigo as cuenta_codigo',
                    'c.nombre as cuenta_nombre',
                    'c.manejaccosto',
                ]);
        }

        $ventaIdsConAsiento = [];
        foreach ($asientos as $asiento) {
            $ref = 'Asiento '.$asiento->numeroasiento.' ('.$asiento->fecha.')';
            if ((int) ($asiento->anita_sucursal ?? 0) > 0 && (int) ($asiento->anita_nro ?? 0) > 0) {
                $ref .= ' · '.trim((string) $asiento->anita_tipo).' '.trim((string) $asiento->anita_letra)
                    .' '.str_pad((string) (int) $asiento->anita_sucursal, 5, '0', STR_PAD_LEFT)
                    .'-'.str_pad((string) (int) $asiento->anita_nro, 8, '0', STR_PAD_LEFT);
            }

            if ((int) ($asiento->venta_id ?? 0) <= 0) {
                $out[] = self::fila(
                    'asiento_sin_venta',
                    'error',
                    $ref,
                    'El asiento de transferencia no está enganchado a una factura.',
                    null,
                    ['asiento_id' => (int) $asiento->id],
                );
            } else {
                $ventaIdsConAsiento[(int) $asiento->venta_id] = true;
            }

            $saldo = (float) ($saldos[$asiento->id]->saldo ?? 0);
            $lineas = (int) ($saldos[$asiento->id]->lineas ?? 0);
            if ($lineas === 0 || abs($saldo) > AsientoBalanceSupport::TOLERANCIA) {
                $out[] = self::fila(
                    'asiento_desbalanceado',
                    'error',
                    $ref,
                    $lineas === 0
                        ? 'El asiento no tiene movimientos.'
                        : 'El asiento no balancea. Diferencia (Debe−Haber firmada): '.number_format($saldo, 2, ',', '.').'.',
                    $saldo,
                    ['asiento_id' => (int) $asiento->id],
                );
            }
        }

        $asientosPorId = $asientos->keyBy('id');
        foreach ($movimientos as $mov) {
            if (! AsientoCentrocostoObligatorioSupport::maneja($mov->manejaccosto ?? null)) {
                continue;
            }
            $asiento = $asientosPorId->get($mov->asiento_id);
            if ($asiento === null) {
                continue;
            }
            $ref = 'Asiento '.$asiento->numeroasiento.' · cuenta '.$mov->cuenta_codigo;
            $out[] = self::fila(
                'movimiento_sin_cc',
                'error',
                $ref,
                'La cuenta '.$mov->cuenta_codigo.' — '.$mov->cuenta_nombre.' maneja centro de costo y la línea quedó sin centro.',
                (float) $mov->monto,
                [
                    'asiento_id' => (int) $asiento->id,
                    'movimiento_id' => (int) $mov->movimiento_id,
                    'cuentacontable_codigo' => (string) $mov->cuenta_codigo,
                ],
            );
        }

        $ventasQ = DB::table('venta as v')
            ->leftJoin('asiento as a', 'a.venta_id', '=', 'v.id')
            ->leftJoin('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->where(function ($q) use ($empresaId): void {
                $q->where('pv.empresa_id', $empresaId)
                    ->orWhereNull('v.puntoventa_id');
            })
            ->whereBetween('v.fecha', [$desde, $hasta])
            ->whereNull('a.id')
            ->where(function ($q) use ($origen): void {
                if ($origen === 'arca') {
                    $q->where('v.leyenda', 'like', 'Importación ARCA%');
                } elseif ($origen === 'gastro') {
                    $q->where('v.leyenda', 'like', 'Transferencia gastronomía%');
                } else {
                    $q->where('v.leyenda', 'like', 'Transferencia gastronomía%')
                        ->orWhere('v.leyenda', 'like', 'Importación ARCA%');
                }
            })
            ->orderBy('v.fecha')
            ->orderBy('v.id')
            ->limit(1500)
            ->get(['v.id', 'v.codigo', 'v.fecha', 'v.total', 'v.leyenda']);

        foreach ($ventasQ as $venta) {
            if (isset($ventaIdsConAsiento[(int) $venta->id])) {
                continue;
            }
            $out[] = self::fila(
                'venta_sin_asiento',
                'error',
                (string) $venta->codigo,
                'La factura transferida el '.$venta->fecha.' no tiene asiento enganchado.',
                (float) $venta->total,
                ['venta_id' => (int) $venta->id],
            );
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function fila(
        string $tipo,
        string $severidad,
        string $referencia,
        string $detalle,
        ?float $importe,
        array $extra = [],
    ): array {
        return array_merge([
            'tipo' => $tipo,
            'tipo_label' => self::TIPOS[$tipo] ?? $tipo,
            'severidad' => $severidad,
            'referencia' => $referencia,
            'detalle' => $detalle,
            'importe' => $importe,
        ], $extra);
    }
}
