<?php

use App\Models\Ticket\Areadestino;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_configuracion_areadestino', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('areadestino_id');
            $table->string('modo_operacion', 20)->default('dispatch');
            $table->timestamps();

            $table->unique('areadestino_id', 'uk_ticket_cfg_area_areadestino');
            $table->foreign('areadestino_id', 'fk_ticket_cfg_area_areadestino')
                ->references('id')
                ->on('areadestino')
                ->cascadeOnDelete();

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });

        $mantenimientoId = (int) (Areadestino::query()
            ->where('nombre', 'Mantenimiento')
            ->value('id') ?? 0);

        if ($mantenimientoId <= 0) {
            $mantenimientoId = (int) (Areadestino::query()
                ->where('nombre', 'like', '%Mantenimiento%')
                ->orderBy('id')
                ->value('id') ?? 0);
        }

        if ($mantenimientoId > 0) {
            DB::table('ticket_configuracion_areadestino')->insert([
                'areadestino_id' => $mantenimientoId,
                'modo_operacion' => 'claim',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_configuracion_areadestino');
    }
};
