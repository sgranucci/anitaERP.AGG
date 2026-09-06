<?php

namespace App\Support\Contable\MayorConcepto;

use App\Support\Contable\MayorFuenteConsultaSupport;

/**
 * Elige de dónde leer el Mayor por concepto según fecha + modo de consulta.
 *
 * Hasta `fuente_erp_hasta` inclusive puede leer del ERP; después siempre Anita.
 * El modo `anita` fuerza bridge también en el tramo migrado. El modo `erp`/`auto`
 * parte el rango en tramos (como el mayor plano) y fusiona las lecturas.
 */
class MayorConceptoLectorHibrido implements MayorConceptoLectorInterface
{
    private MayorConceptoLectorInterface $activo;

    private string $modo = MayorFuenteConsultaSupport::MODO_AUTO;

    /** @var array<string, mixed>|null */
    private ?array $ultimosTramos = null;

    public function __construct(
        private MayorConceptoAnitaBridgeReader $anita,
        private MayorConceptoErpReader $erp,
    ) {
        $this->activo = $anita;
    }

    public function setModoFuente(string $modo): void
    {
        $this->modo = MayorFuenteConsultaSupport::normalizarModo($modo);
    }

    public function modoFuente(): string
    {
        return $this->modo;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function ultimosTramos(): ?array
    {
        return $this->ultimosTramos;
    }

    /**
     * Origen elegido para el último período cargado: 'anita', 'erp' o 'hibrido'.
     */
    public function origenActivo(): string
    {
        if ($this->ultimosTramos !== null) {
            $usaErp = (bool) ($this->ultimosTramos['usa_erp'] ?? false);
            $usaAnita = (bool) ($this->ultimosTramos['usa_anita'] ?? false);
            if ($usaErp && $usaAnita) {
                return 'hibrido';
            }
            if ($usaErp) {
                return 'erp';
            }

            return 'anita';
        }

        return $this->activo === $this->erp ? 'erp' : 'anita';
    }

    private function configKey(): string
    {
        return 'contable.mayor_concepto.fuente_erp_hasta';
    }

    /**
     * @return array<string, mixed>
     */
    private function resolver(int $fechaDesde, int $fechaHasta): array
    {
        return $this->ultimosTramos = MayorFuenteConsultaSupport::resolverTramos(
            $fechaDesde,
            $fechaHasta,
            $this->modo,
            $this->configKey(),
        );
    }

    private function fijarActivoParaDetalle(array $tramos): void
    {
        // Lookups on-demand: ERP si hubo tramo ERP (datos nativos); si no, Anita.
        $this->activo = ($tramos['usa_erp'] ?? false) ? $this->erp : $this->anita;
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     * @return array<string, mixed>
     */
    private function fusionarPeriodos(array $a, array $b): array
    {
        $listas = ['subdiario', 'ctamov', 'auxpag', 'ctaconc', 'promae', 'errores'];
        $out = $a;
        foreach ($listas as $clave) {
            $out[$clave] = array_merge(
                is_array($a[$clave] ?? null) ? $a[$clave] : [],
                is_array($b[$clave] ?? null) ? $b[$clave] : [],
            );
        }

        return $out;
    }

    public function precargarPeriodoEmpresas(array $empresaIds, int $fechaDesde, int $fechaHasta): void
    {
        $tramos = $this->resolver($fechaDesde, $fechaHasta);
        $this->fijarActivoParaDetalle($tramos);

        if ($tramos['usa_erp'] && ! $tramos['usa_anita']) {
            $this->erp->precargarPeriodoEmpresas(
                $empresaIds,
                (int) $tramos['tramo_erp_desde'],
                (int) $tramos['tramo_erp_hasta'],
            );

            return;
        }

        if ($tramos['usa_anita'] && ! $tramos['usa_erp']) {
            $this->anita->precargarPeriodoEmpresas(
                $empresaIds,
                (int) $tramos['tramo_anita_desde'],
                (int) $tramos['tramo_anita_hasta'],
            );

            return;
        }

        if ($tramos['usa_erp']) {
            $this->erp->precargarPeriodoEmpresas(
                $empresaIds,
                (int) $tramos['tramo_erp_desde'],
                (int) $tramos['tramo_erp_hasta'],
            );
        }
        if ($tramos['usa_anita']) {
            $this->anita->precargarPeriodoEmpresas(
                $empresaIds,
                (int) $tramos['tramo_anita_desde'],
                (int) $tramos['tramo_anita_hasta'],
            );
        }
    }

    public function cargarPeriodo(int $empresaId, int $fechaDesde, int $fechaHasta): array
    {
        $tramos = $this->resolver($fechaDesde, $fechaHasta);
        $this->fijarActivoParaDetalle($tramos);

        if ($tramos['usa_erp'] && ! $tramos['usa_anita']) {
            return $this->erp->cargarPeriodo(
                $empresaId,
                (int) $tramos['tramo_erp_desde'],
                (int) $tramos['tramo_erp_hasta'],
            );
        }

        if ($tramos['usa_anita'] && ! $tramos['usa_erp']) {
            return $this->anita->cargarPeriodo(
                $empresaId,
                (int) $tramos['tramo_anita_desde'],
                (int) $tramos['tramo_anita_hasta'],
            );
        }

        $erp = $this->erp->cargarPeriodo(
            $empresaId,
            (int) $tramos['tramo_erp_desde'],
            (int) $tramos['tramo_erp_hasta'],
        );
        $anita = $this->anita->cargarPeriodo(
            $empresaId,
            (int) $tramos['tramo_anita_desde'],
            (int) $tramos['tramo_anita_hasta'],
        );

        return $this->fusionarPeriodos($erp, $anita);
    }

    public function cargarAuxpagHistorico(
        int $empresaId,
        string $tipo,
        int $rec,
        int $fecha,
        string $proveedor,
        int $sucursalOp,
        array &$errores,
    ): array {
        return $this->activo->cargarAuxpagHistorico($empresaId, $tipo, $rec, $fecha, $proveedor, $sucursalOp, $errores);
    }

    public function cargarComSubdiario(int $empresaId, string $tipo, string $letra, int $sucursal, int $nro, array &$errores): array
    {
        return $this->activo->cargarComSubdiario($empresaId, $tipo, $letra, $sucursal, $nro, $errores);
    }

    public function cargarComSubdiarioLote(int $empresaId, array $clavesCom, array &$errores): array
    {
        return $this->activo->cargarComSubdiarioLote($empresaId, $clavesCom, $errores);
    }

    public function cargarSubdiarioFacturaCompras(
        int $empresaId,
        string $tipo,
        string $letra,
        int $sucursal,
        int $nro,
        int $nroInterno,
        string $proveedor,
        array &$errores,
    ): array {
        return $this->activo->cargarSubdiarioFacturaCompras(
            $empresaId, $tipo, $letra, $sucursal, $nro, $nroInterno, $proveedor, $errores
        );
    }

    public function cargarCtamovPorAsiento(int $empresaId, int $nroAsiento, array &$errores): array
    {
        return $this->activo->cargarCtamovPorAsiento($empresaId, $nroAsiento, $errores);
    }

    public function cargarAplicpedFactura(
        string $proveedor,
        string $tipo,
        string $letra,
        int $sucursal,
        int $nro,
        array &$errores,
    ): array {
        return $this->activo->cargarAplicpedFactura($proveedor, $tipo, $letra, $sucursal, $nro, $errores);
    }

    public function cargarAplicpedPorReferencia(
        string $refTipo,
        string $refLetra,
        int $refSucursal,
        int $refNro,
        string $proveedor,
        array &$errores,
    ): array {
        return $this->activo->cargarAplicpedPorReferencia(
            $refTipo, $refLetra, $refSucursal, $refNro, $proveedor, $errores
        );
    }

    public function cargarAplicpedPorFacturas(array $facturas, array &$errores): array
    {
        return $this->activo->cargarAplicpedPorFacturas($facturas, $errores);
    }

    public function cargarPromae(string $proveedor, array &$errores): ?object
    {
        return $this->activo->cargarPromae($proveedor, $errores);
    }

    public function cargarPromaePorProveedores(array $proveedores, array &$errores): array
    {
        return $this->activo->cargarPromaePorProveedores($proveedores, $errores);
    }

    public function conceptoDesdeOrdenCompra(int $empresaId, int $nroOc, array &$errores): int
    {
        return $this->activo->conceptoDesdeOrdenCompra($empresaId, $nroOc, $errores);
    }

    public function fallosLectura(): int
    {
        return $this->anita->fallosLectura() + $this->erp->fallosLectura();
    }
}
