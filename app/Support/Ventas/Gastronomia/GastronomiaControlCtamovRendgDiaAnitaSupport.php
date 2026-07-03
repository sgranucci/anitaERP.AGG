<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\Support\Ventas\GastronomiaAnitaImportEmpresaSupport;
use App\Support\Ventas\KandikoAnitaVentaTipoSupport;

/**
 * Cuadre diario: cinco totales que deben coincidir por jornada.
 *
 * 1. Contabilidad Anita (ctamov): Σ haber − debe en cuentas ventas + IVA débito + IVA crédito fiscal.
 * 2. Rendiciones Anita (rendgastro): Σ rendg_total_z − Σ rendg_tot_nc.
 * 3. Venta Anita (Informix): Σ ven_monto (facturas − NC) + RMV Z vending del día en rendgastro Anita
 *    (solo cabeceras vending leídas del bridge; 0 si no hay filas).
 * 4. Venta ERP (MySQL): Σ total comprobante con signo tipotransaccion + maquinavending_rendicion (ver venta_erp_tabla / venta_erp_vending).
 * 5. Flash (caja Informix): Σ flash_ayb + flash_estac por jornada (tabla flash).
 */
final class GastronomiaControlCtamovRendgDiaAnitaSupport
{
    /** @var array<int, list<int>> */
    private const CODIGOS_VENTAS_POR_EMPRESA = [
        1 => [413010001, 414010001, 415010003, 414020001],
        2 => [413010001, 414010001, 415010003, 414020001],
        3 => [413010001, 414010001, 415010003, 414020001],
    ];

    /** @var array<int, list<int>> IVA crédito fiscal (reversa IVA NC en cierres estacionamiento, ctav 114010-011). */
    private const CUENTAS_IVA_CREDITO_FISCAL_POR_EMPRESA = [
        1 => [114010011],
        2 => [114010011],
        3 => [114010011],
    ];

    /**
     * @return list<int>
     */
    public function codigosCtamovEmpresa(int $empresaId): array
    {
        $map = (array) config('gastronomia.control_ctamov_rendg_dia_anita.cuentas_ventas_por_empresa', self::CODIGOS_VENTAS_POR_EMPRESA);
        $ventas = array_values(array_filter(array_map('intval', $map[$empresaId] ?? $map[(string) $empresaId] ?? [])));
        if ($ventas === [] && isset(self::CODIGOS_VENTAS_POR_EMPRESA[$empresaId])) {
            $ventas = self::CODIGOS_VENTAS_POR_EMPRESA[$empresaId];
        }

        $cfg = CierreJornadaProcesoConfigSupport::paraEmpresaConDetalle($empresaId);
        $ivaCodigo = (int) preg_replace('/\D+/', '', (string) ($cfg['cuenta_iva_codigo'] ?? ''));
        if ($ivaCodigo > 0) {
            $ventas[] = $ivaCodigo;
        }

        $ivaCreditoMap = (array) config(
            'gastronomia.control_ctamov_rendg_dia_anita.cuentas_iva_credito_fiscal_por_empresa',
            self::CUENTAS_IVA_CREDITO_FISCAL_POR_EMPRESA,
        );
        $ivaCredito = array_values(array_filter(array_map(
            'intval',
            $ivaCreditoMap[$empresaId] ?? $ivaCreditoMap[(string) $empresaId] ?? [],
        )));
        if ($ivaCredito === [] && isset(self::CUENTAS_IVA_CREDITO_FISCAL_POR_EMPRESA[$empresaId])) {
            $ivaCredito = self::CUENTAS_IVA_CREDITO_FISCAL_POR_EMPRESA[$empresaId];
        }
        $ventas = array_merge($ventas, $ivaCredito);

        return array_values(array_unique(array_filter($ventas, static fn (int $c): bool => $c > 0)));
    }

    /**
     * @param  list<object>  $ctamovCache
     * @return array<string, list<array<string, mixed>>>
     */
    public function indexarCtamovPorFecha(array $ctamovCache): array
    {
        $map = [];
        foreach ($ctamovCache as $fila) {
            $fechaEntera = (int) preg_replace('/\D+/', '', (string) ($fila->ctav_fecha ?? ''));
            if ($fechaEntera <= 0) {
                continue;
            }
            $fecha = substr((string) $fechaEntera, 0, 4).'-'.substr((string) $fechaEntera, 4, 2).'-'.substr((string) $fechaEntera, 6, 2);
            $map[$fecha][] = [
                'ctav_cuenta' => $fila->ctav_cuenta ?? 0,
                'ctav_importe' => $fila->ctav_importe ?? 0,
                'ctav_d_h' => $fila->ctav_d_h ?? 'D',
            ];
        }

        return $map;
    }

    /**
     * @param  list<object>  $ventaCache
     * @return array<string, list<object>>
     */
    public function indexarVentaAnitaPorJornada(array $ventaCache, string $empresaCodigo): array
    {
        $map = [];

        foreach ($ventaCache as $fila) {
            if (GastronomiaAnitaImportEmpresaSupport::usaFiltroEmpresaAnita()) {
                $empCab = trim((string) ($fila->ven_empresa ?? ''));
                if ($empCab !== '' && $empCab !== $empresaCodigo) {
                    continue;
                }
            }

            $fechaJornada = $this->fechaJornadaDesdeAnita((string) ($fila->ven_fecha_vto ?? ''));
            if ($fechaJornada === null) {
                continue;
            }

            $map[$fechaJornada][] = $fila;
        }

        return $map;
    }

