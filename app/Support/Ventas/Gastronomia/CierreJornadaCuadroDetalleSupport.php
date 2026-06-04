<?php

namespace App\Support\Ventas\Gastronomia;

use App\Models\Ventas\VentaGastronomiaEmision;
use App\Support\Ventas\GastronomiaCuentacajaTotem;
use App\Support\Ventas\GastronomiaVentaDetalleSupport;
use App\Support\Ventas\Gastronomia\VentaGastronomiaEmisionWaitrySupport;
use App\Support\Ventas\Waitry\WaitryMedioPagoCuentacajaSupport;
use Carbon\Carbon;

/**
 * Detalle de una celda del cuadro de cierre (fila × medio) para conciliar contra Waitry.
 */
final class CierreJornadaCuadroDetalleSupport
{
    public const FILA_ANITA_JORNADA = 'anita_jornada';

    public const FILA_ANITA_TOTEM = 'anita_totem';

    public const FILA_WAITRY_PAGO = 'waitry_pago';

    public const FILA_WAITRY_IMPAGO = 'waitry_impago';

    public const FILA_WAITRY_CASH = 'waitry_cash';

    /** @var list<string> */
    public const FILAS = [
        self::FILA_ANITA_JORNADA,
        self::FILA_ANITA_TOTEM,
        self::FILA_WAITRY_PAGO,
        self::FILA_WAITRY_IMPAGO,
        self::FILA_WAITRY_CASH,
    ];

    /** @var list<string> */
    public const MEDIOS = ['qr', 'mp', 'efectivo', 'otros'];

