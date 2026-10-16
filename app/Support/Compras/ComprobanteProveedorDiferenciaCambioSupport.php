<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Difcambio;
use App\Models\Compras\Configuracion_DifcambioFacturaDestinatario;
use App\Models\Configuracion\Parametro_Sistema;
use App\Models\Contable\Cuentacontable;
use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Seguridad\UsuarioOperativoSupport;
use App\Services\Stock\RecepcionProveedorAsientoService;
use App\Support\Configuracion\ParametroSistemaSupport;
use App\Support\Contable\CuentacontableEmpresaHomologacionSupport;
use App\Support\Contable\PeriodoContableCierreSupport;

/**
 * Diferencia de cambio cuando la factura en moneda extranjera llega con otro tipo
 * de cambio que una recepción ya cerrada. La recepción no se reescribe.
 */
final class ComprobanteProveedorDiferenciaCambioSupport
{
    public const EPS_COTIZACION = 0.0000005;

    public const CODIGO_CUENTA = '532020001';

    public const TOLERANCIA_TC_PCT_DEFAULT = 3.0;

    public const CLAVE_TOLERANCIA_TC_PCT = 'factura_proveedor_tolerancia_tc_pct';

    /**
     * Un solo porcentaje para toda la instalación. No depende de la empresa ni del centro de costo.
     */
    public static function toleranciaPct(int $empresaId = 0): float
    {
        return max(0.0, ParametroSistemaSupport::decimal(
            self::CLAVE_TOLERANCIA_TC_PCT,
            self::TOLERANCIA_TC_PCT_DEFAULT
        ));
    }

    public static function guardarToleranciaPct(float $porcentaje): void
    {
        Parametro_Sistema::query()->updateOrCreate(
            ['clave' => self::CLAVE_TOLERANCIA_TC_PCT],
            [
                'grupo' => 'Compras',
                'etiqueta' => 'Tolerancia de TC en factura de proveedor (%)',
                'ayuda' => 'Global. Por encima, la factura en período cerrado queda en borrador para Mis aprobaciones.',
                'tipo' => 'decimal',
                'valor' => (string) max(0.0, $porcentaje),
                'orden' => 55,
            ]
        );
        ParametroSistemaSupport::olvidarCache();
    }

    /**
     * @return list<int>
     */
    public static function idsDestinatarios(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('configuracion_difcambio_factura_destinatario')) {
            return [];
        }

        return Configuracion_DifcambioFacturaDestinatario::query()
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
        $ids = UsuarioOperativoSupport::filtrarIdsActivos($usuarioIds);
        $quedan = Configuracion_DifcambioFacturaDestinatario::query()
            ->whereNotIn('usuario_id', $ids === [] ? [-1] : $ids);
        EloquentAuditDeleteSupport::each($quedan);

