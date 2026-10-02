<?php

namespace App\Support\Stock;

use App\Models\Stock\Recuento;
use App\Repositories\Stock\Articulo_Saldo_DepositoRepositoryInterface;
use App\Support\Contable\PeriodoContableCierreSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

final class RecuentoModoCierreSupport
{
    public const MODO_FECHA_RECUENTO = 'FECHA_RECUENTO';

    public const MODO_SALDO_ACTUAL = 'SALDO_ACTUAL';

    /** @var list<string> */
    public const MODOS_VALIDOS = [
        self::MODO_FECHA_RECUENTO,
        self::MODO_SALDO_ACTUAL,
    ];

    public static function resolverModo(?string $modo): string
    {
        $modo = strtoupper(trim((string) $modo));

        return in_array($modo, self::MODOS_VALIDOS, true)
            ? $modo
            : self::MODO_SALDO_ACTUAL;
    }

    public static function modoPorDefecto(Recuento $recuento): string
    {
        $fecha = $recuento->fecha;
        if ($fecha instanceof Carbon && $fecha->copy()->startOfDay()->lt(now()->startOfDay())) {
            return self::MODO_FECHA_RECUENTO;
        }

        return self::MODO_SALDO_ACTUAL;
    }

    /** Días de antigüedad de la fecha del recuento respecto de hoy (0 si es hoy o futura). */
    public static function diasAntiguedadFecha(?Carbon $fechaRecuento): int
    {
        if (! $fechaRecuento) {
            return 0;
        }

        $hoy = now()->startOfDay();
        $fecha = $fechaRecuento->copy()->startOfDay();
        if ($fecha->gte($hoy)) {
            return 0;
        }

        return (int) $fecha->diffInDays($hoy);
    }

    public static function diasAvisoFechaAntigua(): int
    {
        return max(1, (int) config('stock.recuento_dias_aviso_fecha_antigua', 3));
    }

    public static function diasBloqueoFechaAntigua(): int
    {
        return max(1, (int) config('stock.recuento_dias_bloqueo_fecha_antigua', 15));
    }

    public static function anioMinimoFecha(): int
    {
        return max(1900, (int) config('stock.recuento_fecha_anio_minimo', 2000));
    }

    public static function anioMaximoFecha(): int
    {
        return (int) now()->year + max(0, (int) config('stock.recuento_fecha_anios_futuro', 1));
    }

    /**
     * Null si la fecha se puede grabar. Rechaza años imposibles (p. ej. 0026 por tipear 26).
     */
    public static function mensajeFechaNoGrabable(mixed $fecha): ?string
    {
        if ($fecha === null || $fecha === '') {
            return null;
        }

        try {
            $carbon = $fecha instanceof Carbon ? $fecha->copy() : Carbon::parse((string) $fecha);
        } catch (\Throwable) {
            return 'La fecha del recuento no es válida.';
        }

        $anio = (int) $carbon->year;
        $min = self::anioMinimoFecha();
        $max = self::anioMaximoFecha();
        if ($anio >= $min && $anio <= $max) {
            return null;
        }

        return 'La fecha del recuento ('.$carbon->format('d/m/Y').') no es válida. '
            .'Indique el día real del conteo con el año completo de 4 dígitos, entre '.$min.' y '.$max
            .' (por ejemplo '.now()->format('d/m/Y').').';
    }

    public static function debeAvisarFechaAntigua(?Carbon $fechaRecuento): bool
    {
        return self::diasAntiguedadFecha($fechaRecuento) >= self::diasAvisoFechaAntigua();
    }

    public static function bloqueaCierrePorFechaAntigua(?Carbon $fechaRecuento, string $modoCierre): bool
    {
        if (self::resolverModo($modoCierre) !== self::MODO_FECHA_RECUENTO) {
            return false;
        }

        return self::diasAntiguedadFecha($fechaRecuento) >= self::diasBloqueoFechaAntigua();
    }

    /**
     * Fecha del movimiento de cierre (o la del recuento si no hubo ajuste).
     * Es la que se compara con el cierre contable del módulo de stock.
     */
    public static function fechaOperacionCierre(Recuento $recuento): ?Carbon
    {
        $movimiento = $recuento->relationLoaded('movimientoCierre')
            ? $recuento->movimientoCierre
            : ($recuento->movimientostock_cierre_id ? $recuento->movimientoCierre()->first() : null);

        if ($movimiento && $movimiento->fecha) {
            return Carbon::parse($movimiento->fecha)->startOfDay();
        }

        return $recuento->fecha ? $recuento->fecha->copy()->startOfDay() : null;
    }

