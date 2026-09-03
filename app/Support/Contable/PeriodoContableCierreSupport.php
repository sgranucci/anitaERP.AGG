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

    /** Módulos padre (cierre global por área). */
    public const MODULO_CAJA = 'mod_caja';

    public const MODULO_VENTAS = 'mod_ventas';

    public const MODULO_COMPRAS = 'mod_compras';

    public const MODULO_STOCK = 'mod_stock';

    public const MODULO_SUELDOS = 'mod_sueldos';

    public const MODULO_CONTABLE = 'mod_contable';

    /** Submódulos (alcances operativos). */
    public const ALCANCE_COBRANZA = 'cobranza';

    public const ALCANCE_CAJA = 'caja';

    public const ALCANCE_TRANSFERENCIA = 'transferencia';

    public const ALCANCE_STOCK = 'stock';

    public const ALCANCE_CONTABLE = 'contable';

    public const ALCANCE_FACTURACION = 'facturacion';

    public const ALCANCE_RECEPCION_PROVEEDOR = 'recepcion_proveedor';

    public const ALCANCE_CUENTAS_PAGAR = 'cuentas_pagar';

    public const ALCANCE_INTERBANKING = 'interbanking';

    public const ALCANCE_INDUMENTARIA = 'indumentaria';

    /**
     * Módulos padre: código => etiqueta.
     *
     * @return array<string, string>
     */
    public static function modulosDisponibles(): array
    {
        return [
            self::MODULO_CAJA => 'Caja',
            self::MODULO_VENTAS => 'Ventas',
            self::MODULO_COMPRAS => 'Compras',
            self::MODULO_STOCK => 'Stock',
            self::MODULO_SUELDOS => 'Sueldos',
            self::MODULO_CONTABLE => 'Contable',
        ];
    }

    /**
     * Submódulos por módulo padre.
     *
     * @return array<string, array<string, string>>
     */
    public static function submodulosPorModulo(): array
    {
        return [
            self::MODULO_CAJA => [
                self::ALCANCE_COBRANZA => 'Cobranzas',
                self::ALCANCE_CAJA => 'Ingresos / egresos de caja',
                self::ALCANCE_INTERBANKING => 'Interbanking / conciliación bancaria',
            ],
            self::MODULO_VENTAS => [
                self::ALCANCE_FACTURACION => 'Facturación (PV manual o CAEA)',
            ],
            self::MODULO_COMPRAS => [
                self::ALCANCE_CUENTAS_PAGAR => 'Cuentas a pagar (facturas de proveedor)',
            ],
            self::MODULO_STOCK => [
                self::ALCANCE_STOCK => 'Movimientos de stock',
                self::ALCANCE_TRANSFERENCIA => 'Transferencias de mercadería',
                self::ALCANCE_RECEPCION_PROVEEDOR => 'Recepción de proveedores',
            ],
            self::MODULO_SUELDOS => [
                self::ALCANCE_INDUMENTARIA => 'Indumentaria (entrega y asiento)',
            ],
            self::MODULO_CONTABLE => [
                self::ALCANCE_CONTABLE => 'Asientos contables manuales',
            ],
        ];
    }

    /**
     * Catálogo completo: general + módulos padre + submódulos.
     *
     * @return array<string, string>
     */
    public static function alcancesDisponibles(): array
    {
        $lista = [
            self::ALCANCE_GENERAL => 'General (todos los módulos)',
        ];

        foreach (self::modulosDisponibles() as $codigo => $etiqueta) {
            $lista[$codigo] = $etiqueta.' (módulo completo)';
            foreach (self::submodulosPorModulo()[$codigo] ?? [] as $subCodigo => $subEtiqueta) {
                $lista[$subCodigo] = $subEtiqueta;
            }
        }

        return $lista;
    }

    /**
     * Alcances de la agenda mensual (módulos + submódulos; sin "general").
     *
     * @return array<string, string>
     */
    public static function alcancesAgenda(): array
    {
        $todos = self::alcancesDisponibles();
        unset($todos[self::ALCANCE_GENERAL]);

        return $todos;
    }

    /**
     * Solo submódulos operativos (sin general ni módulos padre).
     *
     * @return array<string, string>
     */
    public static function alcancesOperativos(): array
    {
        $lista = [];
        foreach (self::submodulosPorModulo() as $hijos) {
            foreach ($hijos as $codigo => $etiqueta) {
                $lista[$codigo] = $etiqueta;
            }
        }

        return $lista;
    }

    /**
     * Estructura para UI (agenda / selects con optgroup).
     *
     * @return list<array{
     *   codigo: string,
     *   etiqueta: string,
     *   es_modulo: bool,
     *   hijos: list<array{codigo: string, etiqueta: string}>
     * }>
     */
    public static function jerarquiaAgenda(): array
    {
        $arbol = [];
        foreach (self::modulosDisponibles() as $codigo => $etiqueta) {
            $hijos = [];
            foreach (self::submodulosPorModulo()[$codigo] ?? [] as $subCodigo => $subEtiqueta) {
                $hijos[] = [
                    'codigo' => $subCodigo,
                    'etiqueta' => $subEtiqueta,
                ];
            }
            $arbol[] = [
                'codigo' => $codigo,
                'etiqueta' => $etiqueta,
                'es_modulo' => true,
                'hijos' => $hijos,
            ];
        }

        return $arbol;
    }

    public static function etiquetaAlcance(string $alcance): string
    {
        return self::alcancesDisponibles()[$alcance] ?? $alcance;
    }

    public static function alcanceEsValido(string $alcance): bool
    {
        return array_key_exists($alcance, self::alcancesDisponibles());
    }

    public static function esModuloPadre(string $alcance): bool
    {
        return array_key_exists($alcance, self::modulosDisponibles());
    }

    public static function esSubmodulo(string $alcance): bool
    {
        return array_key_exists($alcance, self::alcancesOperativos());
    }

    /**
     * Módulo padre del alcance. Si ya es módulo, se retorna a sí mismo.
     */
    public static function moduloPadreDe(string $alcance): ?string
    {
        if (self::esModuloPadre($alcance)) {
            return $alcance;
        }

        foreach (self::submodulosPorModulo() as $modulo => $hijos) {
            if (array_key_exists($alcance, $hijos)) {
                return $modulo;
            }
        }

        return null;
    }

    /**
     * Alcances de cierre que restringen una operación (general + módulo padre + propio).
     *
     * @return list<string>
     */
    public static function alcancesQueRestringen(string $alcanceOperacion): array
    {
        if ($alcanceOperacion === '' || $alcanceOperacion === self::ALCANCE_GENERAL) {
            return [self::ALCANCE_GENERAL];
        }

        $alcances = [self::ALCANCE_GENERAL];
        $modulo = self::moduloPadreDe($alcanceOperacion);
        if ($modulo !== null) {
            $alcances[] = $modulo;
        }
        if ($alcanceOperacion !== self::ALCANCE_GENERAL) {
            $alcances[] = $alcanceOperacion;
        }

        return array_values(array_unique($alcances));
    }

    /**
     * Fecha de cierre vigente para la empresa.
     * Si se indica alcance de operación, considera cierres general, del módulo padre y del submódulo
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
            $query->whereIn('alcance', self::alcancesQueRestringen($alcance));
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
     * True si la fecha de operación está cubierta por el cierre vigente (sin bypass de permiso ni apertura).
     */
    public static function fechaEnPeriodoCerrado(int $empresaId, string $fecha, string $alcance): bool
    {
        if ($empresaId <= 0 || trim($fecha) === '') {
            return false;
        }

        $fechaCierre = self::fechaCierreVigente($empresaId, $alcance);
        if ($fechaCierre === null) {
            return false;
        }

        return ! Carbon::parse($fecha)->startOfDay()->gt($fechaCierre);
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
            self::mensajeBloqueo(
                $fechaOperacion,
                $fechaCierre,
                $alcance,
                self::detalleAperturaUsuario($empresaId, $usuarioId, $fechaOperacion, $alcance)
            ),
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
        $cubiertos = self::alcancesQueCubrenApertura($alcance);

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
            ->whereIn('alcance', $cubiertos)
            ->exists();
    }

    /**
     * Alcances de apertura que habilitan una operación (general, módulo padre, el propio).
     *
     * @return list<string>
     */
    public static function alcancesQueCubrenApertura(string $alcanceOperacion): array
    {
        return self::alcancesQueRestringen($alcanceOperacion);
    }

    public static function alcanceCubre(string $alcanceApertura, string $alcanceOperacion): bool
    {
        if ($alcanceApertura === self::ALCANCE_GENERAL) {
            return true;
        }

        if ($alcanceApertura === $alcanceOperacion) {
            return true;
        }

        if (self::esModuloPadre($alcanceApertura)) {
            $hijos = self::submodulosPorModulo()[$alcanceApertura] ?? [];

            return array_key_exists($alcanceOperacion, $hijos)
                || $alcanceOperacion === $alcanceApertura;
        }

        return false;
    }

    public static function mensajeBloqueo(
        Carbon $fechaOperacion,
        Carbon $fechaCierre,
        string $alcance,
        ?string $detalleApertura = null
    ): string {
        $mensaje = 'El período contable está cerrado hasta el '
            .$fechaCierre->format('d/m/Y')
            .'. No puede registrar operaciones con fecha '
            .$fechaOperacion->format('d/m/Y')
            .' en '
            .self::etiquetaAlcance($alcance)
            .'.';

        if ($detalleApertura !== null && $detalleApertura !== '') {
            return $mensaje.' '.$detalleApertura;
        }

        return $mensaje.' Solicite una apertura programada al encargado de contaduría.';
    }

    /**
     * Explica la situación de las aperturas del propio usuario cuando el período está bloqueado:
     * vencida, vigente pero con otro rango de fechas, de otro alcance o pendiente de habilitación.
     * Sin apertura relacionada devuelve null (mensaje genérico).
     */
    public static function detalleAperturaUsuario(
        int $empresaId,
        int $usuarioId,
        Carbon $fechaOperacion,
        string $alcance
    ): ?string {
        if ($empresaId <= 0 || $usuarioId <= 0) {
            return null;
        }

        $aperturas = AperturaPeriodoContable::query()
            ->where('empresa_id', $empresaId)
            ->where('usuario_habilitado_id', $usuarioId)
            ->whereIn('estado', ['activa', 'vencida', 'pendiente'])
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $vencida = null;
        $fueraDeRango = null;
        $otroAlcance = null;
        $pendiente = null;
        $vencidaOtroRango = null;

        foreach ($aperturas as $apertura) {
            if ($apertura->estado === 'pendiente') {
                $pendiente = $pendiente ?? $apertura;

                continue;
            }

            $cubreAlcance = self::alcanceCubre((string) $apertura->alcance, $alcance);
            $cubreFecha = self::aperturaCubreFecha($apertura, $fechaOperacion);
            $vigente = $apertura->estaActiva();

            if ($cubreAlcance && $cubreFecha && ! $vigente) {
                $vencida = $vencida ?? $apertura;
            } elseif ($cubreAlcance && ! $cubreFecha && $vigente) {
                $fueraDeRango = $fueraDeRango ?? $apertura;
            } elseif (! $cubreAlcance && $cubreFecha && $vigente) {
                $otroAlcance = $otroAlcance ?? $apertura;
            } elseif ($cubreAlcance && ! $cubreFecha && ! $vigente) {
                $vencidaOtroRango = $vencidaOtroRango ?? $apertura;
            }
        }

        if ($vencida !== null) {
            return 'Su apertura '.self::referenciaApertura($vencida)
                .' para esta fecha venció el '
                .optional($vencida->vence_en)->format('d/m/Y H:i')
                .' ('.$vencida->etiquetaDuracion().'). Solicite una apertura nueva para seguir operando.';
        }

        if ($fueraDeRango !== null) {
            return 'Su apertura '.self::referenciaApertura($fueraDeRango)
                .' está vigente hasta las '
                .optional($fueraDeRango->vence_en)->format('H:i')
                .' pero habilita solo del '
                .optional($fueraDeRango->fecha_operacion_desde)->format('d/m/Y')
                .' al '
                .optional($fueraDeRango->fecha_operacion_hasta)->format('d/m/Y')
                .'. Solicite una apertura que incluya el '
                .$fechaOperacion->format('d/m/Y').'.';
        }

        if ($otroAlcance !== null) {
            return 'Su apertura '.self::referenciaApertura($otroAlcance)
                .' está vigente pero solo para '
                .$otroAlcance->etiquetaAlcance()
                .'. Solicite una apertura para '.self::etiquetaAlcance($alcance).'.';
        }

        if ($pendiente !== null) {
            return 'Su solicitud de apertura '.self::referenciaApertura($pendiente)
                .' sigue pendiente de habilitación.';
        }

        if ($vencidaOtroRango !== null) {
            return 'Su última apertura '.self::referenciaApertura($vencidaOtroRango)
                .' habilitaba del '
                .optional($vencidaOtroRango->fecha_operacion_desde)->format('d/m/Y')
                .' al '
                .optional($vencidaOtroRango->fecha_operacion_hasta)->format('d/m/Y')
                .' y venció el '
                .optional($vencidaOtroRango->vence_en)->format('d/m/Y H:i')
                .'. Solicite una apertura que incluya el '
                .$fechaOperacion->format('d/m/Y').'.';
        }

        return null;
    }

    private static function aperturaCubreFecha(AperturaPeriodoContable $apertura, Carbon $fechaOperacion): bool
    {
        if ($apertura->fecha_operacion_desde === null || $apertura->fecha_operacion_hasta === null) {
            return false;
        }

        $desde = $apertura->fecha_operacion_desde->copy()->startOfDay();
        $hasta = $apertura->fecha_operacion_hasta->copy()->startOfDay();

        return $fechaOperacion->gte($desde) && $fechaOperacion->lte($hasta);
    }

    private static function referenciaApertura(AperturaPeriodoContable $apertura): string
    {
        return '#'.(int) $apertura->id;
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
            'minutos' => $inicio->copy()->addMinutes($cantidad),
            default => $inicio->copy()->addHours($cantidad),
        };
    }

    public static function etiquetaDuracion(int $cantidad, string $unidad): string
    {
        $sufijo = match ($unidad) {
            'dias' => 'día(s)',
            'minutos' => 'minuto(s)',
            default => 'hora(s)',
        };

        return $cantidad.' '.$sufijo;
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
