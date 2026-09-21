<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parámetros Facturación Local (Reportes: costo fábrica).
 * Sin SoftDeletes. Valores iniciales desde config/env.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('facturacion_local_parametro')) {
            Schema::create('facturacion_local_parametro', function (Blueprint $table) {
                $table->id();
                $table->string('clave', 80)->unique();
                $table->string('valor', 500)->default('');
                $table->string('etiqueta', 120)->nullable();
                $table->string('ayuda', 255)->nullable();
                $table->unsignedInteger('orden')->default(0);
                $table->timestamps();
            });
        }

        $defaults = [
            [
                'clave' => 'costo_descuento_pct',
                'valor' => (string) config('facturacion_local.costo_descuento_pct', 67),
                'etiqueta' => 'Descuento % sobre precio fábrica',
                'ayuda' => 'Costo = precio fábrica × (1 − este % / 100). Ej. 67 → queda el 33 %.',
                'orden' => 10,
            ],
            [
                'clave' => 'costo_listas_fabrica',
                'valor' => implode(',', config('facturacion_local.costo_listas_fabrica_codigos', ['1', '2', '3', '4', '5'])),
                'etiqueta' => 'Códigos de listas fábrica',
                'ayuda' => 'Códigos de listaprecio separados por coma (tiponumeración). Default Ferli: 1,2,3,4,5.',
                'orden' => 20,
            ],
            [
                'clave' => 'costo_listaprecio_codigo',
                'valor' => (string) config('facturacion_local.costo_listaprecio_codigo', ''),
                'etiqueta' => 'Lista fábrica forzada (opcional)',
                'ayuda' => 'Si tiene valor, usa solo ese código de lista e ignora las de arriba. Vacío = listas fábrica + talle.',
                'orden' => 30,
            ],
        ];

        $now = now();
        foreach ($defaults as $fila) {
            $existe = DB::table('facturacion_local_parametro')->where('clave', $fila['clave'])->exists();
            if ($existe) {
                continue;
            }
            DB::table('facturacion_local_parametro')->insert(array_merge($fila, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('facturacion_local_parametro');
    }
};
