<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Integrantes de un condominio (RG 830 art. 8 y 25): un proveedor, varias personas.
 * La carga del 003655 y el régimen Alquileres van en la misma migración porque
 * el operador lo pidió para ese padrón.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('proveedor_integrante')) {
            Schema::create('proveedor_integrante', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('proveedor_id');
                $table->unsignedSmallInteger('orden')->default(1);
                $table->string('nombre', 60);
                $table->string('cuit', 15);
                $table->decimal('porcentaje', 7, 4);
                $table->char('inscripto', 1)->default('S');
                $table->timestamps();

                $table->index('proveedor_id', 'proveedor_integrante_proveedor_idx');
                $table->foreign('proveedor_id', 'fk_proveedor_integrante_proveedor')
                    ->references('id')->on('proveedor');
            });
        }

        $this->cargarCondominioMugica();
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedor_integrante');
    }

    private function cargarCondominioMugica(): void
    {
        $proveedorId = DB::table('proveedor')
            ->where(function ($q) {
                $q->where('codigo', '3655')
                    ->orWhere('codigo', '003655')
                    ->orWhere('nroinscripcion', '30-71327470-0');
            })
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->value('id');

        if (! $proveedorId) {
            return;
        }

        $alquileresId = DB::table('retencionganancia')->where('codigo', '3')->value('id');
        if ($alquileresId) {
            DB::table('proveedor')->where('id', $proveedorId)->update([
                'retencionganancia_id' => $alquileresId,
                'updated_at' => now(),
            ]);
        }

        $ya = DB::table('proveedor_integrante')->where('proveedor_id', $proveedorId)->count();
        if ($ya > 0) {
            return;
        }

        $ahora = now();
        DB::table('proveedor_integrante')->insert([
            [
                'proveedor_id' => $proveedorId,
                'orden' => 1,
                'nombre' => 'MUGICA FIDEL',
                'cuit' => '20-07612050-2',
                'porcentaje' => 50,
                'inscripto' => 'S',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            [
                'proveedor_id' => $proveedorId,
                'orden' => 2,
                'nombre' => 'ROHR JOSE',
                'cuit' => '20-12961082-5',
                'porcentaje' => 50,
                'inscripto' => 'S',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
        ]);
    }
};
