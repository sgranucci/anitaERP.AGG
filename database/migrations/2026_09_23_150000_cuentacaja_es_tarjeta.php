<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flag operativo: la cuenta es tarjeta / pide Nº de cupón o transacción en POS Local.
 * No confundir con tipocuenta (Valores / Retenciones).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cuentacaja')) {
            return;
        }

        if (! Schema::hasColumn('cuentacaja', 'es_tarjeta')) {
            Schema::table('cuentacaja', function (Blueprint $table) {
                $table->boolean('es_tarjeta')->default(false)->after('tipocuenta');
            });
        }

        $this->backfillEsTarjeta();
    }

    public function down(): void
    {
        if (Schema::hasTable('cuentacaja') && Schema::hasColumn('cuentacaja', 'es_tarjeta')) {
            Schema::table('cuentacaja', function (Blueprint $table) {
                $table->dropColumn('es_tarjeta');
            });
        }
    }

    private function backfillEsTarjeta(): void
    {
        $rows = DB::table('cuentacaja')->get(['id', 'codigo', 'nombre']);
        foreach ($rows as $row) {
            $esTarjeta = $this->heuristicaEsTarjeta((string) $row->nombre, (string) $row->codigo);
            DB::table('cuentacaja')->where('id', $row->id)->update(['es_tarjeta' => $esTarjeta]);
        }
    }

    /**
     * Semilla única al migrar. En runtime manda el flag del ABM.
     */
    private function heuristicaEsTarjeta(string $nombre, string $codigo): bool
    {
        $texto = $this->normalizar($nombre.' '.$codigo);
        if ($texto === '') {
            return false;
        }

        foreach ([
            'CANJE', 'CTG', 'EFECTIVO', 'TRANSFER', 'MERCADO PAGO', 'MERCADOPAGO',
            'CHEQUE', 'DOLAR', 'EURO', 'FONDO FIJO',
        ] as $excluido) {
            if ($this->contienePalabra($texto, $excluido)) {
                return false;
            }
        }
        // Prefijo: COMISIONES / COMISION …
        if (str_contains($texto, 'COMISION')) {
            return false;
        }

        foreach ([
            'VISA', 'MASTERCARD', 'MASTER CARD', 'MAESTRO', 'CABAL', 'AMEX', 'AMERICAN EXPRESS',
            'NARANJA', 'FISERV', 'POSNET', 'GETNET', 'PAYWAY', 'FIRST DATA',
            'TARJETA', 'MEP', 'GO CUOTAS', 'GOCUOTAS',
        ] as $marca) {
            if ($this->contienePalabra($texto, $marca)) {
                return true;
            }
        }

        // MASTER suelto (p. ej. MASTER * FIRST DATA ya cubierto por FIRST DATA / MASTERCARD).
        if ($this->contienePalabra($texto, 'MASTER') && ! $this->contienePalabra($texto, 'MAESTRO')) {
            return true;
        }

        return false;
    }

    private function normalizar(string $texto): string
    {
        $texto = mb_strtoupper(trim($texto));
        $texto = str_replace(
            ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ'],
            ['A', 'E', 'I', 'O', 'U', 'U', 'N'],
            $texto
        );

        return preg_replace('/\s+/', ' ', $texto) ?? $texto;
    }

    private function contienePalabra(string $texto, string $frase): bool
    {
        $frase = $this->normalizar($frase);
        if ($frase === '') {
            return false;
        }
        $pattern = '/(?<![A-Z0-9])'.preg_quote($frase, '/').'(?![A-Z0-9])/u';

        return (bool) preg_match($pattern, $texto);
    }
};