    /**
     * @param  list<array<string, mixed>>  $filasCtamov
     * @param  list<int>  $codigosCuenta
     */
    public function totalCtamovVentasIva(array $filasCtamov, array $codigosCuenta): float
    {
        return GastronomiaFacturacionAuditoriaCtamovSupport::sumarVentasDesdeCtamov($filasCtamov, $codigosCuenta);
    }

    /**
     * Σ rendg_total_z del día.
     *
     * @param  list<object>  $cabecerasRendg
     */
    public function totalRendgBrutoZ(array $cabecerasRendg): float
    {
        $total = 0.0;

        foreach ($cabecerasRendg as $fila) {
            $total += round((float) ($fila->rendg_total_z ?? 0), 2);
        }

        return round($total, 2);
    }

    /**
     * Σ rendg_tot_nc del día.
     *
     * @param  list<object>  $cabecerasRendg
     */
    public function totalRendgNotasCredito(array $cabecerasRendg): float
    {
        $total = 0.0;

        foreach ($cabecerasRendg as $fila) {
            $total += round((float) ($fila->rendg_tot_nc ?? 0), 2);
        }

        return round($total, 2);
    }

    /**
     * Rendiciones netas: Σ rendg_total_z − Σ rendg_tot_nc (suma cabecera a cabecera).
     *
     * @param  list<object>  $cabecerasRendg
     */
    public function totalRendgNetoDia(array $cabecerasRendg): float
    {
        $total = 0.0;

        foreach ($cabecerasRendg as $fila) {
            $z = round((float) ($fila->rendg_total_z ?? 0), 2);
            $nc = round((float) ($fila->rendg_tot_nc ?? 0), 2);
            $total += round($z - $nc, 2);
        }

        return round($total, 2);
    }

    /**
     * Venta Informix neta: Σ ven_monto (FAC suma, NC resta).
     *
     * @param  list<object>  $cabecerasVenta
     */
    public function totalVentaAnitaNeto(array $cabecerasVenta): float
    {
        $total = 0.0;

        foreach ($cabecerasVenta as $cab) {
            $tipo = strtoupper(trim((string) ($cab->ven_tipo ?? '')));
            $monto = round((float) ($cab->ven_monto ?? 0), 2);
            if (str_starts_with($tipo, 'NC')) {
                $total -= abs($monto);
            } else {
                $total += $monto;
            }
        }

        return round($total, 2);
    }

    /**
     * Cabeceras venta Anita del día (deduplicadas, sin FSL/FBI).
     *
     * @param  list<object>  $cabecerasDia
     * @return list<object>
     */
    public function cabecerasVentaAnitaDia(array $cabecerasDia): array
    {
        return GastronomiaAuditoriaMesTotalesAnitaSupport::filtrarCabecerasIncluidas(
            $this->deduplicarCabecerasComprobante($cabecerasDia),
        );
    }

    public function cuadra(float $a, float $b, float $tolerancia): bool
    {
        return abs(round($a, 2) - round($b, 2)) <= $tolerancia;
    }

    /**
     * @param  list<float>  $totales
     */
    public function cuadranTodos(array $totales, float $tolerancia): bool
    {
        if ($totales === []) {
            return true;
        }

        $referencia = round((float) $totales[0], 2);
        foreach ($totales as $total) {
            if (! $this->cuadra($referencia, (float) $total, $tolerancia)) {
                return false;
            }
        }

        return true;
    }

    public function fechaJornadaDesdeAnita(string $fechaEntera): ?string
    {
        $digits = preg_replace('/\D+/', '', $fechaEntera);
        if ($digits === null || strlen($digits) !== 8) {
            return null;
        }

        return substr($digits, 0, 4).'-'.substr($digits, 4, 2).'-'.substr($digits, 6, 2);
    }

    /**
     * Una cabecera por comprobante (letra+sucursal+nro); FAK prevalece sobre FAC duplicado.
     *
     * @param  list<object>  $cabeceras
     * @return list<object>
     */
    private function deduplicarCabecerasComprobante(array $cabeceras): array
    {
        $map = [];

        foreach ($cabeceras as $cab) {
            $sucursal = (int) preg_replace('/\D+/', '', (string) ($cab->ven_sucursal ?? ''));
            $nro = (int) ($cab->ven_nro ?? 0);
            $letra = strtoupper(trim((string) ($cab->ven_letra ?? 'B')));
            if ($sucursal <= 0 || $nro <= 0) {
                continue;
            }

            $key = $letra.'|'.$sucursal.'|'.$nro;
            $existente = $map[$key] ?? null;
            if ($existente === null) {
                $map[$key] = $cab;

                continue;
            }

            $tipoNuevo = strtoupper(trim((string) ($cab->ven_tipo ?? '')));
            $tipoExist = strtoupper(trim((string) ($existente->ven_tipo ?? '')));
            if ($tipoExist !== KandikoAnitaVentaTipoSupport::TIPO_VENTA_BRIDGE
                && $tipoNuevo === KandikoAnitaVentaTipoSupport::TIPO_VENTA_BRIDGE) {
                $map[$key] = $cab;
            }
        }

        return array_values($map);
    }
}
