<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Anticipo_Desvio;
use App\Models\Compras\Configuracion_AnticipoFacturaDestinatario;
use App\Models\Compras\Ordencompra;
use App\Models\Configuracion\Parametro_Sistema;
use App\Queries\Configuracion\CotizacionQueryInterface;
use App\Services\Configuracion\ImpuestoService;
use App\Support\Configuracion\ParametroSistemaSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Seguridad\UsuarioOperativoSupport;
use Illuminate\Support\Facades\Schema;

/**
 * % de anticipo contractual en la OC y gate de facturas anticipadas.
 *
 * Sin plan (o un solo ítem) en «Comprobantes a venir»: una sola factura de anticipo
 * por el % de la OC. Con varias cuotas/comprobantes en el plan: se admiten hasta esa
 * cantidad de anticipadas; el % se controla acumulado (no factura a factura).
 *
 * Base del esperado: {@see OrdencompraTotalesResumen::desdeModelo} campo `total`
 * (neto + IVA, o precio total si el proveedor no discrimina IVA).
 * La factura se compara por su `total` (importe bruto del comprobante),
 * convertido a la moneda de la OC ({@see importeEnMonedaOc}) para no mezclar $ y USD.
 */
final class ComprobanteProveedorAnticipoPctSupport
{
    public const TOLERANCIA_ANTICIPO_PCT_DEFAULT = 1.0;

    public const CLAVE_TOLERANCIA_ANTICIPO_PCT = 'factura_proveedor_tolerancia_anticipo_pct';

    /** Completar % en OC ya aprobada (legado). En AGG se asigna solo a roles de Compras. */
    public const PERMISO_COMPLETAR_PCT_OC_APROBADA = 'completar-pct-anticipo-ordencompra';

    public static function puedeCompletarPctOcAprobada(): bool
    {
        return can(self::PERMISO_COMPLETAR_PCT_OC_APROBADA, false);
    }

    public static function toleranciaPct(): float
    {
        return max(0.0, ParametroSistemaSupport::decimal(
            self::CLAVE_TOLERANCIA_ANTICIPO_PCT,
            self::TOLERANCIA_ANTICIPO_PCT_DEFAULT
        ));
    }

    public static function guardarToleranciaPct(float $porcentaje): void
    {
        Parametro_Sistema::query()->updateOrCreate(
            ['clave' => self::CLAVE_TOLERANCIA_ANTICIPO_PCT],
            [
                'grupo' => 'Compras',
                'etiqueta' => 'Tolerancia de % anticipo en factura de proveedor (%)',
                'ayuda' => 'Global. Por encima, la factura de anticipo queda en borrador para Mis aprobaciones.',
                'tipo' => 'decimal',
                'valor' => (string) max(0.0, $porcentaje),
                'orden' => 56,
            ]
        );
        ParametroSistemaSupport::olvidarCache();
    }

