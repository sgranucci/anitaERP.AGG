<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajuste de auditoría sobre un cierre ya firmado.
 * El permiso no se asigna a ningún rol: lo tilda Sistemas en Permiso - Rol.
 */
return new class extends Migration
{
    private const PERMISO = 'ajustar-tesoreria-crown-caja';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (! Schema::hasTable('tesoreria_crown_ajuste')) {
            Schema::create('tesoreria_crown_ajuste', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sala_id')->constrained('sala');
                $table->foreignId('empresa_id')->constrained('empresa');
                $table->foreignId('jornada_id')->constrained('tesoreria_crown_jornada');
                $table->foreignId('asignacion_id')->constrained('tesoreria_crown_asignacion');
                $table->unsignedBigInteger('cierre_id');
                $table->unsignedInteger('numero');
                $table->char('corrige', 1);
                $table->decimal('importe', 14, 2);
                $table->string('motivo', 500);
                $table->foreignId('auditor_usuario_id')->constrained('usuario');
                $table->dateTime('auditor_at');
                $table->foreignId('tesoreria_usuario_id')->constrained('usuario');
                $table->dateTime('tesoreria_at');
                $table->timestamps();
                $table->unique(['sala_id', 'numero'], 'tc_ajuste_numero_uq');
                $table->foreign('cierre_id', 'tc_ajuste_cierre_fk')->references('id')->on('tesoreria_crown_cierre');
            });
        }

        $ahora = now();
        $id = DB::table('permiso')->where('slug', self::PERMISO)->value('id');
        if (! $id) {
            $id = DB::table('permiso')->insertGetId([
                'nombre' => 'Ajustar cierre de tesorería Crown',
                'slug' => self::PERMISO,
                'ayuda' => 'Permiso especial, aparte de tesorería. No viene asignado a ningún rol: se tilda solo en el rol de quien audita una caja ya cerrada. En el cierre, Ajuste de caja graba un comprobante nuevo, con motivo e importe, firmado por dos usuarios distintos. No reescribe la habilitación, la rendición ni el cierre original, y no reabre la caja ni la jornada. Si la diferencia vigente supera la tolerancia, el caso vuelve a auditoría. El rol administrador entra igual, porque ese rol no consulta los permisos.',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
        $menuId = DB::table('menu')->where('url', 'caja/tesoreria-crown/jornada')->value('id');
        if ($menuId) {
            DB::table('permiso')->where('id', $id)->update([
                'menu_id' => $menuId,
                'updated_at' => $ahora,
            ]);
        }
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        Schema::dropIfExists('tesoreria_crown_ajuste');
        $permisoIds = DB::table('permiso')->where('slug', self::PERMISO)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }
        SuitecrmPermiso::flushCachePermisos();
    }
};
