<?php

use App\Support\Caja\RendicionMaquina\RendicionMaquinaFormulaCatalogo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rendicion_maquina_formula')) {
            Schema::create('rendicion_maquina_formula', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('codigo', 20);
                $table->string('destino', 80);
                $table->text('expresion');
                $table->string('seccion', 30)->default('prep');
                $table->unsignedInteger('orden')->default(0);
                $table->boolean('activo')->default(true);
                $table->boolean('solo_completo')->default(false);
                $table->string('detalle', 255)->nullable();
                $table->unsignedInteger('version_catalogo')->default(1);
                $table->timestamps();

                $table->unique('codigo', 'uq_rendmaq_formula_codigo');
                $table->index(['activo', 'orden'], 'idx_rendmaq_formula_activo_orden');
            });
        }

        $ahora = now();
        foreach (RendicionMaquinaFormulaCatalogo::canonicos() as $paso) {
            $exists = DB::table('rendicion_maquina_formula')
                ->where('codigo', $paso['codigo'])
                ->exists();
            if ($exists) {
                continue;
            }

            DB::table('rendicion_maquina_formula')->insert([
                'codigo' => $paso['codigo'],
                'destino' => $paso['destino'],
                'expresion' => $paso['expresion'],
                'seccion' => $paso['seccion'],
                'orden' => $paso['orden'],
                'activo' => $paso['activo'] ? 1 : 0,
                'solo_completo' => ! empty($paso['solo_completo']) ? 1 : 0,
                'detalle' => $paso['detalle'] ?? null,
                'version_catalogo' => RendicionMaquinaFormulaCatalogo::VERSION,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rendicion_maquina_formula');
    }
};
