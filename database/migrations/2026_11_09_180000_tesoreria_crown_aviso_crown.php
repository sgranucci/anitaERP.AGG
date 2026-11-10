<?php

use App\Models\Admin\Menu;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bandeja de avisos de auditoría de tesorería Crown (fase 1).
 * Cada aviso apunta a una jornada, caja, comprobante o cierre y se revisa o descarta con comentario.
 * Revisar y descartar piden el permiso auditar-tesoreria-crown-caja; listar, el de esta migración.
 */
return new class extends Migration
{
    private const TABLA = 'tesoreria_crown_aviso';

    private const URL = 'caja/tesoreria-crown/aviso';

    private const URL_PADRE = '#tesoreria-crown';

    private const PERMISO = 'listar-tesoreria-crown-aviso';

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-sistemas',
        'Enc-tesorería',
        'Ger-Tesoreria',
        'Sup-tesoreria',
        'Enc-contaduría',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (! Schema::hasTable(self::TABLA)) {
            Schema::create(self::TABLA, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sala_id')->nullable();
                $table->date('fecha')->nullable();
                $table->unsignedBigInteger('jornada_id')->nullable();
                $table->unsignedBigInteger('asignacion_id')->nullable();
                $table->unsignedBigInteger('comprobante_id')->nullable();
                $table->unsignedBigInteger('cierre_id')->nullable();
                $table->unsignedBigInteger('cajero_crown_id')->nullable();
                $table->string('regla', 40);
                $table->char('severidad', 1);
                $table->string('clave', 191)->unique('tc_aviso_clave_uq');
                $table->string('titulo', 255);
                $table->text('detalle')->nullable();
                $table->decimal('importe', 18, 2)->nullable();
                $table->char('estado', 1)->default('A');
                $table->text('comentario')->nullable();
                $table->unsignedBigInteger('resuelto_usuario_id')->nullable();
                $table->dateTime('resuelto_at')->nullable();
                $table->timestamps();
                $table->index(['estado', 'fecha'], 'tc_aviso_estado_fecha_idx');
                $table->index('jornada_id', 'tc_aviso_jornada_idx');
                $table->index(['regla', 'estado'], 'tc_aviso_regla_estado_idx');
            });
        }

        $padre = Menu::query()->where('url', self::URL_PADRE)->first();
        if (! $padre) {
            return;
        }
        $menu = Menu::query()->where('url', self::URL)->first() ?? new Menu;
        $menu->nombre = 'Avisos de auditoría';
        $menu->url = self::URL;
        $menu->icono = 'fa-bell';
        $menu->menu_id = $padre->id;
        // Debajo de Flash y del resto: no reutilizar un orden ya ocupado (empate inestable).
        $menu->orden = (int) (DB::table('menu')->where('menu_id', $padre->id)->max('orden') ?? 0) + 1;
        $menu->save();

        $ahora = now();
        $permisoId = DB::table('permiso')->where('slug', self::PERMISO)->value('id');
        if (! $permisoId) {
            $fila = [
                'nombre' => 'Listar avisos de auditoría de tesorería Crown',
                'slug' => self::PERMISO,
                'menu_id' => $menu->id,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
            if (Schema::hasColumn('permiso', 'ayuda')) {
                $fila['ayuda'] = 'Ver la bandeja de avisos de auditoría (diferencias, no conformes, reversas, reaperturas, firmas, diferencias contra Anita) y exportarla. Revisar o descartar un aviso pide además el permiso de auditar cajas.';
            }
            $permisoId = DB::table('permiso')->insertGetId($fila);
        }

        foreach (DB::table('rol')->whereIn('nombre', self::ROLES)->pluck('id') as $rolId) {
            foreach ([$menu->id, $padre->id] as $menuId) {
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
                }
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        $menuId = Menu::query()->where('url', self::URL)->value('id');
        if ($menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }
        $permisoId = DB::table('permiso')->where('slug', self::PERMISO)->value('id');
        if ($permisoId) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }
        Schema::dropIfExists(self::TABLA);
        SuitecrmPermiso::flushCachePermisos();
    }
};