    /**
     * @return list<int>
     */
    public static function idsDestinatarios(): array
    {
        if (! Schema::hasTable('configuracion_anticipo_factura_destinatario')) {
            return [];
        }

        return Configuracion_AnticipoFacturaDestinatario::query()
            ->orderBy('id')
            ->pluck('usuario_id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->values()
            ->all();
    }

    public static function esDestinatario(int $usuarioId): bool
    {
        if ($usuarioId <= 0) {
            return false;
        }

        $ids = UsuarioOperativoSupport::filtrarIdsActivos(self::idsDestinatarios());

        return in_array($usuarioId, $ids, true);
    }

    /**
     * @return list<\App\Models\Seguridad\Usuario>
     */
    public static function destinatarios(): array
    {
        $ids = UsuarioOperativoSupport::filtrarIdsActivos(self::idsDestinatarios());
        if ($ids === []) {
            return [];
        }

        return UsuarioOperativoSupport::query()
            ->whereIn('id', $ids)
            ->orderBy('nombre')
            ->get()
            ->all();
    }

    /**
     * @param  list<int>  $usuarioIds
     */
    public static function guardarDestinatarios(array $usuarioIds): void
    {
        if (! Schema::hasTable('configuracion_anticipo_factura_destinatario')) {
            return;
        }

        $ids = UsuarioOperativoSupport::filtrarIdsActivos($usuarioIds);
        $quedan = Configuracion_AnticipoFacturaDestinatario::query()
            ->whereNotIn('usuario_id', $ids === [] ? [-1] : $ids);
        EloquentAuditDeleteSupport::each($quedan);

        foreach ($ids as $usuarioId) {
            Configuracion_AnticipoFacturaDestinatario::query()->firstOrCreate([
                'usuario_id' => $usuarioId,
            ]);
        }
    }

    public static function pctAnticipoOc(?Ordencompra $ordencompra): float
    {
        if (! $ordencompra) {
            return 0.0;
        }

        return max(0.0, (float) ($ordencompra->pct_anticipo ?? 0));
    }

    /**
     * Total contractual de la OC (neto + IVA) en moneda de las líneas.
     */
    public static function totalOc(Ordencompra $ordencompra): float
    {
        $ordencompra->loadMissing(['ordencompra_articulos.monedas', 'proveedores.condicionivas']);
        $resumen = OrdencompraTotalesResumen::desdeModelo(
            $ordencompra,
            app(CotizacionQueryInterface::class),
            app(ImpuestoService::class),
        );

        return round((float) ($resumen['total'] ?? 0), 2);
    }

    /**
     * Moneda de referencia del total OC (primera línea).
     */
    public static function monedaReferenciaOc(Ordencompra $ordencompra): int
    {
        $ordencompra->loadMissing(['ordencompra_articulos']);
        $primer = collect($ordencompra->ordencompra_articulos ?? [])->sortBy('id')->first();

        return max(1, (int) ($primer->moneda_id ?? 1));
    }

    /**
     * Cotización de la línea de referencia (pesos por unidad ME).
     */
    public static function cotizacionReferenciaOc(Ordencompra $ordencompra): float
    {
        $ordencompra->loadMissing(['ordencompra_articulos']);
        $primer = collect($ordencompra->ordencompra_articulos ?? [])->sortBy('id')->first();

        return (float) ($primer->cotizacion ?? 1);
    }

    /**
     * Lleva un importe de la factura a la moneda del total OC.
     * Sin esto, FC en $ vs OC en USD dispara falso “supera el total de la OC”.
     */
    public static function importeEnMonedaOc(
        Ordencompra $ordencompra,
        float $importe,
        int $monedaOrigenId = 1,
        mixed $cotizacionOrigen = 1.0,
        mixed $fechaOrigen = null,
    ): float {
        return ComprobanteProveedorMonedaMotor::convertirTolerante(
            abs($importe),
            $monedaOrigenId,
            $cotizacionOrigen,
            $fechaOrigen,
            self::monedaReferenciaOc($ordencompra),
            self::cotizacionReferenciaOc($ordencompra),
            $fechaOrigen,
            'importe de la factura',
            'total de la OC',
        );
    }

    public static function importeEsperado(Ordencompra $ordencompra): float
    {
        $pct = self::pctAnticipoOc($ordencompra);
        if ($pct <= 0) {
            return 0.0;
        }

        return round(self::totalOc($ordencompra) * $pct / 100.0, 2);
    }

    /**
     * Plan «Comprobantes a venir» de la OC: cuántas facturas/cuotas se esperan.
     *
     * Cada fila de cuota cuenta 1. Si el comprobante no tiene cuotas cargadas,
     * cuenta {@see Ordencompra_Comprobante::$cantidadcuota} (mínimo 1).
     *
     * @return array{cantidad: int, monto: float, permite_varias: bool}
     */
    public static function planComprobantesAVenir(?Ordencompra $ordencompra): array
    {
        $vacio = ['cantidad' => 0, 'monto' => 0.0, 'permite_varias' => false];
        if (! $ordencompra || ! Schema::hasTable('ordencompra_comprobante')) {
            return $vacio;
        }

        $ordencompra->loadMissing(['ordencompra_comprobantes.ordencompra_comprobante_cuotas']);
        $cantidad = 0;
        $monto = 0.0;

        foreach ($ordencompra->ordencompra_comprobantes ?? [] as $comp) {
            $cuotas = $comp->ordencompra_comprobante_cuotas ?? collect();
            if ($cuotas->isNotEmpty()) {
                $cantidad += $cuotas->count();
                $monto += (float) $cuotas->sum(static fn ($c) => (float) ($c->monto ?? 0));

                continue;
            }

            $n = max(1, (int) ($comp->cantidadcuota ?? 0));
            $cantidad += $n;
            $monto += (float) ($comp->monto ?? 0);
        }

        return [
            'cantidad' => $cantidad,
            'monto' => round($monto, 2),
            'permite_varias' => $cantidad > 1,
        ];
    }

    /**
     * Tope de facturas de anticipo según el plan de la OC (mínimo 1).
     */
    public static function maxFacturasAnticipo(?Ordencompra $ordencompra): int
    {
        $plan = self::planComprobantesAVenir($ordencompra);
        if ($plan['cantidad'] <= 0) {
            return 1;
        }

        return max(1, $plan['cantidad']);
    }

    public static function desvioPct(float $importeIngresado, float $importeEsperado): float
    {
        if ($importeEsperado <= 0.0001) {
            return $importeIngresado > 0.0001 ? 100.0 : 0.0;
        }

        return round(abs($importeIngresado - $importeEsperado) / $importeEsperado * 100.0, 4);
    }

    public static function dentroDeTolerancia(float $importeIngresado, float $importeEsperado, ?float $toleranciaPct = null): bool
    {
        $tol = $toleranciaPct ?? self::toleranciaPct();

        return self::desvioPct($importeIngresado, $importeEsperado) <= $tol + 0.0001;
    }

    /**
     * Otras facturas de anticipo del legajo (sin COM), excluyendo anuladas.
     *
     * @return list<array{id: int, estado: string}>
     */
    public static function otrasAnticipadasSinCom(int $ordencompraId, ?int $excluirComprobanteId = null): array
    {
        if ($ordencompraId <= 0 || ! Schema::hasTable('comprobante_proveedor')) {
            return [];
        }

        $query = Comprobante_Proveedor::query()
            ->where('ordencompra_id', $ordencompraId)
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhereRaw('UPPER(TRIM(estado)) != ?', [ComprobanteProveedorEstados::ANULADO]);
            })
            ->where(function ($q) {
                $q->whereNull('modo_carga')
                    ->orWhere('modo_carga', '!=', ComprobanteProveedorModoCarga::ASIGNA_RECEPCION);
            });