    /**
     * @param  list<array<string, mixed>>  $movimientos  Movimientos clasificados del proceso
     * @return array{
     *   ok: bool,
     *   fila: string,
     *   medio: string,
     *   etiqueta_fila: string,
     *   etiqueta_medio: string,
     *   total_importe: float,
     *   total_registros: int,
     *   pagina: int,
     *   por_pagina: int,
     *   total_paginas: int,
     *   items: list<array<string, mixed>>
     * }
     */
    public static function consultar(
        int $empresaId,
        string $fechaJornada,
        string $fila,
        string $medio,
        array $movimientos,
        int $pagina = 1,
        int $porPagina = 100,
    ): array {
        self::validarFilaMedio($fila, $medio);

        $pagina = max(1, $pagina);
        $porPagina = max(10, min(500, $porPagina));

        $items = match ($fila) {
            self::FILA_ANITA_JORNADA, self::FILA_ANITA_TOTEM => self::itemsDesdeEmisionesAnita(
                $empresaId,
                $fechaJornada,
                $fila,
                $medio,
            ),
            default => self::itemsDesdeMovimientosWaitry($movimientos, $fila, $medio),
        };

        usort($items, function (array $a, array $b): int {
            $cmp = strcmp((string) ($a['fecha_hora_raw'] ?? ''), (string) ($b['fecha_hora_raw'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }

            return ((int) ($a['waitry_order_id'] ?? 0)) <=> ((int) ($b['waitry_order_id'] ?? 0));
        });

        $totalRegistros = count($items);
        $totalImporte = round(array_sum(array_map(
            fn (array $it) => (float) ($it['total'] ?? 0),
            $items,
        )), 2);
        $offset = ($pagina - 1) * $porPagina;
        $slice = array_slice($items, $offset, $porPagina);

        return [
            'ok' => true,
            'fila' => $fila,
            'medio' => $medio,
            'etiqueta_fila' => self::etiquetaFila($fila),
            'etiqueta_medio' => self::etiquetaMedio($medio),
            'fecha_jornada' => $fechaJornada,
            'empresa_id' => $empresaId,
            'total_importe' => $totalImporte,
            'total_registros' => $totalRegistros,
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total_paginas' => (int) max(1, (int) ceil($totalRegistros / $porPagina)),
            'items' => $slice,
        ];
    }

    public static function etiquetaFila(string $fila): string
    {
        return match ($fila) {
            self::FILA_ANITA_JORNADA => 'Facturado Anita (jornada)',
            self::FILA_ANITA_TOTEM => 'Facturado Anita — cobro TOTEM (medio real Waitry)',
            self::FILA_WAITRY_PAGO => 'Waitry pagado sin facturar (a facturar)',
            self::FILA_WAITRY_IMPAGO => 'Waitry impago (referencia)',
            self::FILA_WAITRY_CASH => 'Efectivo Waitry — no se factura',
            default => $fila,
        };
    }

    public static function etiquetaMedio(string $medio): string
    {
        return match ($medio) {
            'qr' => 'QR (Totalcoin / tótem)',
            'mp' => 'Mercado Pago',
            'efectivo' => 'Efectivo',
            'otros' => 'Otros',
            default => $medio,
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function itemsDesdeEmisionesAnita(
        int $empresaId,
        string $fechaJornada,
        string $fila,
        string $medio,
    ): array {
        if ($empresaId <= 0 || $fechaJornada === '') {
            return [];
        }

        $emisiones = VentaGastronomiaEmision::query()
            ->with(['venta', 'cuenta', 'waitryComandaEnvio'])
            ->whereHas('venta', function ($q) use ($empresaId, $fechaJornada) {
                $q->whereDate('fechajornada', $fechaJornada)
                    ->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId));
            })
            ->get();

        $totemId = (int) (GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresaId)['id'] ?? 0);
        $esFilaTotem = $fila === self::FILA_ANITA_TOTEM;
        $out = [];

        foreach ($emisiones as $emision) {
            $venta = $emision->venta;
            if ($venta === null) {
                continue;
            }

            $monto = round((float) ($venta->total ?? 0), 2);
            if (abs($monto) <= 0.0001) {
                continue;
            }

            if (($emision->venta_factura_origen_id ?? null) !== null) {
                continue;
            }

            $esTotem = CierreJornadaFacturadoAnitaSupport::esFacturaCobroTotemPublico($emision, $empresaId, $totemId);
            if ($esTotem !== $esFilaTotem) {
                continue;
            }

            $col = CierreJornadaFacturadoAnitaSupport::columnaMedioParaEmisionPublico(
                $emision,
                $empresaId,
                $totemId,
                $esTotem,
            );
            if ($col !== $medio) {
                continue;
            }

            $waitryId = VentaGastronomiaEmisionWaitrySupport::resolverOrderId($emision);
            $waitryTipo = $emision->cuenta?->waitry_tipo_pago;
            $medioAnita = self::primerMedioCobranzaLabel($emision);
            $fechaHora = $venta->created_at;

            $out[] = self::formatearItem([
                'waitry_order_id' => $waitryId > 0 ? $waitryId : null,
                'display_id' => $waitryId > 0 ? '#'.$waitryId : (string) ($venta->codigo ?? ''),
                'fecha_hora' => $fechaHora,
                'placed_at_fmt' => self::formatearFechaHora($fechaHora),
                'total' => $monto,
                'venta_codigo' => (string) ($venta->codigo ?? ''),
                'waitry_tipo_pago' => $waitryTipo,
                'waitry_medio_label' => WaitryMedioPagoCuentacajaSupport::etiquetaTipo($waitryTipo),
                'medio_anita_label' => $medioAnita,
                'medio_cuadro' => $medio,
                'origen' => $fila,
                'facturada_erp' => true,
                'paid_waitry' => null,
                'fuente' => 'anita',
            ]);
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return list<array<string, mixed>>
     */
    private static function itemsDesdeMovimientosWaitry(array $movimientos, string $fila, string $medio): array
    {
        $out = [];

        foreach ($movimientos as $mov) {
            if (! self::movimientoPerteneceCelda($mov, $fila, $medio)) {
                continue;
            }

            $waitryTipo = $mov['waitry_tipo_pago'] ?? null;
            $fechaRaw = $mov['placed_at'] ?? null;

            $out[] = self::formatearItem([
                'waitry_order_id' => (int) ($mov['waitry_order_id'] ?? 0) ?: null,
                'display_id' => (string) ($mov['display_id'] ?? ''),
                'fecha_hora' => $fechaRaw,
                'placed_at_fmt' => (string) ($mov['placed_at_fmt'] ?? self::formatearFechaHora($fechaRaw)),
                'total' => round((float) ($mov['total'] ?? 0), 2),
                'venta_codigo' => (string) ($mov['venta_codigo'] ?? ''),
                'waitry_tipo_pago' => $waitryTipo,
                'waitry_medio_label' => (string) ($mov['waitry_medio_label'] ?? WaitryMedioPagoCuentacajaSupport::etiquetaTipo($waitryTipo)),
                'medio_anita_label' => (string) ($mov['anita_cuentacaja_label'] ?? ''),
                'medio_cuadro' => $medio,
                'origen' => $fila,
                'facturada_erp' => ! empty($mov['facturada_erp']),
                'paid_waitry' => $mov['paid_waitry'] ?? null,
                'fuente' => (string) ($mov['fuente_listado'] ?? 'waitry'),
            ]);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $mov
     */
    private static function movimientoPerteneceCelda(array $mov, string $fila, string $medio): bool
    {
        if (! empty($mov['discrepancia_gap'])) {
            return false;
        }

        $total = round((float) ($mov['total'] ?? 0), 2);
        if ($total <= 0.0001) {
            return false;
        }

        $col = self::columnaDesdeClave((string) ($mov['medio_waitry_clave'] ?? CierreJornadaProcesoMedioSupport::CLAVE_OTRO));

        if ($fila === self::FILA_WAITRY_CASH) {
            return $medio === 'efectivo'
                && CierreJornadaProcesoMedioSupport::esWaitryCash($mov['waitry_tipo_pago'] ?? null)
                && empty($mov['facturada_erp']);
        }

        if (CierreJornadaProcesoMedioSupport::esWaitryCash($mov['waitry_tipo_pago'] ?? null)) {
            return false;
        }

        if ($col !== $medio) {
            return false;
        }

        if (! empty($mov['facturada_erp'])) {
            return false;
        }

        $cobrada = self::cobradaEnWaitry($mov);

        return match ($fila) {
            self::FILA_WAITRY_PAGO => $cobrada,
            self::FILA_WAITRY_IMPAGO => ! $cobrada,
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $mov
     */
    private static function cobradaEnWaitry(array $mov): bool
    {
        if (! empty($mov['waitry_cobro_totem'])) {
            return true;
        }
        if (($mov['paid_waitry'] ?? null) === true) {
            return true;
        }

        return (float) ($mov['monto_cobro_waitry'] ?? 0) > 0.0001;
    }

    private static function columnaDesdeClave(string $clave): string
    {
        return match ($clave) {
            CierreJornadaProcesoMedioSupport::CLAVE_QR => 'qr',
            CierreJornadaProcesoMedioSupport::CLAVE_MP => 'mp',
            CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO => 'efectivo',
            default => 'otros',
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function formatearItem(array $data): array
    {
        $fecha = $data['fecha_hora'] ?? null;
        $raw = '';
        if ($fecha instanceof \DateTimeInterface) {
            $raw = $fecha->format('Y-m-d H:i:s');
        } elseif ($fecha !== null && $fecha !== '') {
            try {
                $raw = Carbon::parse((string) $fecha)->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                $raw = (string) $fecha;
            }
        }

        $data['fecha_hora_raw'] = $raw;
        $data['fecha_hora_fmt'] = $data['placed_at_fmt'] ?? self::formatearFechaHora($fecha);

        return $data;
    }

    private static function formatearFechaHora(mixed $fecha): string
    {
        if ($fecha === null || $fecha === '') {
            return '—';
        }

        try {
            if ($fecha instanceof \DateTimeInterface) {
                return $fecha->format('d/m/Y H:i:s');
            }

            return Carbon::parse((string) $fecha)->format('d/m/Y H:i:s');
        } catch (\Throwable) {
            return (string) $fecha;
        }
    }

    private static function primerMedioCobranzaLabel(VentaGastronomiaEmision $emision): string
    {
        $venta = $emision->venta;
        if ($venta === null) {
            return '';
        }

        $cobranzas = GastronomiaVentaDetalleSupport::cobranzasDeVenta($venta);
        $medios = GastronomiaVentaDetalleSupport::mediosPagoPorCobranza($cobranzas);
        foreach ($medios as $lineas) {
            foreach ($lineas as $medio) {
                $ccId = (int) ($medio->cuentacaja_id ?? 0);
                if ($ccId <= 0) {
                    continue;
                }
                $codigo = trim((string) ($medio->codigo ?? ''));
                $nombre = trim((string) ($medio->nombre ?? ''));

                return $codigo !== '' && $nombre !== ''
                    ? $codigo.' — '.$nombre
                    : ($codigo !== '' ? $codigo : ($nombre !== '' ? $nombre : '#'.$ccId));
            }
        }

        return '';
    }

    private static function validarFilaMedio(string $fila, string $medio): void
    {
        if (! in_array($fila, self::FILAS, true)) {
            throw new \InvalidArgumentException(
                'Fila inválida «'.$fila.'». Valores: '.implode(', ', self::FILAS).'.'
            );
        }

        if (! in_array($medio, self::MEDIOS, true)) {
            throw new \InvalidArgumentException(
                'Medio inválido «'.$medio.'». Valores: '.implode(', ', self::MEDIOS).'.'
            );
        }

        if ($fila === self::FILA_WAITRY_CASH && $medio !== 'efectivo') {
            throw new \InvalidArgumentException('La fila waitry_cash solo admite medio «efectivo».');
        }
    }
}
