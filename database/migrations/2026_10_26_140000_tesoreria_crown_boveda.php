<?php

use App\Models\Admin\Menu;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Movimientos de bóveda de Crown, con el catálogo de transacciones de Anita caja.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const PERMISOS = [
        'listar-tesoreria-crown-boveda' => 'Listar movimientos de bóveda de tesorería Crown',
        'crear-tesoreria-crown-boveda' => 'Crear movimientos de bóveda de tesorería Crown',
        'editar-tesoreria-crown-boveda' => 'Confirmar y anular movimientos de bóveda de tesorería Crown',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (! Schema::hasTable('tesoreria_crown_boveda_tipo')) {
            Schema::create('tesoreria_crown_boveda_tipo', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sala_id')->constrained('sala');
                $table->unsignedInteger('codigo');
                $table->string('abreviatura', 4);
                $table->string('nombre', 40);
                $table->char('signo', 1);
                $table->char('operacion', 1);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->unique(['sala_id', 'codigo'], 'tc_boveda_tipo_codigo_uq');
            });
        }

        if (! Schema::hasTable('tesoreria_crown_boveda')) {
            Schema::create('tesoreria_crown_boveda', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sala_id')->constrained('sala');
                $table->foreignId('empresa_id')->constrained('empresa');
                $table->foreignId('jornada_id')->constrained('tesoreria_crown_jornada');
                $table->foreignId('deposito_id')->constrained('tesoreria_crown_deposito');
                $table->unsignedBigInteger('tipo_id');
                $table->unsignedInteger('numero');
                $table->char('estado', 1);
                $table->string('comentario', 80)->nullable();
                $table->unsignedBigInteger('comprobante_id')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamp('confirmado_at')->nullable();
                $table->timestamps();
                $table->unique(['sala_id', 'numero'], 'tc_boveda_numero_uq');
                $table->index(['jornada_id', 'deposito_id'], 'tc_boveda_jornada_dep_idx');
                $table->foreign('tipo_id', 'tc_boveda_tipo_fk')
                    ->references('id')->on('tesoreria_crown_boveda_tipo');
                $table->foreign('comprobante_id', 'tc_boveda_comp_fk')
                    ->references('id')->on('tesoreria_crown_comprobante');
            });
        }

        if (! Schema::hasTable('tesoreria_crown_boveda_linea')) {
            Schema::create('tesoreria_crown_boveda_linea', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('boveda_id');
                $table->foreignId('valor_id')->constrained('tesoreria_crown_valor');
                $table->decimal('cantidad', 14, 2);
                $table->timestamps();
                $table->unique(['boveda_id', 'valor_id'], 'tc_boveda_linea_valor_uq');
                $table->foreign('boveda_id', 'tc_boveda_linea_fk')
                    ->references('id')->on('tesoreria_crown_boveda');
            });
        }

        $this->sembrarTipos();
        $this->crearMenu();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        Schema::dropIfExists('tesoreria_crown_boveda_linea');
        Schema::dropIfExists('tesoreria_crown_boveda');
        Schema::dropIfExists('tesoreria_crown_boveda_tipo');

        $menu = Menu::query()->where('url', 'caja/tesoreria-crown/boveda')->first();
        if ($menu) {
            DB::table('menu_rol')->where('menu_id', $menu->id)->delete();
            $menu->delete();
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', array_keys(self::PERMISOS))->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function sembrarTipos(): void
    {
        $salas = DB::table('sala')->whereRaw('UPPER(codigo) <> ?', ['AC'])->pluck('id');
        $ahora = now();
        foreach ($salas as $salaId) {
            foreach ($this->catalogoAnita() as $fila) {
                $existe = DB::table('tesoreria_crown_boveda_tipo')
                    ->where('sala_id', $salaId)
                    ->where('codigo', $fila['codigo'])
                    ->exists();
                if ($existe) {
                    continue;
                }
                DB::table('tesoreria_crown_boveda_tipo')->insert($fila + [
                    'sala_id' => $salaId,
                    'activo' => ! str_starts_with($fila['nombre'], 'NO USAR'),
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }
    }

    private function crearMenu(): void
    {
        $modulo = Menu::query()->where('url', '#tesoreria-crown')->first();
        if (! $modulo) {
            return;
        }

        $ahora = now();
        $permisoIds = [];
        foreach (self::PERMISOS as $slug => $nombre) {
            $id = DB::table('permiso')->where('slug', $slug)->value('id');
            if (! $id) {
                $id = DB::table('permiso')->insertGetId([
                    'nombre' => $nombre,
                    'slug' => $slug,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
            $permisoIds[] = (int) $id;
        }

        $menu = Menu::query()->where('url', 'caja/tesoreria-crown/boveda')->first();
        if (! $menu) {
            $menu = new Menu;
            $menu->nombre = 'Bóveda';
            $menu->url = 'caja/tesoreria-crown/boveda';
            $menu->icono = 'fa-university';
            $menu->menu_id = $modulo->id;
            $menu->orden = 4;
            $menu->save();
        }

        $roles = DB::table('menu_rol')->where('menu_id', $modulo->id)->pluck('rol_id');
        foreach ($roles as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menu->id)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menu->id, 'rol_id' => $rolId]);
            }
            foreach ($permisoIds as $permisoId) {
                if (! DB::table('permiso_rol')->where('rol_id', $rolId)->where('permiso_id', $permisoId)->exists()) {
                    DB::table('permiso_rol')->insert(['rol_id' => $rolId, 'permiso_id' => $permisoId]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /**
     * Catálogo activo de transmae en la caja de Cipolletti (cncadmin, sistema caja).
     * Signo Anita: 0 entra, 1 sale, 2 no mueve el saldo.
     *
     * @return list<array{codigo: int, abreviatura: string, nombre: string, signo: string, operacion: string}>
     */
    private function catalogoAnita(): array
    {
        return [
            ['codigo' => 1, 'abreviatura' => 'INGR', 'nombre' => 'Otros ingresos', 'signo' => '0', 'operacion' => '0'],
            ['codigo' => 2, 'abreviatura' => 'OP', 'nombre' => 'Orden de pago', 'signo' => '1', 'operacion' => '5'],
            ['codigo' => 3, 'abreviatura' => 'DEP$', 'nombre' => 'NO USAR DEPOSITO PESOS', 'signo' => '1', 'operacion' => '0'],
            ['codigo' => 4, 'abreviatura' => 'ADEL', 'nombre' => 'Adelanto sueldo', 'signo' => '1', 'operacion' => '4'],
            ['codigo' => 6, 'abreviatura' => 'ICCH', 'nombre' => 'Ingreso caja chica adm.', 'signo' => '1', 'operacion' => '0'],
            ['codigo' => 7, 'abreviatura' => 'DEPR', 'nombre' => 'NO USAR DEPOSITO PROPINA', 'signo' => '1', 'operacion' => '0'],
            ['codigo' => 8, 'abreviatura' => 'DEUS', 'nombre' => 'Deposito dolares', 'signo' => '1', 'operacion' => '2'],
            ['codigo' => 9, 'abreviatura' => 'DEEU', 'nombre' => 'Deposito euros', 'signo' => '1', 'operacion' => '2'],
            ['codigo' => 11, 'abreviatura' => 'INUS', 'nombre' => 'Ingresos dolares', 'signo' => '0', 'operacion' => '2'],
            ['codigo' => 12, 'abreviatura' => 'INEU', 'nombre' => 'Ingresos euros', 'signo' => '0', 'operacion' => '2'],
            ['codigo' => 13, 'abreviatura' => 'EXCE', 'nombre' => 'Excedente de caja', 'signo' => '0', 'operacion' => '4'],
            ['codigo' => 14, 'abreviatura' => 'OEGR', 'nombre' => 'Otros egresos', 'signo' => '1', 'operacion' => '0'],
            ['codigo' => 15, 'abreviatura' => 'FCAJ', 'nombre' => 'Fallo de caja', 'signo' => '1', 'operacion' => '4'],
            ['codigo' => 16, 'abreviatura' => 'FBOV', 'nombre' => 'Faltante de boveda', 'signo' => '1', 'operacion' => '4'],
            ['codigo' => 17, 'abreviatura' => 'SBOV', 'nombre' => 'Sobrante de boveda', 'signo' => '0', 'operacion' => '4'],
            ['codigo' => 18, 'abreviatura' => 'BIN', 'nombre' => 'Ingresos por Bingo', 'signo' => '0', 'operacion' => '0'],
            ['codigo' => 19, 'abreviatura' => 'PFF', 'nombre' => 'Pagos por fondo fijo', 'signo' => '1', 'operacion' => '5'],
            ['codigo' => 20, 'abreviatura' => 'RANT', 'nombre' => 'Retiro anticipado', 'signo' => '1', 'operacion' => '0'],
            ['codigo' => 21, 'abreviatura' => 'PDEP', 'nombre' => 'Pre Deposito', 'signo' => '1', 'operacion' => '0'],
            ['codigo' => 22, 'abreviatura' => 'RDEP', 'nombre' => 'Retiro de Deposito', 'signo' => '2', 'operacion' => '0'],
            ['codigo' => 23, 'abreviatura' => 'PDPR', 'nombre' => 'Pre Deposito Propina', 'signo' => '1', 'operacion' => '1'],
            ['codigo' => 24, 'abreviatura' => 'PRPR', 'nombre' => 'Retiro propina', 'signo' => '2', 'operacion' => '1'],
            ['codigo' => 25, 'abreviatura' => 'IFON', 'nombre' => 'Ingreso de Fondo', 'signo' => '2', 'operacion' => '0'],
            ['codigo' => 26, 'abreviatura' => 'OBIN', 'nombre' => 'Otros egresos bingo', 'signo' => '1', 'operacion' => '0'],
            ['codigo' => 27, 'abreviatura' => 'ICAM', 'nombre' => 'Ingreso por cambio', 'signo' => '0', 'operacion' => '3'],
            ['codigo' => 28, 'abreviatura' => 'ECAM', 'nombre' => 'EGRESOS POR CAMBIO', 'signo' => '1', 'operacion' => '0'],
            ['codigo' => 29, 'abreviatura' => 'IENC', 'nombre' => 'Ingreso de valores encontrados', 'signo' => '0', 'operacion' => '0'],
            ['codigo' => 30, 'abreviatura' => 'RFON', 'nombre' => 'Retiro de fondo', 'signo' => '2', 'operacion' => '0'],
            ['codigo' => 31, 'abreviatura' => 'CHOT', 'nombre' => 'Ingreso caja chica hotel', 'signo' => '1', 'operacion' => '0'],
            ['codigo' => 32, 'abreviatura' => 'RHOT', 'nombre' => 'Egreso caja chica hotel', 'signo' => '0', 'operacion' => '0'],
            ['codigo' => 33, 'abreviatura' => 'IDVE', 'nombre' => 'Ingreso diferencia a verificar', 'signo' => '2', 'operacion' => '0'],
            ['codigo' => 34, 'abreviatura' => 'EDVE', 'nombre' => 'Egreso diferencia a verificar', 'signo' => '2', 'operacion' => '0'],
            ['codigo' => 35, 'abreviatura' => 'IDTE', 'nombre' => 'Ingreso diferencia tesoreria', 'signo' => '2', 'operacion' => '0'],
            ['codigo' => 36, 'abreviatura' => 'EDTE', 'nombre' => 'Egreso diferencia tesoreria', 'signo' => '2', 'operacion' => '0'],
            ['codigo' => 37, 'abreviatura' => 'ITOR', 'nombre' => 'Ingreso de Torneo', 'signo' => '0', 'operacion' => '0'],
            ['codigo' => 38, 'abreviatura' => 'ETOR', 'nombre' => 'Egreso de torneo', 'signo' => '1', 'operacion' => '0'],
            ['codigo' => 39, 'abreviatura' => 'PCRE', 'nombre' => 'PAGO DE CREDITOS', 'signo' => '1', 'operacion' => '7'],
            ['codigo' => 40, 'abreviatura' => 'RADM', 'nombre' => 'Egreso caja chica adm.', 'signo' => '0', 'operacion' => '0'],
            ['codigo' => 41, 'abreviatura' => 'DTES', 'nombre' => 'Dolares ingresados a Tesoreria', 'signo' => '0', 'operacion' => '2'],
            ['codigo' => 98, 'abreviatura' => 'CDF-', 'nombre' => 'Cancela Dif.Final Boveda -', 'signo' => '2', 'operacion' => '0'],
            ['codigo' => 99, 'abreviatura' => 'CDB+', 'nombre' => 'Cancela Dif.Final de Boveda +', 'signo' => '2', 'operacion' => '0'],
        ];
    }
};