    /**
     * Null si se puede anular. La fecha del movimiento de cierre no puede caer
     * dentro del cierre contable de stock (general, módulo o movimientos).
     * Una apertura programada vigente lo habilita. El permiso de operar en
     * período cerrado no alcanza: anular reescribe el stock de ese período.
     */
    public static function mensajeAnulacionBloqueadaPorCierreContable(Recuento $recuento): ?string
    {
        $fecha = self::fechaOperacionCierre($recuento);
        if (! $fecha) {
            return null;
        }

        $empresaId = (int) $recuento->empresa_id;
        $alcance = PeriodoContableCierreSupport::ALCANCE_STOCK;
        if (! PeriodoContableCierreSupport::fechaEnPeriodoCerrado($empresaId, $fecha->toDateString(), $alcance)) {
            return null;
        }

        $usuarioId = (int) (Auth::id() ?? 0);
        if ($usuarioId > 0 && PeriodoContableCierreSupport::tieneAperturaActiva($empresaId, $usuarioId, $fecha, $alcance)) {
            return null;
        }

        $fechaCierre = PeriodoContableCierreSupport::fechaCierreVigente($empresaId, $alcance);
        if (! $fechaCierre) {
            return null;
        }

        return 'No se puede anular el cierre. '.PeriodoContableCierreSupport::mensajeBloqueo(
            $fecha,
            $fechaCierre,
            $alcance,
            PeriodoContableCierreSupport::detalleAperturaUsuario($empresaId, $usuarioId, $fecha, $alcance)
        );
    }

    public static function mensajeBloqueoFechaAntigua(Recuento $recuento): string
    {
        $dias = self::diasAntiguedadFecha($recuento->fecha);
        $fechaFmt = optional($recuento->fecha)->format('d/m/Y') ?: '—';
        $max = self::diasBloqueoFechaAntigua();

        return "No se puede cerrar «a fecha del recuento» con fecha {$fechaFmt} "
            ."({$dias} días atrás; máximo permitido {$max}). "
            .'Corrija la fecha del recuento (debe coincidir con el día del conteo) '
            .'o cierre con el modo «Al saldo actual». '
            .'Una fecha vieja reaplica el ajuste histórico y deja mal el stock vigente '
            .'si ya hubo movimientos posteriores.';
    }

    public static function mensajeAvisoFechaAntigua(Recuento $recuento): string
    {
        $dias = self::diasAntiguedadFecha($recuento->fecha);
        $fechaFmt = optional($recuento->fecha)->format('d/m/Y') ?: '—';

        return "La fecha del recuento ({$fechaFmt}) tiene {$dias} días de antigüedad. "
            .'Verifique que sea el día real del conteo. Si el conteo refleja el stock de hoy, '
            .'use «Al saldo actual»; si pone una fecha vieja por error, el cierre puede dejar saldos negativos.';
    }

