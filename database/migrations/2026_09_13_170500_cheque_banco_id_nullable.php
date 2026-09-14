<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cheque', function (Blueprint $table) {
            if (Schema::hasColumn('cheque', 'banco_id')) {
                $table->unsignedBigInteger('banco_id')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        // No forzar NOT NULL: quedarían filas CHT sin banco.
    }
};
