<?php

namespace App\Console\Commands\Compras;

use App\Services\Compras\ProveedorCuentacorrienteDeudaFichaAjusteService;
use App\Support\Compras\ProveedorCuentacorrienteConciliacionSupport;
use App\Support\Compras\ProveedorCuentacorrienteDeudaFichaCuadreSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CuadrarDeudaFichaProveedorCommand extends Command
{
    protected $signature = 'compras:cuadrar-deuda-ficha-proveedor
                            {--fecha=2026-09-30 : Corte Y-m-d. La ficha y la deuda se leen hasta ese día}
                            {--empresa= : Solo esta empresa_id}
                            {--proveedor= : Solo este proveedor_id}
                            {--tolerancia= : Diferencia mínima en pesos y en dólares}
                            {--ejecutar : Graba el ajuste en la ficha. Sin este flag solo informa}';

    protected $description = 'Compara deuda y ficha de proveedores (pesos y dólares a cambio histórico) y ajusta la ficha en pesos';

    public function handle(
        ProveedorCuentacorrienteDeudaFichaCuadreSupport $cuadre,
        ProveedorCuentacorrienteDeudaFichaAjusteService $ajuste,
    ): int {
        $fecha = trim((string) ($this->option('fecha') ?: ProveedorCuentacorrienteDeudaFichaCuadreSupport::FECHA_CORTE_DEFAULT));
        $empresaId = (int) ($this->option('empresa') ?: 0);
        $proveedorId = (int) ($this->option('proveedor') ?: 0);
        $toleranciaOpcion = trim((string) ($this->option('tolerancia') ?? ''));
        $tolerancia = $toleranciaOpcion === ''
            ? ProveedorCuentacorrienteConciliacionSupport::TOLERANCIA
            : (float) $toleranciaOpcion;
        $ejecutar = (bool) $this->option('ejecutar');

        $this->line(sprintf(
            'Deuda vs ficha de proveedores al %s · pesos y dólares a cambio histórico · %s',
            $fecha,
            $ejecutar ? 'GRABA el ajuste en la ficha' : 'solo informe, no graba'
        ));
        $this->line('No usa Anita ni el bridge. El ajuste es un renglón en pesos en la cuenta corriente, con fecha de corte.');
        $this->line('Las aplicaciones se toman completas, igual que el reporte: un pago posterior al corte aplicado a una factura anterior baja esa deuda y no está en la ficha hasta el corte.');

        try {
            $informe = $cuadre->cuadrar(
                $fecha,
                $empresaId > 0 ? $empresaId : null,
                $proveedorId > 0 ? $proveedorId : null,
                $tolerancia,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $resumen = $informe['resumen'];
        $this->table(['Control', 'Valor'], [
            ['Movimientos leídos', (string) $resumen['movimientos']],
            ['Grupos con diferencia', (string) $resumen['grupos']],
            ['Con diferencia en pesos', (string) $resumen['con_diferencia_pesos']],
            ['Con diferencia en dólares', (string) $resumen['con_diferencia_dolares']],
            ['Suma diferencia pesos (deuda − ficha)', number_format((float) $resumen['suma_diferencia_pesos'], 2, ',', '.')],
            ['Suma diferencia dólares', number_format((float) $resumen['suma_diferencia_dolares'], 2, ',', '.')],
            ['Ajustes en pesos a grabar', (string) $resumen['ajustes_a_grabar']],
            ['Suma de esos ajustes', number_format((float) $resumen['suma_ajuste_propuesto'], 2, ',', '.')],
            ['Ajustes a borrar', (string) $resumen['ajustes_a_borrar']],
            ['Dólar del corte', $informe['moneda_dolar_abreviatura'].' '.number_format((float) $informe['cotizacion_dolar_corte'], 2, ',', '.').' (vigente '.$informe['cotizacion_dolar_fecha'].')'],
            ['Diferencia en dólares después del ajuste en pesos', number_format((float) $resumen['suma_dolares_despues'], 2, ',', '.')],
            ['Filas que tomaron cotización del día', (string) $resumen['filas_cotizacion_dia']],
            ['Filas sin cotización (quedaron en 1)', (string) $resumen['filas_sin_cotizacion']],
            ['Filas sin dólar de esa fecha', (string) $resumen['filas_sin_dolar']],
        ]);

        $this->line('El ajuste en pesos cierra la ficha contra la deuda en pesos. En dólares puede quedar un resto: cada movimiento usa el dólar de su fecha y el ajuste usa el dólar del corte.');
        $ruta = $this->escribirCsv($informe);
        $this->info('Detalle: '.$ruta);

        if (! $ejecutar) {
            $this->comment('No se grabó nada. Para persistir el mismo corte: php artisan compras:cuadrar-deuda-ficha-proveedor --fecha='.$informe['fecha'].' --ejecutar');

            return self::SUCCESS;
        }

        $aplicado = $ajuste->aplicar($informe);
        $this->info(sprintf(
            'Grabados: %d · Borrados: %d · Sin cambios: %d',
            $aplicado['grabados'],
            $aplicado['borrados'],
            $aplicado['sin_cambios']
        ));
        $this->warn('La corrida por consola no deja fila en audits.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    private function escribirCsv(array $informe): string
    {
        $directorio = storage_path('app/compras');
        File::ensureDirectoryExists($directorio);
        $ruta = $directorio.'/cuadre-deuda-ficha-proveedor-'.$informe['fecha'].'.csv';
        $archivo = fopen($ruta, 'wb');
        if ($archivo === false) {
            throw new \RuntimeException('No se pudo escribir '.$ruta);
        }

        fputcsv($archivo, [
            'proveedor_id',
            'codigo',
            'proveedor',
            'empresa_id',
            'empresa',
            'deuda_pesos',
            'ficha_pesos',
            'diferencia_pesos',
            'deuda_usd',
            'ficha_usd',
            'diferencia_usd',
            'ajuste_actual',
            'ajuste_propuesto',
            'diferencia_usd_despues',
            'usd_incompleto',
            'accion',
        ], ';');

        foreach ($informe['grupos'] as $grupo) {
            fputcsv($archivo, [
                $grupo['proveedor_id'],
                $grupo['proveedor_codigo'],
                $grupo['proveedor_nombre'],
                $grupo['empresa_id'],
                $grupo['empresa_nombre'],
                number_format((float) $grupo['deuda_pesos'], 2, '.', ''),
                number_format((float) $grupo['ficha_pesos'], 2, '.', ''),
                number_format((float) $grupo['diferencia_pesos'], 2, '.', ''),
                number_format((float) $grupo['deuda_usd'], 2, '.', ''),
                number_format((float) $grupo['ficha_usd'], 2, '.', ''),
                number_format((float) $grupo['diferencia_usd'], 2, '.', ''),
                number_format((float) $grupo['ajuste_actual'], 2, '.', ''),
                number_format((float) $grupo['ajuste_propuesto'], 2, '.', ''),
                number_format((float) $grupo['diferencia_usd_despues'], 2, '.', ''),
                $grupo['usd_incompleto'] ? 'si' : 'no',
                $grupo['accion'],
            ], ';');
        }

        fclose($archivo);

        return $ruta;
    }
}