        foreach ($ids as $usuarioId) {
            Configuracion_DifcambioFacturaDestinatario::query()->firstOrCreate([
                'usuario_id' => $usuarioId,
            ]);
        }
    }

    /**
     * Recepciones en período cerrado cuyo TC no es el de la factura.
     *
     * @param  iterable<Recepcion_Proveedor>  $recepciones
     * @return list<array{id: int, tc: float, pct: float, fecha: string}>
     */
    public static function recepcionesCerradasConOtroTc(
        iterable $recepciones,
        float $cotizacionFactura,
        int $monedaFacturaId,
        int $empresaId,
    ): array {
        if (! ComprobanteProveedorMonedaMotor::esMonedaExtranjera($monedaFacturaId) || $cotizacionFactura <= 0) {
            return [];
        }

        $cerradas = [];
        foreach ($recepciones as $recepcion) {
            if (! $recepcion instanceof Recepcion_Proveedor) {
                continue;
            }
            if (! ComprobanteProveedorMonedaMotor::esMonedaExtranjera((int) ($recepcion->moneda_id ?: 1))) {
                continue;
            }
            if ((int) ($recepcion->moneda_id ?: 1) !== ComprobanteProveedorMonedaMotor::normalizarMonedaId($monedaFacturaId)) {
                continue;
            }

            $tc = (float) ($recepcion->cotizacion ?: 0);
            if ($tc <= ComprobanteProveedorMonedaMotor::COTIZACION_MINIMA) {
                continue;
            }
            if (abs($tc - $cotizacionFactura) <= self::EPS_COTIZACION) {
                continue;
            }

            $fecha = $recepcion->fecha ? $recepcion->fecha->format('Y-m-d') : '';
            if ($fecha === '' || ! PeriodoContableCierreSupport::fechaEnPeriodoCerrado(
                $empresaId > 0 ? $empresaId : (int) ($recepcion->empresa_id ?? 0),
                $fecha,
                PeriodoContableCierreSupport::ALCANCE_RECEPCION_PROVEEDOR
            )) {
                continue;
            }

            $cerradas[] = [
                'id' => (int) $recepcion->id,
                'tc' => $tc,
                'pct' => $tc > 0 ? (abs($cotizacionFactura - $tc) / $tc) * 100.0 : 0.0,
                'fecha' => $fecha,
            ];
        }

        return $cerradas;
    }

    public static function porcentajeMaximo(array $cerradas): float
    {
        $max = 0.0;
        foreach ($cerradas as $fila) {
            $max = max($max, (float) ($fila['pct'] ?? 0));
        }

        return $max;
    }

    public static function requiereAprobacion(float $porcentaje, int $empresaId = 0): bool
    {
        return $porcentaje > self::toleranciaPct($empresaId) + 0.0001;
    }

    /**
     * Participación de cada recepción en la provisión que esta factura cancela,
     * y la diferencia de cambio en pesos de las que están en período cerrado.
     *
     * @return array{
     *     aplica: bool,
     *     requiere_aprobacion: bool,
     *     porcentaje_max: float,
     *     tolerancia: float,
     *     tramos: list<array<string, mixed>>
     * }
     */
    public static function analizar(Comprobante_Proveedor $comprobante): array
    {
        $vacio = [
            'aplica' => false,
            'requiere_aprobacion' => false,
            'porcentaje_max' => 0.0,
            'tolerancia' => self::toleranciaPct((int) ($comprobante->empresa_id ?? 0)),
            'tramos' => [],
        ];

        $comprobante->loadMissing([
            'comprobante_proveedor_recepciones.recepcion_proveedores',
            'proveedores',
            'ordencompras',
        ]);

        $factura = [
            'moneda_id' => ComprobanteProveedorMonedaMotor::normalizarMonedaId($comprobante->moneda_id),
            'cotizacion' => (float) ($comprobante->cotizacion ?: 0),
            'fecha' => $comprobante->fechacomprobante instanceof \DateTimeInterface
                ? $comprobante->fechacomprobante->format('Y-m-d')
                : substr((string) ($comprobante->fechacomprobante ?? ''), 0, 10),
        ];
        if (! ComprobanteProveedorMonedaMotor::esMonedaExtranjera($factura['moneda_id'])
            || $factura['cotizacion'] <= ComprobanteProveedorMonedaMotor::COTIZACION_MINIMA) {
            return $vacio;
        }

        $empresaId = (int) ($comprobante->empresa_id ?? 0);
        $brutos = [];
        foreach ($comprobante->comprobante_proveedor_recepciones as $vinculo) {
            $recepcion = $vinculo->recepcion_proveedores;
            if (! $recepcion || ! ComprobanteProveedorComContabilidadSupport::recepcionTieneProvisionContable($recepcion)) {
                continue;
            }

            $preview = app(RecepcionProveedorAsientoService::class)->previewAsientoContable($recepcion);
            $nominal = ComprobanteProveedorImporteComparacionComSupport::desdeRecepcionAFactura(
                (float) ($preview['total_debe'] ?? 0),
                (int) ($recepcion->moneda_id ?: 1),
                (float) ($recepcion->cotizacion ?: 0),
                $factura['moneda_id'],
                $factura['cotizacion'],
                $recepcion->fecha instanceof \DateTimeInterface
                    ? $recepcion->fecha->format('Y-m-d')
                    : substr((string) ($recepcion->fecha ?? ''), 0, 10),
                $factura['fecha'],
            );
            if ($nominal <= 0.0001) {
                continue;
            }

            $tc = (float) ($recepcion->cotizacion ?: 0);
            $fecha = $recepcion->fecha ? $recepcion->fecha->format('Y-m-d') : '';
            $cerrada = $fecha !== '' && PeriodoContableCierreSupport::fechaEnPeriodoCerrado(
                $empresaId,
                $fecha,
                PeriodoContableCierreSupport::ALCANCE_RECEPCION_PROVEEDOR
            );
            $distinta = $tc > ComprobanteProveedorMonedaMotor::COTIZACION_MINIMA
                && abs($tc - $factura['cotizacion']) > self::EPS_COTIZACION
                && ComprobanteProveedorMonedaMotor::esMonedaExtranjera((int) ($recepcion->moneda_id ?: 1))
                && (int) ($recepcion->moneda_id ?: 1) === $factura['moneda_id'];

            $brutos[] = [
                'recepcion' => $recepcion,
                'nominal' => $nominal,
                'tc' => $tc,
                'ajusta' => $cerrada && $distinta,
            ];
        }

        if ($brutos === []) {
            return $vacio;
        }

        $suma = round(array_sum(array_column($brutos, 'nominal')), 2);
        $ya = ComprobanteProveedorImporteYaFacturadoLegajoSupport::sumarComparableParaProvisionCom(
            array_map(static fn (array $b) => (int) $b['recepcion']->id, $brutos),
            (int) ($comprobante->ordencompra_id ?? 0),
            ComprobanteProveedorFlujoOcComFacSupport::esOcAnticipada($comprobante->ordencompras),
            (int) ($comprobante->id ?? 0) > 0 ? (int) $comprobante->id : null,
            $factura['moneda_id'],
            $factura['cotizacion'],
            $factura['fecha'],
        );
        $disponible = ComprobanteProveedorImporteYaFacturadoLegajoSupport::provisionDisponible(
            $suma,
            (float) ($ya['importe'] ?? 0)
        );
        if ($disponible <= 0.0001 || $suma <= 0) {
            return $vacio;
        }

        $factor = $disponible / $suma;
        $tramos = [];
        $asignado = 0.0;
        $ultimo = count($brutos) - 1;
        foreach ($brutos as $i => $bruto) {
            $share = $i === $ultimo
                ? round($disponible - $asignado, 2)
                : round($bruto['nominal'] * $factor, 2);
            $asignado = round($asignado + $share, 2);
            if ($share <= 0.0001) {
                continue;
            }

            /** @var Recepcion_Proveedor $recepcion */
            $recepcion = $bruto['recepcion'];
            $tc = (float) $bruto['tc'];
            $pesos = 0.0;
            $pct = 0.0;
            if ($bruto['ajusta']) {
                $pesos = round($share * $tc, 2) - round($share * $factura['cotizacion'], 2);
                $pct = $tc > 0 ? (abs($factura['cotizacion'] - $tc) / $tc) * 100.0 : 0.0;
            }

            $tramos[] = [
                'recepcion_id' => (int) $recepcion->id,
                'ordencompra_id' => (int) ($comprobante->ordencompra_id ?? 0),
                'empresa_id' => $empresaId,
                'proveedor_id' => (int) ($comprobante->proveedor_id ?? 0),
                'tc_recepcion' => $tc,
                'tc_factura' => $factura['cotizacion'],
                'nominal_me' => $share,
                'importe_pesos' => $pesos,
                'porcentaje' => $pct,
                'ajusta' => (bool) $bruto['ajusta'] && abs($pesos) >= 0.01,
                'cotizacion_linea' => $bruto['ajusta'] ? $tc : $factura['cotizacion'],
            ];
        }

        $ajustan = array_values(array_filter($tramos, static fn (array $t) => $t['ajusta']));
        $pctMax = self::porcentajeMaximo($ajustan);

        return [
            'aplica' => $ajustan !== [],
            'requiere_aprobacion' => $ajustan !== [] && self::requiereAprobacion($pctMax, $empresaId),
            'porcentaje_max' => $pctMax,
            'tolerancia' => self::toleranciaPct($empresaId),
            'tramos' => $tramos,
        ];
    }

    public static function estaAprobada(int $comprobanteId): bool
    {
        if ($comprobanteId <= 0 || ! \Illuminate\Support\Facades\Schema::hasTable('comprobante_proveedor_difcambio')) {
            return false;
        }

        return Comprobante_Proveedor_Difcambio::query()
            ->where('comprobante_proveedor_id', $comprobanteId)
            ->where('estado', Comprobante_Proveedor_Difcambio::ESTADO_APROBADA)
            ->exists();
    }

    public static function cuentaDiferenciaCambioId(Comprobante_Proveedor $comprobante): int
    {
        $empresaId = (int) ($comprobante->empresa_id ?? 0);
        $comprobante->loadMissing('proveedores');
        $proveedor = $comprobante->proveedores;
        $ids = array_filter([
            (int) ($proveedor->cuentacontable_id ?? 0),
            (int) ($proveedor->cuentacontableme_id ?? 0),
        ]);
        foreach ($ids as $id) {
            $cuenta = Cuentacontable::query()->find($id);
            $dcId = (int) ($cuenta->cuentacontable_difcambio_id ?? 0);
            if ($dcId > 0 && $dcId !== (int) $id) {
                return $empresaId > 0
                    ? CuentacontableEmpresaHomologacionSupport::idParaEmpresa($dcId, $empresaId)
                    : $dcId;
            }
        }

        if ($empresaId > 0) {
            $fallback = Cuentacontable::query()
                ->where('empresa_id', $empresaId)
                ->where('codigo', self::CODIGO_CUENTA)
                ->value('id');
            if ($fallback) {
                return (int) $fallback;
            }
        }

        return 0;
    }
}