    public static function etiqueta(?string $modo): string
    {
        return match (self::resolverModo($modo)) {
            self::MODO_FECHA_RECUENTO => 'A fecha del recuento',
            self::MODO_SALDO_ACTUAL => 'Al saldo actual',
            default => (string) $modo,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function textosImplicancias(): array
    {
        return [
            self::MODO_FECHA_RECUENTO => 'Compara lo contado con el saldo del sistema calculado a la fecha del recuento '
                .'(suma de movimientos con fecha ≤ fecha del recuento). '
                .'El ajuste se registra con esa fecha. '
                .'Use esta opción si el conteo corresponde a un cierre de período (ej. inventario al 31/5) '
                .'y hubo movimientos de stock posteriores: el stock vigente quedará coherente con '
                .'“conteo correcto en esa fecha + movimientos posteriores”. '
                .'No se permite cerrar si ese resultado vigente quedaría negativo.',
            self::MODO_SALDO_ACTUAL => 'Compara lo contado con el saldo vigente hoy en el depósito. '
                .'El ajuste se registra con la fecha de hoy. '
                .'Use esta opción si el conteo refleja lo que hay físicamente ahora y desea '
                .'igualar el sistema al conteo actual, sin reconstruir el saldo histórico.',
        ];
    }

    /**
     * Saldo vigente que quedaría tras cerrar a fecha: contado + movimientos con fecha > fecha recuento.
     */
    public static function saldoVigenteTrasCierreAFecha(
        Articulo_Saldo_DepositoRepositoryInterface $saldoRepository,
        int $articuloId,
        int $depositoId,
        string $fechaRecuento,
        float $cantidadContada,
        ?int $colorId = null,
        ?int $talleId = null,
    ): float {
        [$colorKey, $talleKey] = ArticuloStockColorTalleSupport::claveSaldo($colorId, $talleId);
        $posterior = $saldoRepository->sumaVariantePosteriorAFecha(
            $articuloId,
            $depositoId,
            $fechaRecuento,
            $colorKey > 0 ? $colorKey : null,
            $talleKey > 0 ? $talleKey : null
        );

        return $cantidadContada + $posterior;
    }

    /**
     * Detecta variantes que quedarían con saldo vigente negativo tras un cierre FECHA_RECUENTO.
     *
     * @param  list<array{articulo_id:int, color_id?:int, talle_id?:int, cantidad_contada:float, sku?:string}>  $lineas
     * @return list<array{articulo_id:int, sku:string, contado:float, posterior:float, vigente:float}>
     */
    public static function lineasConSaldoVigenteNegativoTrasCierreFecha(
        Articulo_Saldo_DepositoRepositoryInterface $saldoRepository,
        int $depositoId,
        string $fechaRecuento,
        array $lineas,
    ): array {
        $conflictos = [];
        foreach ($lineas as $linea) {
            $articuloId = (int) $linea['articulo_id'];
            $colorId = isset($linea['color_id']) ? (int) $linea['color_id'] : 0;
            $talleId = isset($linea['talle_id']) ? (int) $linea['talle_id'] : 0;
            $contado = (float) $linea['cantidad_contada'];
            [$colorKey, $talleKey] = ArticuloStockColorTalleSupport::claveSaldo(
                $colorId > 0 ? $colorId : null,
                $talleId > 0 ? $talleId : null
            );
            $posterior = $saldoRepository->sumaVariantePosteriorAFecha(
                $articuloId,
                $depositoId,
                $fechaRecuento,
                $colorKey > 0 ? $colorKey : null,
                $talleKey > 0 ? $talleKey : null
            );
            $vigente = $contado + $posterior;
            if ($vigente >= -1e-9) {
                continue;
            }
            $conflictos[] = [
                'articulo_id' => $articuloId,
                'sku' => (string) ($linea['sku'] ?? (string) $articuloId),
                'contado' => $contado,
                'posterior' => $posterior,
                'vigente' => $vigente,
            ];
        }

        return $conflictos;
    }

    /**
     * @param  list<array{articulo_id:int, sku:string, contado:float, posterior:float, vigente:float}>  $conflictos
     */
    public static function mensajeBloqueoSaldoNegativoTrasCierreFecha(
        Recuento $recuento,
        array $conflictos,
    ): string {
        $fechaFmt = optional($recuento->fecha)->format('d/m/Y') ?: '—';
        $partes = [];
        foreach (array_slice($conflictos, 0, 8) as $c) {
            $partes[] = sprintf(
                '%s (contado %.0f + mov. posteriores %.0f = vigente %.0f)',
                $c['sku'],
                $c['contado'],
                $c['posterior'],
                $c['vigente']
            );
        }
        $extra = count($conflictos) > 8 ? ' … (+'.(count($conflictos) - 8).' más)' : '';

        return "No se puede cerrar «a fecha del recuento» ({$fechaFmt}): "
            .'hay movimientos posteriores que dejarían saldo vigente negativo. '
            .'Ejemplos: '.implode('; ', $partes).$extra.'. '
            .'Corrija esos movimientos, cambie la fecha del recuento, o cierre con «Al saldo actual».';
    }

    /**
     * Saldo de referencia por variante (color/talle; null/0 = sin variante).
     */
    public static function saldoReferencia(
        Articulo_Saldo_DepositoRepositoryInterface $saldoRepository,
        int $articuloId,
        int $depositoId,
        string $modoCierre,
        ?Carbon $fechaRecuento,
        ?int $colorId = null,
        ?int $talleId = null,
    ): float {
        [$colorKey, $talleKey] = ArticuloStockColorTalleSupport::claveSaldo($colorId, $talleId);

        $fecha = (self::resolverModo($modoCierre) === self::MODO_FECHA_RECUENTO && $fechaRecuento)
            ? $fechaRecuento->toDateString()
            : now()->toDateString();

        return $saldoRepository->saldoVarianteAFecha(
            $articuloId,
            $depositoId,
            $fecha,
            $colorKey > 0 ? $colorKey : null,
            $talleKey > 0 ? $talleKey : null
        );
    }

    public static function fechaMovimientoCierre(Recuento $recuento, string $modoCierre): string
    {
        if (self::resolverModo($modoCierre) === self::MODO_FECHA_RECUENTO && $recuento->fecha) {
            return $recuento->fecha->toDateString();
        }

        return now()->toDateString();
    }
}
