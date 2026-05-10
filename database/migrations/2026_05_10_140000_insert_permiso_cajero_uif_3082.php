<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Permiso fijo para rol Cajero UIF (no usar AUTO_INCREMENT para este id).
     */
    public function up(): void
    {
        $exists = DB::table('permiso')->where('id', 3082)->exists();
        if ($exists) {
            DB::table('permiso')->where('id', 3082)->update([
                'nombre' => 'Cajero UIF',
                'slug' => 'cajero-uif',
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('permiso')->insert([
            'id' => 3082,
            'nombre' => 'Cajero UIF',
            'slug' => 'cajero-uif',
            'menu_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('permiso')->where('id', 3082)->delete();
    }
};
