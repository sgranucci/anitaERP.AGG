<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El consumo del contrato pasó a calcularse desde las recepciones confirmadas, con la
 * factura como respaldo. Se ajusta el texto ya sembrado del aviso preventivo.
 *
 * Se usa REPLACE sobre la frase puntual para no pisar plantillas que el usuario haya editado.
 */
return new class extends Migration
{
    private const MODULO = 'compras';

    private const CODIGO = 'ordencompra_contrato_vencimiento';

    private const TEXTO_ANTERIOR = 'Contratos cuyo facturado alcanzó el porcentaje configurado del tope.';

    private const TEXTO_NUEVO = "Contratos cuyo consumo alcanzó el porcentaje configurado del tope.\n"
        .'El consumo sale de las recepciones confirmadas y de las facturas sin recepción vinculada.';

    private const DESC_ANTERIOR = 'consumo del monto contratado.';

    private const DESC_NUEVA = 'consumo del monto contratado (recepciones confirmadas, con la factura como respaldo).';

    public function up(): void
    {
        $this->reemplazar(self::TEXTO_ANTERIOR, self::TEXTO_NUEVO, self::DESC_ANTERIOR, self::DESC_NUEVA);
    }

    public function down(): void
    {
        $this->reemplazar(self::TEXTO_NUEVO, self::TEXTO_ANTERIOR, self::DESC_NUEVA, self::DESC_ANTERIOR);
    }

    private function reemplazar(string $textoDesde, string $textoHasta, string $descDesde, string $descHasta): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        DB::table('modulo_aviso_tipo')
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO)
            ->update([
                'mail_texto' => DB::raw('REPLACE(mail_texto, '.$this->q($textoDesde).', '.$this->q($textoHasta).')'),
                'descripcion' => DB::raw('REPLACE(descripcion, '.$this->q($descDesde).', '.$this->q($descHasta).')'),
                'updated_at' => now(),
            ]);
    }

    private function q(string $valor): string
    {
        return (string) DB::getPdo()->quote($valor);
    }
};
