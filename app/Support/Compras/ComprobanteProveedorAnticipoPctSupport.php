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
 * % de anticipo contractual en la OC y gate de la única factura anticipada.
 *
 * Base del esperado: {@see OrdencompraTotalesResumen::desdeModelo} campo `total`
 * (neto + IVA, o precio total si el proveedor no discrimina IVA).
 * La factura se compara por su `total` (importe bruto del comprobante).
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

    public static function importeEsperado(Ordencompra $ordencompra): float
    {
        $pct = self::pctAnticipoOc($ordencompra);
        if ($pct <= 0) {
            return 0.0;
        }

        return round(self::totalOc($ordencompra) * $pct / 100.0, 2);
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
     *     desvio_pct: float,
     *     tolerancia: float,
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
            'desvio_pct' => 0.0,
            'tolerancia' => self::toleranciaPct(),
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
        $ingresado = round(abs($totalFactura), 2);
        $tol = self::toleranciaPct();
        $otras = self::otrasAnticipadasSinCom((int) $ordencompra->id, $excluirComprobanteId);
        $errores = [];
        $avisos = [];

        if ($pct <= 0) {
            $errores[] = 'La OC anticipada no tiene % de anticipo. Completelo en la orden de compra antes de cargar la factura.';
        }

        $okUnicidad = $otras === [];
        if (! $okUnicidad) {
            $ids = implode(', ', array_map(static fn (array $f) => '#'.$f['id'], $otras));
            $errores[] = 'Solo se admite una factura de anticipo por OC. Ya existe: '.$ids.'.';
        }

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

        return [
            'aplica' => true,
            'ok_unicidad' => $okUnicidad,
            'ok_pct' => $okPct || $pct <= 0,
            'requiere_aprobacion' => $requiereAprobacion,
            'pct_anticipo' => $pct,
            'total_oc' => $totalOc,
            'importe_esperado' => $esperado,
            'importe_ingresado' => $ingresado,
            'desvio_pct' => $desvio,
            'tolerancia' => $tol,
            'otras_anticipadas' => $otras,
            'errores' => $errores,
            'avisos' => $avisos,
        ];
    }

    /**
     * Tope: suma de facturas del legajo (incl. la actual) no puede superar total OC + tolerancia.
     *
     * @return array{ok: bool, error: ?string, ya_facturado: float, proyectado: float, tope: float}
     */
    public static function controlarTopeTotalOc(
        Ordencompra $ordencompra,
        float $importeFacturaActual,
        ?int $excluirComprobanteId = null,
    ): array {
        $totalOc = self::totalOc($ordencompra);
        $tol = self::toleranciaPct();
        $ya = ComprobanteProveedorImporteYaFacturadoLegajoSupport::sumarComparableEnLegajo(
            (int) $ordencompra->id,
            $excluirComprobanteId,
        );
        $proyectado = round((float) $ya['importe'] + abs($importeFacturaActual), 2);
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
     * Residual esperado para factura final: total OC − anticipo ya aplicado.
     */
    public static function residualTrasAnticipo(Ordencompra $ordencompra, ?int $excluirComprobanteId = null): float
    {
        $totalOc = self::totalOc($ordencompra);
        $anticipo = (float) ComprobanteProveedorImporteYaFacturadoLegajoSupport::sumarComparableAnticipadasSinCom(
            (int) $ordencompra->id,
            $excluirComprobanteId,
        )['importe'];

        return round(max(0.0, $totalOc - abs($anticipo)), 2);
    }
}
