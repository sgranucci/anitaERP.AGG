<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: FOL/COL (Del Río online suc. 67 + Betsur suc. 68) en Facturación juego.
 * Cuentas se resuelven por código al sembrar; editables en la pantalla de config.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (Schema::hasTable('flash_crown')) {
            Schema::table('flash_crown', function (Blueprint $table) {
                if (! Schema::hasColumn('flash_crown', 'proc_del_rio')) {
                    $table->char('proc_del_rio', 1)->default(' ')->after('proc_ruleta_el');
                }
                if (! Schema::hasColumn('flash_crown', 'proc_betsur')) {
                    $table->char('proc_betsur', 1)->default(' ')->after('proc_del_rio');
                }
                if (! Schema::hasColumn('flash_crown', 'venta_del_rio_id')) {
                    $table->unsignedBigInteger('venta_del_rio_id')->nullable()->after('venta_ruleta_el_id');
                }
                if (! Schema::hasColumn('flash_crown', 'venta_betsur_id')) {
                    $table->unsignedBigInteger('venta_betsur_id')->nullable()->after('venta_del_rio_id');
                }
                if (! Schema::hasColumn('flash_crown', 'asiento_del_rio_id')) {
                    $table->unsignedBigInteger('asiento_del_rio_id')->nullable()->after('asiento_ruleta_el_id');
                }
                if (! Schema::hasColumn('flash_crown', 'asiento_betsur_id')) {
                    $table->unsignedBigInteger('asiento_betsur_id')->nullable()->after('asiento_del_rio_id');
                }
            });
        }

        if (Schema::hasTable('facturacion_juego_parametro')) {
            Schema::table('facturacion_juego_parametro', function (Blueprint $table) {
                if (! Schema::hasColumn('facturacion_juego_parametro', 'tipo_gan_online')) {
                    $table->string('tipo_gan_online', 3)->default('FOL')->after('tipo_per_ruleta_el');
                }
                if (! Schema::hasColumn('facturacion_juego_parametro', 'tipo_per_online')) {
                    $table->string('tipo_per_online', 3)->default('COL')->after('tipo_gan_online');
                }
                if (! Schema::hasColumn('facturacion_juego_parametro', 'sucursal_del_rio')) {
                    $table->unsignedInteger('sucursal_del_rio')->default(67)->after('tipo_per_online');
                }
                if (! Schema::hasColumn('facturacion_juego_parametro', 'sucursal_betsur')) {
                    $table->unsignedInteger('sucursal_betsur')->default(68)->after('sucursal_del_rio');
                }
            });

            $ahora = now();
            DB::table('facturacion_juego_parametro')
                ->where(function ($q) {
                    $q->whereNull('tipo_gan_online')->orWhere('tipo_gan_online', '');
                })
                ->update(['tipo_gan_online' => 'FOL', 'updated_at' => $ahora]);
            DB::table('facturacion_juego_parametro')
                ->where(function ($q) {
                    $q->whereNull('tipo_per_online')->orWhere('tipo_per_online', '');
                })
                ->update(['tipo_per_online' => 'COL', 'updated_at' => $ahora]);
            DB::table('facturacion_juego_parametro')
                ->where(function ($q) {
                    $q->whereNull('sucursal_del_rio')->orWhere('sucursal_del_rio', 0);
                })
                ->update(['sucursal_del_rio' => 67, 'updated_at' => $ahora]);
            DB::table('facturacion_juego_parametro')
                ->where(function ($q) {
                    $q->whereNull('sucursal_betsur')->orWhere('sucursal_betsur', 0);
                })
                ->update(['sucursal_betsur' => 68, 'updated_at' => $ahora]);
        }

        if (Schema::hasTable('facturacion_juego_sala')) {
            Schema::table('facturacion_juego_sala', function (Blueprint $table) {
                if (! Schema::hasColumn('facturacion_juego_sala', 'cuentacontable_del_rio_id')) {
                    $table->unsignedBigInteger('cuentacontable_del_rio_id')->nullable()->after('cuentacontable_ruleta_el_id');
                }
                if (! Schema::hasColumn('facturacion_juego_sala', 'cuentacontable_betsur_id')) {
                    $table->unsignedBigInteger('cuentacontable_betsur_id')->nullable()->after('cuentacontable_del_rio_id');
                }
            });

            $cuentaDelRioId = $this->cuentaIdPorCodigo('610401000');
            $cuentaBetsurId = $this->cuentaIdPorCodigo('610402000');
            if ($cuentaDelRioId > 0 || $cuentaBetsurId > 0) {
                $rows = DB::table('facturacion_juego_sala')->get(['id', 'cuentacontable_del_rio_id', 'cuentacontable_betsur_id']);
                foreach ($rows as $row) {
                    $upd = [];
                    if ($cuentaDelRioId > 0 && (int) ($row->cuentacontable_del_rio_id ?? 0) <= 0) {
                        $upd['cuentacontable_del_rio_id'] = $cuentaDelRioId;
                    }
                    if ($cuentaBetsurId > 0 && (int) ($row->cuentacontable_betsur_id ?? 0) <= 0) {
                        $upd['cuentacontable_betsur_id'] = $cuentaBetsurId;
                    }
                    if ($upd !== []) {
                        $upd['updated_at'] = now();
                        DB::table('facturacion_juego_sala')->where('id', $row->id)->update($upd);
                    }
                }
            }
        }

        $this->asegurarTipoCol();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (Schema::hasTable('facturacion_juego_sala')) {
            Schema::table('facturacion_juego_sala', function (Blueprint $table) {
                foreach (['cuentacontable_betsur_id', 'cuentacontable_del_rio_id'] as $col) {
                    if (Schema::hasColumn('facturacion_juego_sala', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('facturacion_juego_parametro')) {
            Schema::table('facturacion_juego_parametro', function (Blueprint $table) {
                foreach (['sucursal_betsur', 'sucursal_del_rio', 'tipo_per_online', 'tipo_gan_online'] as $col) {
                    if (Schema::hasColumn('facturacion_juego_parametro', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('flash_crown')) {
            Schema::table('flash_crown', function (Blueprint $table) {
                foreach ([
                    'asiento_betsur_id', 'asiento_del_rio_id',
                    'venta_betsur_id', 'venta_del_rio_id',
                    'proc_betsur', 'proc_del_rio',
                ] as $col) {
                    if (Schema::hasColumn('flash_crown', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }

    private function cuentaIdPorCodigo(string $codigo): int
    {
        if (! Schema::hasTable('cuentacontable')) {
            return 0;
        }

        $q = DB::table('cuentacontable')->where('codigo', $codigo);
        if (Schema::hasColumn('cuentacontable', 'deleted_at')) {
            $q->whereNull('deleted_at');
        }

        return (int) ($q->value('id') ?? 0);
    }

    private function asegurarTipoCol(): void
    {
        if (! Schema::hasTable('tipotransaccion')) {
            return;
        }
        $existeQ = DB::table('tipotransaccion')->where('abreviatura', 'COL');
        if (Schema::hasColumn('tipotransaccion', 'deleted_at')) {
            $existeQ->whereNull('deleted_at');
        }
        if ($existeQ->exists()) {
            return;
        }

        $cslQ = DB::table('tipotransaccion')->where('abreviatura', 'CSL');
        if (Schema::hasColumn('tipotransaccion', 'deleted_at')) {
            $cslQ->whereNull('deleted_at');
        }
        $csl = $cslQ->first();

        $ahora = now();
        DB::table('tipotransaccion')->insert([
            'nombre' => 'Credito ON LINE',
            'operacion' => $csl->operacion ?? 'C',
            'operacionstock' => $csl->operacionstock ?? 'E',
            'abreviatura' => 'COL',
            'codigo' => $csl->codigo ?? '003',
            'signo' => -1,
            'estado' => 'A',
            'iva_ventas' => (int) ($csl->iva_ventas ?? 1),
            'genera_asiento' => (int) ($csl->genera_asiento ?? 1),
            'concepto_venta_id' => $csl->concepto_venta_id ?? null,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }
};
