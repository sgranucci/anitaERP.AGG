<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: baja BUDGET (id 3, código 3) y LISTADO PARA INVITACIONES (id 4, código 99)
 * junto con las filas que las referencian.
 *
 * En otros clientes el id 3 es otra empresa (en AGG, Rebisco). Solo corre con
 * EMPRESA=CROWN y si el nombre y el código coinciden.
 *
 * Tesorería y gastronomía Crown de estas dos empresas estaban en cero.
 * Lo que sí había: cuentas automáticas, configuración de compras/recepción,
 * jurisdicciones de IIBB (cascada al borrar la empresa) y 13 flash_caja en cero.
 */
return new class extends Migration
{
    /** @var array<int, array{nombre: string, codigo: int}> */
    private const EMPRESAS = [
        3 => ['nombre' => 'BUDGET', 'codigo' => 3],
        4 => ['nombre' => 'LISTADO PARA INVITACIONES', 'codigo' => 99],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('empresa')) {
            return;
        }

        $ids = $this->idsConfirmados();
        if ($ids === []) {
            return;
        }

        DB::transaction(function () use ($ids): void {
            $this->borrarHijosCrown($ids);
            $this->borrarPorEmpresa($ids);
            DB::table('empresa')->whereIn('id', $ids)->delete();
        });
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        // La baja es definitiva: BUDGET e invitaciones no se restauran.
    }

    /**
     * @return array<int, int>
     */
    private function idsConfirmados(): array
    {
        $ids = [];
        foreach (self::EMPRESAS as $id => $esperado) {
            $fila = DB::table('empresa')->where('id', $id)->first();
            if ($fila === null) {
                continue;
            }
            if ((string) $fila->nombre !== $esperado['nombre'] || (int) $fila->codigo !== $esperado['codigo']) {
                continue;
            }
            $ids[] = (int) $id;
        }

        return $ids;
    }

    /**
     * Hijos sin empresa_id que referencian cabeceras de estas empresas.
     * Con cabeceras vacías no borra nada.
     *
     * @param  array<int, int>  $ids
     */
    private function borrarHijosCrown(array $ids): void
    {
        $this->anularAutorreferencia('tesoreria_crown_mesa', 'revierte_id', $ids);
        $this->anularAutorreferencia('tesoreria_crown_comprobante', 'revierte_id', $ids);
        $this->anularAutorreferencia('tesoreria_crown_boveda', 'revierte_id', $ids);
        $this->anularAutorreferencia('tesoreria_crown_boveda', 'par_id', $ids);
        $this->anularAutorreferencia('tesoreria_crown_boveda_arqueo', 'arqueo_anterior_id', $ids);

        $this->borrarHijos('tesoreria_crown_slot_linea', 'slot_id', 'tesoreria_crown_slot', $ids);
        $this->borrarHijos('tesoreria_crown_mesa_linea', 'mesa_id', 'tesoreria_crown_mesa', $ids);
        $this->borrarHijos('tesoreria_crown_comprobante_linea', 'comprobante_id', 'tesoreria_crown_comprobante', $ids);
        $this->borrarHijos('tesoreria_crown_boveda_linea', 'boveda_id', 'tesoreria_crown_boveda', $ids);
        $this->borrarHijos('tesoreria_crown_boveda_arqueo_linea', 'arqueo_id', 'tesoreria_crown_boveda_arqueo', $ids);
        $this->borrarHijos('tesoreria_crown_boveda_stock_dia_linea', 'stock_dia_id', 'tesoreria_crown_boveda_stock_dia', $ids);
        $this->borrarHijos('tesoreria_crown_cierre_evento', 'cierre_id', 'tesoreria_crown_cierre', $ids);
        $this->borrarHijos('tesoreria_crown_asignacion', 'jornada_id', 'tesoreria_crown_jornada', $ids);
        $this->borrarHijos('tesoreria_crown_asignacion', 'deposito_id', 'tesoreria_crown_deposito', $ids);
        $this->borrarHijos('tesoreria_crown_asignacion', 'cajero_crown_id', 'cajero_crown', $ids);
        $this->borrarHijos('cajero_crown_externo', 'cajero_crown_id', 'cajero_crown', $ids);
        $this->borrarHijos('gastro_crown_comanda_linea', 'gastro_crown_comanda_id', 'gastro_crown_comanda', $ids);
        $this->borrarHijos('gastro_crown_comanda_evento', 'gastro_crown_comanda_id', 'gastro_crown_comanda', $ids);
        $this->borrarHijos('gastro_crown_cuenta_linea', 'gastro_crown_cuenta_id', 'gastro_crown_cuenta', $ids);
        $this->borrarHijos('gastro_crown_cuenta_evento', 'gastro_crown_cuenta_id', 'gastro_crown_cuenta', $ids);
        $this->borrarHijos('gastro_crown_comanda', 'gastro_crown_cuenta_id', 'gastro_crown_cuenta', $ids);
        $this->borrarHijos('flash_caja_modificacion', 'flash_caja_id', 'flash_caja', $ids);
    }

    /**
     * Cabeceras con empresa_id. El orden respeta las FK entre tablas Crown.
     * Cuentas automáticas y flash_caja no son tablas Crown, pero impiden borrar la empresa.
     *
     * @param  array<int, int>  $ids
     */
    private function borrarPorEmpresa(array $ids): void
    {
        foreach ([
            'tesoreria_crown_ajuste',
            'tesoreria_crown_mesa',
            'tesoreria_crown_slot',
            'tesoreria_crown_resultado_online',
            'tesoreria_crown_bingo',
            'tesoreria_crown_boveda',
            'tesoreria_crown_boveda_arqueo',
            'tesoreria_crown_boveda_stock_dia',
            'tesoreria_crown_cierre',
            'tesoreria_crown_comprobante',
            'tesoreria_crown_deposito',
            'tesoreria_crown_cotizacion',
            'tesoreria_crown_jornada',
            'cajero_crown',
            'gastro_crown_comanda',
            'gastro_crown_cuenta',
            'gastro_crown_kds_estacion',
            'flash_crown',
            'flash_caja',
            'transferencia_crown_pv_centrocosto',
            'contabilidad_cuenta_automatica_detalle',
            'contabilidad_cuenta_automatica',
        ] as $tabla) {
            if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, 'empresa_id')) {
                continue;
            }
            DB::table($tabla)->whereIn('empresa_id', $ids)->delete();
        }
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function borrarHijos(string $hijo, string $columna, string $padre, array $ids): void
    {
        if (! Schema::hasTable($hijo) || ! Schema::hasTable($padre) || ! Schema::hasColumn($hijo, $columna) || ! Schema::hasColumn($padre, 'empresa_id')) {
            return;
        }

        $padres = DB::table($padre)->whereIn('empresa_id', $ids)->pluck('id');
        if ($padres->isEmpty()) {
            return;
        }

        foreach ($padres->chunk(500) as $lote) {
            DB::table($hijo)->whereIn($columna, $lote->all())->delete();
        }
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function anularAutorreferencia(string $tabla, string $columna, array $ids): void
    {
        if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, $columna) || ! Schema::hasColumn($tabla, 'empresa_id')) {
            return;
        }

        $padres = DB::table($tabla)->whereIn('empresa_id', $ids)->pluck('id');
        if ($padres->isEmpty()) {
            return;
        }

        DB::table($tabla)
            ->whereIn('id', $padres->all())
            ->whereIn($columna, $padres->all())
            ->update([$columna => null]);
    }
};