        if ($excluirComprobanteId !== null && $excluirComprobanteId > 0) {
            $query->where('id', '!=', $excluirComprobanteId);
        }

        if (Schema::hasTable('comprobante_proveedor_recepcion')) {
            $query->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('comprobante_proveedor_recepcion')
                    ->whereColumn(
                        'comprobante_proveedor_recepcion.comprobante_proveedor_id',
                        'comprobante_proveedor.id'
                    );
            });
        }

        $out = [];
        foreach ($query->with('ordencompras')->orderBy('id')->get() as $cp) {
            if (! ComprobanteProveedorFacturaAnticipadaSupport::aplica($cp)) {
                continue;
            }
            $out[] = [
                'id' => (int) $cp->id,
                'estado' => (string) ($cp->estado ?? ''),
            ];
        }

        return $out;
    }

    public static function estaAprobada(int $comprobanteId): bool
    {
        if ($comprobanteId <= 0 || ! Schema::hasTable('comprobante_proveedor_anticipo_desvio')) {
            return false;
        }

        return Comprobante_Proveedor_Anticipo_Desvio::query()
            ->where('comprobante_proveedor_id', $comprobanteId)
            ->whereIn('estado', [
                Comprobante_Proveedor_Anticipo_Desvio::ESTADO_APROBADA,
                Comprobante_Proveedor_Anticipo_Desvio::ESTADO_CONTABILIZADO,
            ])
            ->exists();
    }

    /**
     * @return array{
     *     aplica: bool,
     *     ok_unicidad: bool,
     *     ok_pct: bool,
     *     requiere_aprobacion: bool,
     *     pct_anticipo: float,
     *     total_oc: float,
     *     importe_esperado: float,
     *     importe_ingresado: float,
     *     importe_acumulado: float,
     *     desvio_pct: float,
     *     tolerancia: float,
     *     max_anticipos: int,
     *     permite_varias: bool,
     *     plan_cantidad: int,
     *     otras_anticipadas: list<array{id: int, estado: string}>,
     *     errores: list<string>,
     *     avisos: list<string>
     * }
     */
    public static function analizar(
        ?Ordencompra $ordencompra,
        string $modoCarga,
        float $totalFactura,
        ?int $excluirComprobanteId = null,
        int $monedaFacturaId = 1,
        mixed $cotizacionFactura = 1.0,
        mixed $fechaFactura = null,
    ): array {
        $vacio = [
            'aplica' => false,
            'ok_unicidad' => true,
            'ok_pct' => true,
            'requiere_aprobacion' => false,
            'pct_anticipo' => 0.0,
            'total_oc' => 0.0,
            'importe_esperado' => 0.0,
            'importe_ingresado' => round(abs($totalFactura), 2),
            'importe_acumulado' => round(abs($totalFactura), 2),
            'desvio_pct' => 0.0,
            'tolerancia' => self::toleranciaPct(),
            'max_anticipos' => 1,
            'permite_varias' => false,
            'plan_cantidad' => 0,
            'otras_anticipadas' => [],
            'errores' => [],
            'avisos' => [],
        ];

        if (! $ordencompra) {
            return $vacio;
        }
        if (! ComprobanteProveedorFlujoOcComFacSupport::esOcAnticipada($ordencompra)) {
            return $vacio;
        }
        if ($modoCarga === ComprobanteProveedorModoCarga::ASIGNA_RECEPCION) {
            return $vacio;
        }
        if (OrdencompraContratoRutaFacturaSupport::aplicaSinRecepcion($ordencompra, null)) {
            return $vacio;
        }

        $pct = self::pctAnticipoOc($ordencompra);
        $totalOc = self::totalOc($ordencompra);
        $esperado = self::importeEsperado($ordencompra);
        // Comparar en moneda OC: FC en $ vs esperado en USD no es desvío real.
        $ingresado = self::importeEnMonedaOc(
            $ordencompra,
            $totalFactura,
            $monedaFacturaId,
            $cotizacionFactura,
            $fechaFactura,
        );
        $tol = self::toleranciaPct();
        $plan = self::planComprobantesAVenir($ordencompra);
        $maxAnticipos = self::maxFacturasAnticipo($ordencompra);
        $permiteVarias = $plan['permite_varias'];
        $otras = self::otrasAnticipadasSinCom((int) $ordencompra->id, $excluirComprobanteId);
        $errores = [];
        $avisos = [];

        if ($pct <= 0) {
            $errores[] = 'La OC anticipada no tiene % de anticipo. Completelo en la orden de compra antes de cargar la factura.';
        }

        $okUnicidad = count($otras) < $maxAnticipos;
        if (! $okUnicidad) {
            $ids = implode(', ', array_map(static fn (array $f) => '#'.$f['id'], $otras));
            if ($permiteVarias) {
                $errores[] = sprintf(
                    'El plan de comprobantes a venir admite hasta %d factura(s) de anticipo; ya hay %d: %s.',
                    $maxAnticipos,
                    count($otras),
                    $ids
                );
            } else {
                $errores[] = 'Solo se admite una factura de anticipo por OC. Ya existe: '.$ids.'.';
            }
        }

        // Con plan multi-cuota el % se mira acumulado (cuotas parciales OK).
        // Sin plan / una sola: la factura debe coincidir con el % contractual.
        $yaAnticipo = abs((float) ComprobanteProveedorImporteYaFacturadoLegajoSupport::sumarComparableAnticipadasSinCom(
            (int) $ordencompra->id,
            $excluirComprobanteId,
            self::monedaReferenciaOc($ordencompra),
            self::cotizacionReferenciaOc($ordencompra),
            $fechaFactura,
        )['importe']);
        $acumulado = round($yaAnticipo + $ingresado, 2);

        if ($permiteVarias) {
            $topeEsperado = round($esperado * (1 + $tol / 100.0), 2);
            $okPct = $pct <= 0 || $acumulado <= $topeEsperado + 0.05;
            $desvio = $esperado > 0.0001
                ? round(max(0.0, $acumulado - $esperado) / $esperado * 100.0, 4)
                : ($acumulado > 0.0001 ? 100.0 : 0.0);
            // Parciales bajo el esperado no piden aprobación; solo el exceso acumulado.
            $requiereAprobacion = $pct > 0 && $okUnicidad && ! $okPct;
            if ($requiereAprobacion) {
                $avisos[] = sprintf(
                    'La suma de anticipos ($%s) supera el %s%% contractual ($%s del total OC $%s) en %s%% (tolerancia %s%%). '
                    .'Queda en borrador hasta liberación en Mis aprobaciones.',
                    number_format($acumulado, 2, ',', '.'),
                    number_format($pct, 2, ',', '.'),
                    number_format($esperado, 2, ',', '.'),
                    number_format($totalOc, 2, ',', '.'),
                    number_format($desvio, 2, ',', '.'),
                    number_format($tol, 2, ',', '.')
                );
            }
        } else {
            $desvio = self::desvioPct($ingresado, $esperado);
            $okPct = $pct > 0 && self::dentroDeTolerancia($ingresado, $esperado, $tol);
            $requiereAprobacion = $pct > 0 && $okUnicidad && ! $okPct;
            if ($requiereAprobacion) {
                $avisos[] = sprintf(
                    'El anticipo cargado ($%s) difiere del esperado $%s (%s%% del total OC $%s) en %s%% (tolerancia %s%%). '
                    .'Queda en borrador hasta liberación en Mis aprobaciones.',
                    number_format($ingresado, 2, ',', '.'),
                    number_format($esperado, 2, ',', '.'),
                    number_format($pct, 2, ',', '.'),
                    number_format($totalOc, 2, ',', '.'),
                    number_format($desvio, 2, ',', '.'),
                    number_format($tol, 2, ',', '.')
                );
            }
        }

        return [
            'aplica' => true,
            'ok_unicidad' => $okUnicidad,
            'ok_pct' => $okPct || $pct <= 0,
            'requiere_aprobacion' => $requiereAprobacion,
            'pct_anticipo' => $pct,
            'total_oc' => $totalOc,
            'importe_esperado' => $esperado,
            'importe_ingresado' => $ingresado,
            'importe_acumulado' => $acumulado,
            'desvio_pct' => $desvio,
            'tolerancia' => $tol,
            'max_anticipos' => $maxAnticipos,
            'permite_varias' => $permiteVarias,
            'plan_cantidad' => $plan['cantidad'],
            'otras_anticipadas' => $otras,
            'errores' => $errores,
            'avisos' => $avisos,
        ];
    }

    /**
     * Tope: suma de facturas del legajo (incl. la actual) no puede superar total OC + tolerancia.
     * Importes se normalizan a la moneda de la OC (FC en $ vs OC en USD).
     *
     * @return array{ok: bool, error: ?string, ya_facturado: float, proyectado: float, tope: float}
     */
    public static function controlarTopeTotalOc(
        Ordencompra $ordencompra,
        float $importeFacturaActual,
        ?int $excluirComprobanteId = null,
        int $monedaFacturaId = 1,
        mixed $cotizacionFactura = 1.0,
        mixed $fechaFactura = null,
    ): array {
        $totalOc = self::totalOc($ordencompra);
        $tol = self::toleranciaPct();
        $monedaOcId = self::monedaReferenciaOc($ordencompra);
        $cotizacionOc = self::cotizacionReferenciaOc($ordencompra);
        $ya = ComprobanteProveedorImporteYaFacturadoLegajoSupport::sumarComparableEnLegajo(
            (int) $ordencompra->id,
            $excluirComprobanteId,
            $monedaOcId,
            $cotizacionOc,
            $fechaFactura,
            true,
        );
        $actualEnOc = self::importeEnMonedaOc(
            $ordencompra,
            $importeFacturaActual,
            $monedaFacturaId,
            $cotizacionFactura,
            $fechaFactura,
        );
        $proyectado = round((float) $ya['importe'] + $actualEnOc, 2);
        $tope = round($totalOc * (1 + $tol / 100.0), 2);
        $ok = $totalOc <= 0.0001 || $proyectado <= $tope + 0.05;

        return [
            'ok' => $ok,
            'error' => $ok ? null : sprintf(
                'La suma de anticipos y facturas ($%s) supera el total de la OC ($%s, tolerancia %s%%).',
                number_format($proyectado, 2, ',', '.'),
                number_format($totalOc, 2, ',', '.'),
                number_format($tol, 2, ',', '.')
            ),
            'ya_facturado' => (float) $ya['importe'],
            'proyectado' => $proyectado,
            'tope' => $tope,
        ];
    }

    /**
     * Residual esperado para factura final: total OC − anticipo ya aplicado (misma moneda OC).
     */
    public static function residualTrasAnticipo(Ordencompra $ordencompra, ?int $excluirComprobanteId = null): float
    {
        $totalOc = self::totalOc($ordencompra);
        $anticipo = (float) ComprobanteProveedorImporteYaFacturadoLegajoSupport::sumarComparableAnticipadasSinCom(
            (int) $ordencompra->id,
            $excluirComprobanteId,
            self::monedaReferenciaOc($ordencompra),
            self::cotizacionReferenciaOc($ordencompra),
        )['importe'];

        return round(max(0.0, $totalOc - abs($anticipo)), 2);
    }
}
