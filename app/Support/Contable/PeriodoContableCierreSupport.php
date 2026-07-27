<?php

namespace App\Support\Contable;

use App\Exceptions\Contable\PeriodoContableCerradoException;
use App\Models\Contable\AperturaPeriodoContable;
use App\Models\Contable\PeriodoCierreContable;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class PeriodoContableCierreSupport
{
    public const SLUG_OPERAR_CERRADO = 'operar-periodo-cerrado-contable';

    public const ALCANCE_GENERAL = 'general';

    public const ALCANCE_COBRANZA = 'cobranza';

    public const ALCANCE_CAJA = 'caja';

    public const ALCANCE_TRANSFERENCIA = 'transferencia';

    public const ALCANCE_STOCK = 'stock';

    public const ALCANCE_CONTABLE = 'contable';

    public const ALCANCE_FACTURACION = 'facturacion';

    public const ALCANCE_RECEPCION_PROVEEDOR = 'recepcion_proveedor';

    public const ALCANCE_INTERBANKING = 'interbanking';

    /** @return array<string, string> */
    public static function alcancesDisponibles(): array
    {
        return [
            self::ALCANCE_GENERAL => 'General (todos los módulos)',
            self::ALCANCE_COBRANZA => 'Cobranzas',
            self::ALCANCE_CAJA => 'Ingresos / egresos de caja',
            self::ALCANCE_TRANSFERENCIA => 'Transferencias de mercadería',
            self::ALCANCE_STOCK => 'Movimientos de stock',
            self::ALCANCE_RECEPCION_PROVEEDOR => 'Recepción de proveedores',
            self::ALCANCE_CONTABLE => 'Asientos contables manuales',
            self::ALCANCE_INTERBANKING => 'Interbanking / conciliación bancaria',
            self::ALCANCE_FACTURACION => 'Facturación (PV manual o CAEA)',
        ];
    }

    /**
     * Alcances de la agenda mensual (sin "general"; el cierre de todos usa el atajo masivo).
     *
     * @return array<string, string>
     */
    public static function alcancesAgenda(): array
    {
        $todos = self::alcancesDisponibles();
        unset($todos[self::ALCANCE_GENERAL]);

        return $todos;
    }

    public static function etiquetaAlcance(string $alcance): string
    {
        return self::alcancesDisponibles()[$alcance] ?? $alcance;
    }

    public static function alcanceEsValido(string $alcance): bool
    {
        return array_key_exists($alcance, self::alcancesDisponibles());
    }

    /**
     * Fecha de cierre vigente para la empresa.
     * Si se indica alcance de operación, considera cierres "general" y del mismo alcance
     * (el MAX fecha_hasta es el más restrictivo).
     */
    public static function fechaCierreVigente(int $empresaId, ?string $alcance = null): ?Carbon
    {
        if ($empresaId <= 0) {
            return null;
        }

        $query = PeriodoCierreContable::query()
            ->where('empresa_id', $empresaId);

        if ($alcance !== null && $alcance !== '' && $alcance !== self::ALCANCE_GENERAL) {
            $query->where(function ($q) use ($alcance) {
                $q->where('alcance', self::ALCANCE_GENERAL)
                    ->orWhere('alcance', $alcance);
            });
        } elseif ($alcance === self::ALCANCE_GENERAL) {
            $query->where('alcance', self::ALCANCE_GENERAL);
        }

        $fecha = $query->max('fecha_hasta');

        if ($fecha === null) {
            return null;
        }

        return Carbon::parse($fecha)->startOfDay();
    }

    /**
     * Facturación electrónica (WSFE/CAE): la fecha la valida AFIP/ARCA.
     * Manual (M) y CAEA (A) sí aplican cierre contable.
     */
    public static function facturacionRequiereValidacionCierre(?string $modoFacturacionPv): bool
    {
        $modo = strtoupper(trim((string) $modoFacturacionPv));

        return $modo === '' || $modo === 'M' || $modo === 'A';
    }

    /**
     * @param  array{omitir_validacion?: bool, modofacturacion_pv?: string|null, fechajornada?: string|null}  $opciones
     */
    public static function assertOperacionPermitida(
        int $empresaId,
        string $fecha,
        string $alcance,
        ?int $usuarioId = null,
        array $opciones = []
    ): void {
        if (! empty($opciones['omitir_validacion'])) {
            return;
        }

        if (can(self::SLUG_OPERAR_CERRADO, false)) {
            return;
        }

        if ($alcance === self::ALCANCE_FACTURACION
            && ! self::facturacionRequiereValidacionCierre($opciones['modofacturacion_pv'] ?? null)) {
            return;
        }

        $fechaValidacion = $fecha;
        if ($alcance === self::ALCANCE_FACTURACION
            && ! empty($opciones['fechajornada'])) {
            $fechaValidacion = (string) $opciones['fechajornada'];
        }

        $fechaOperacion = Carbon::parse($fechaValidacion)->startOfDay();
        $fechaCierre = self::fechaCierreVigente($empresaId, $alcance);

        if ($fechaCierre === null || $fechaOperacion->gt($fechaCierre)) {
            return;
        }

        $usuarioId = $usuarioId ?? (int) (Auth::id() ?? 0);

        if ($usuarioId > 0 && self::tieneAperturaActiva($empresaId, $usuarioId, $fechaOperacion, $alcance)) {
            return;
        }

        throw new PeriodoContableCerradoException(
            self::mensajeBloqueo($fechaOperacion, $fechaCierre, $alcance),
            $fechaOperacion->format('Y-m-d'),
            $fechaCierre->format('Y-m-d'),
            $alcance
        );
    }

    public static function tieneAperturaActiva(
        int $empresaId,
        int $usuarioId,
        Carbon $fechaOperacion,
        string $alcance
    ): bool {
        return AperturaPeriodoContable::query()
            ->where('empresa_id', $empresaId)
            ->where('usuario_habilitado_id', $usuarioId)
            ->where('estado', 'activa')
            ->whereNotNull('inicio_en')
            ->whereNotNull('vence_en')
            ->where('inicio_en', '<=', now())
            ->where('vence_en', '>', now())
            ->whereDate('fecha_operacion_desde', '<=', $fechaOperacion)
            ->whereDate('fecha_operacion_hasta', '>=', $fechaOperacion)
            ->where(function ($query) use ($alcance) {
                $query->where('alcance', self::ALCANCE_GENERAL)
                    ->orWhere('alcance', $alcance);
            })
            ->exists();
    }

    public static function alcanceCubre(string $alcanceApertura, string $alcanceOperacion): bool
    {
        if ($alcanceApertura === self::ALCANCE_GENERAL) {
            return true;
        }

        return $alcanceApertura === $alcanceOperacion;
    }

    public static function mensajeBloqueo(Carbon $fechaOperacion, Carbon $fechaCierre, string $alcance): string
    {
        return 'El período contable está cerrado hasta el '
            .$fechaCierre->format('d/m/Y')
            .'. No puede registrar operaciones con fecha '
            .$fechaOperacion->format('d/m/Y')
            .' en '
            .self::etiquetaAlcance($alcance)
            .'. Solicite una apertura programada al encargado de contaduría.';
    }

    public static function mensajeRestriccionGenerico(): string
    {
        return 'Operación bloqueada por cierre contable del período. '
            .'Solicite una apertura programada si necesita registrar fechas anteriores.';
    }

    public static function calcularVencimiento(Carbon $inicio, int $cantidad, string $unidad): Carbon
    {
        $cantidad = max(1, $cantidad);

        return match ($unidad) {
            'dias' => $inicio->copy()->addDays($cantidad),
            default => $inicio->copy()->addHours($cantidad),
        };
    }

    /**
     * Valida cada día del rango (inclusive). Si no hay fechas, valida el día de hoy.
     *
     * @param  array{omitir_validacion?: bool, modofacturacion_pv?: string|null, fechajornada?: string|null}  $opciones
     */
    public static function assertRangoOperacionPermitido(
        int $empresaId,
        ?string $fechaDesde,
        ?string $fechaHasta,
        string $alcance,
        ?int $usuarioId = null,
        array $opciones = []
    ): void {
        if ($fechaDesde === null && $fechaHasta === null) {
            self::assertOperacionPermitida(
                $empresaId,
                now()->format('Y-m-d'),
                $alcance,
                $usuarioId,
                $opciones
            );

            return;
        }

        $desde = Carbon::parse($fechaDesde ?? $fechaHasta)->startOfDay();
        $hasta = Carbon::parse($fechaHasta ?? $fechaDesde)->startOfDay();

        if ($hasta->lt($desde)) {
            throw new PeriodoContableCerradoException(
                'Rango de fechas inválido para validación de cierre contable.',
                null,
                null,
                $alcance
            );
        }

        for ($cursor = $desde->copy(); $cursor->lte($hasta); $cursor->addDay()) {
            self::assertOperacionPermitida(
                $empresaId,
                $cursor->format('Y-m-d'),
                $alcance,
                $usuarioId,
                $opciones
            );
        }
    }
}
