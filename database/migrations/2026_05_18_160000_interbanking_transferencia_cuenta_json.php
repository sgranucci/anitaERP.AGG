<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interbanking_transferencia', function (Blueprint $table) {
            $table->json('debit_account_json')->nullable()->after('debit_account');
            $table->json('credit_account_json')->nullable()->after('credit_account');
        });
    }

    public function down(): void
    {
        Schema::table('interbanking_transferencia', function (Blueprint $table) {
            $table->dropColumn(['debit_account_json', 'credit_account_json']);
        });
    }
};
