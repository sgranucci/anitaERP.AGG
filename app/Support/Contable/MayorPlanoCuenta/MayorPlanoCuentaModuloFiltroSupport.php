<?php

declare(strict_types=1);

namespace App\Support\Contable\MayorPlanoCuenta;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Filtro opcional del mayor plano por módulo, al estilo del subdiario Anita
 * (subd_sistema V ventas, C compras, T tesorería/caja).
 *
 * Cuentas a pagar no es una letra propia: en Anita las órdenes de pago viven
 * en el sistema T (OPP, OPA, APA) y se separan de la caja.
 */
final class MayorPlanoCuentaModuloFiltroSupport
{
    public const TODOS = '';

    public const VENTAS = 'ventas';

    public const COMPRAS = 'compras';

    public const CUENTAS_PAGAR = 'cuentas_pagar';

    public const CAJA = 'caja';

    /** @var list<string> */
    public const TIPOS_CUENTAS_PAGAR = ['OPP', 'OPA', 'APA'];

    /** @var list<string> */
    private const MODULOS = [
        self::VENTAS,
        self::COMPRAS,
        self::CUENTAS_PAGAR,
        self::CAJA,
    ];

    public static function normalizar(mixed $modulo): string
    {
        $modulo = strtolower(trim((string) $modulo));

        return in_array($modulo, self::MODULOS, true) ? $modulo : self::TODOS;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function desdeFiltros(array $filtros): string
    {
        $modulo = self::normalizar($filtros['modulo_movimientos'] ?? '');
        if ($modulo === self::TODOS && ! empty($filtros['solo_movimientos_ventas'])) {
            return self::VENTAS;
        }

        return $modulo;
    }

    public static function etiqueta(string $modulo): string
    {
        return match (self::normalizar($modulo)) {
            self::VENTAS => 'Ventas',
            self::COMPRAS => 'Compras',
            self::CUENTAS_PAGAR => 'Cuentas a pagar',
            self::CAJA => 'Caja',
            default => 'Todos',
        };
    }

    public static function letra(string $modulo): string
    {
        return match (self::normalizar($modulo)) {
            self::VENTAS => 'V',
            self::COMPRAS => 'C',
            self::CUENTAS_PAGAR => 'OP',
            self::CAJA => 'T',
            default => '',
        };
    }

    public static function tituloConsulta(string $modulo): string
    {
        $modulo = self::normalizar($modulo);
        if ($modulo === self::TODOS) {
            return 'Mayor analítico por cuenta contable';
        }

        return 'Mayor analítico por cuenta — solo movimientos de '.self::etiqueta($modulo);
    }

    /**
     * @param  array<string, mixed>  $movimiento
     */
    public static function esMovimiento(array $movimiento, string $modulo): bool
    {
        return match (self::normalizar($modulo)) {
            self::VENTAS => MayorPlanoCuentaVentasFiltroSupport::esMovimientoVentas($movimiento),
            self::COMPRAS => self::esCompras($movimiento),
            self::CUENTAS_PAGAR => self::esCuentasPagar($movimiento),
            self::CAJA => self::esCaja($movimiento),
            default => true,
        };
    }

    /**
     * Condición Informix para ctamov / subdiario / subhist.
     */
    public static function condicionSqlAnita(string $colSistema, ?string $colTipo, string $modulo): string
    {
        $colSistema = self::columnaSql($colSistema);
        $colTipo = $colTipo !== null ? self::columnaSql($colTipo) : '';
        if ($colSistema === '') {
            return '';
        }

        $modulo = self::normalizar($modulo);

        return match ($modulo) {
            self::VENTAS => MayorPlanoCuentaVentasFiltroSupport::condicionSqlSistema($colSistema),
            self::COMPRAS => ' AND '.$colSistema."='C'",
            self::CUENTAS_PAGAR => $colTipo === ''
                ? ''
                : ' AND '.$colSistema."='T' AND TRIM(".$colTipo.') IN ('.self::listaTiposSql().')',
            self::CAJA => $colTipo === ''
                ? ' AND '.$colSistema."='T'"
                : ' AND '.$colSistema."='T' AND TRIM(".$colTipo.') NOT IN ('.self::listaTiposSql().')',
            default => '',
        };
    }

    /**
     * @param  list<string>  $columnasAnita
     */
    public static function aplicarFiltroErpQuery(Builder $query, string $modulo, array $columnasAnita = []): void
    {
        $modulo = self::normalizar($modulo);
        if ($modulo === self::VENTAS) {
            MayorPlanoCuentaVentasFiltroSupport::aplicarFiltroErpQuery($query, $columnasAnita);

            return;
        }
        if ($modulo === self::TODOS) {
            return;
        }

        $tieneSistema = in_array('anita_sistema', $columnasAnita, true);
        $tieneTipo = in_array('anita_tipo', $columnasAnita, true);

        if ($modulo === self::COMPRAS) {
            $query->where(function (Builder $q) use ($tieneSistema): void {
                $q->where('t.abreviatura', 'COM');
                if ($tieneSistema) {
                    $q->orWhere('a.anita_sistema', 'C');
                }
                if (Schema::hasColumn('asiento', 'comprobante_proveedor_id')) {
                    $q->orWhere('a.comprobante_proveedor_id', '>', 0);
                }
                if (Schema::hasColumn('asiento', 'recepcionproveedor_id')) {
                    $q->orWhere('a.recepcionproveedor_id', '>', 0);
                }
                if (Schema::hasColumn('asiento', 'compra_id')) {
                    $q->orWhere('a.compra_id', '>', 0);
                }
                if (Schema::hasColumn('asiento_movimiento', 'comprobante_proveedor_id')) {
                    $q->orWhere('am.comprobante_proveedor_id', '>', 0);
                }
                $q->orWhere('a.observacion', 'like', '%[SUBH] C %')
                    ->orWhere('a.observacion', 'like', '%[SUBD] C %')
                    ->orWhere('a.observacion', 'like', '%[SUBHIST] C %')
                    ->orWhere('a.observacion', 'like', '%[SUBDIARIO] C %');
            });
            self::excluirCuentasPagarErp($query, $tieneTipo);

            return;
        }

        if ($modulo === self::CUENTAS_PAGAR) {
            $query->where(function (Builder $q) use ($tieneTipo): void {
                $aplico = false;
                if (Schema::hasColumn('asiento', 'pagoproveedor_id')) {
                    $q->where('a.pagoproveedor_id', '>', 0);
                    $aplico = true;
                }
                if ($tieneTipo) {
                    $metodo = $aplico ? 'orWhereIn' : 'whereIn';
                    $q->{$metodo}('a.anita_tipo', self::TIPOS_CUENTAS_PAGAR);
                    $aplico = true;
                }
                if (! $aplico) {
                    $q->whereRaw('1 = 0');
                }
            });

            return;
        }

        $query->where(function (Builder $q) use ($tieneSistema): void {
            $q->where('t.abreviatura', 'TES');
            if ($tieneSistema) {
                $q->orWhere('a.anita_sistema', 'T');
            }
            foreach (['caja_movimiento_id', 'cobranza_id', 'remesa_id'] as $fk) {
                if (Schema::hasColumn('asiento', $fk)) {
                    $q->orWhere('a.'.$fk, '>', 0);
                }
            }
            $q->orWhere('a.observacion', 'like', '%[SUBH] T %')
                ->orWhere('a.observacion', 'like', '%[SUBD] T %')
                ->orWhere('a.observacion', 'like', '%[SUBHIST] T %')
                ->orWhere('a.observacion', 'like', '%[SUBDIARIO] T %');
        });
        self::excluirCuentasPagarErp($query, $tieneTipo);
        if ($tieneSistema) {
            $query->where(function (Builder $q): void {
                $q->whereNull('a.anita_sistema')
                    ->orWhere('a.anita_sistema', '')
                    ->orWhereNotIn('a.anita_sistema', ['V', 'C']);
            });
        }
    }

    /**
     * @param  array<string, mixed>  $movimiento
     */
    private static function esCompras(array $movimiento): bool
    {
        if (self::esCuentasPagar($movimiento)) {
            return false;
        }
        if (self::sistema($movimiento) === 'T' || self::tipoAsiento($movimiento) === 'TES') {
            return false;
        }
        if (self::sistema($movimiento) === 'C') {
            return true;
        }
        if (self::tipoAsiento($movimiento) === 'COM') {
            return true;
        }
        if ((int) ($movimiento['erp_mov_comprobante_proveedor_id'] ?? 0) > 0) {
            return true;
        }

        return self::fk($movimiento, 'comprobante_proveedor_id') > 0
            || self::fk($movimiento, 'recepcionproveedor_id') > 0
            || self::fk($movimiento, 'compra_id') > 0;
    }

    /**
     * @param  array<string, mixed>  $movimiento
     */
    private static function esCuentasPagar(array $movimiento): bool
    {
        if (self::fk($movimiento, 'pagoproveedor_id') > 0) {
            return true;
        }

        return in_array(self::tipoComp($movimiento), self::TIPOS_CUENTAS_PAGAR, true);
    }

    /**
     * @param  array<string, mixed>  $movimiento
     */
    private static function esCaja(array $movimiento): bool
    {
        if (self::esCuentasPagar($movimiento) || self::esCompras($movimiento)) {
            return false;
        }
        if (MayorPlanoCuentaVentasFiltroSupport::esMovimientoVentas($movimiento)) {
            return false;
        }
        if (self::sistema($movimiento) === 'T' || self::tipoAsiento($movimiento) === 'TES') {
            return true;
        }

        return self::fk($movimiento, 'caja_movimiento_id') > 0
            || self::fk($movimiento, 'cobranza_id') > 0
            || self::fk($movimiento, 'remesa_id') > 0;
    }

    private static function excluirCuentasPagarErp(Builder $query, bool $tieneTipo): void
    {
        if (Schema::hasColumn('asiento', 'pagoproveedor_id')) {
            $query->where(function (Builder $q): void {
                $q->whereNull('a.pagoproveedor_id')
                    ->orWhere('a.pagoproveedor_id', '<=', 0);
            });
        }
        if ($tieneTipo) {
            $query->where(function (Builder $q): void {
                $q->whereNull('a.anita_tipo')
                    ->orWhere('a.anita_tipo', '')
                    ->orWhereNotIn('a.anita_tipo', self::TIPOS_CUENTAS_PAGAR);
            });
        }
    }

    /**
     * @param  array<string, mixed>  $movimiento
     */
    private static function sistema(array $movimiento): string
    {
        return strtoupper(trim((string) ($movimiento['sistema'] ?? '')));
    }

    /**
     * @param  array<string, mixed>  $movimiento
     */
    private static function tipoAsiento(array $movimiento): string
    {
        return strtoupper(trim((string) ($movimiento['tipo_asiento'] ?? '')));
    }

    /**
     * @param  array<string, mixed>  $movimiento
     */
    private static function tipoComp(array $movimiento): string
    {
        return strtoupper(trim((string) ($movimiento['tipo_comp'] ?? '')));
    }

    /**
     * @param  array<string, mixed>  $movimiento
     */
    private static function fk(array $movimiento, string $clave): int
    {
        $fks = $movimiento['erp_asiento_fks'] ?? null;
        if (! is_array($fks)) {
            return 0;
        }

        return (int) ($fks[$clave] ?? 0);
    }

    private static function columnaSql(string $columna): string
    {
        $columna = trim($columna);

        return preg_match('/^[a-z_]+$/', $columna) === 1 ? $columna : '';
    }

    private static function listaTiposSql(): string
    {
        return "'".implode("','", self::TIPOS_CUENTAS_PAGAR)."'";
    }
}
