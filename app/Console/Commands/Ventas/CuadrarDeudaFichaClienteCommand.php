<?php

namespace App\Console\Commands\Ventas;

use App\Services\Ventas\ClienteCuentacorrienteDeudaFichaAjusteService;
use App\Support\Compras\ProveedorCuentacorrienteConciliacionSupport;
use App\Support\Ventas\ClienteCuentacorrienteDeudaFichaCuadreSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CuadrarDeudaFichaClienteCommand extends Command
{
    protected $signature = 'ventas:cuadrar-deuda-ficha-cliente
                            {--fecha=2026-10-18 : Corte Y-m-d. La ficha y la deuda se leen hasta ese día}
                            {--empresa= : Solo esta empresa_id}
                            {--cliente= : Solo este cliente_id}
                            {--tolerancia= : Diferencia mínima}
                            {--expresion=origen : origen (moneda del movimiento) o pesos (histórico + dólares)}
                            {--ejecutar : Graba el ajuste en la ficha. Sin este flag solo informa}';

    protected $description = 'Compara deuda y ficha de clientes (moneda origen o pesos/dólares) y ajusta la ficha';

    public function handle(
        ClienteCuentacorrienteDeudaFichaCuadreSupport $cuadre,
        ClienteCuentacorrienteDeudaFichaAjusteService $ajuste,
    ): int {
        $fecha = trim((string) ($this->option('fecha') ?: ClienteCuentacorrienteDeudaFichaCuadreSupport::FECHA_CORTE_DEFAULT));
        $empresaId = (int) ($this->option('empresa') ?: 0);
        $clienteId = (int) ($this->option('cliente') ?: 0);
        $toleranciaOpcion = trim((string) ($this->option('tolerancia') ?? ''));
        $tolerancia = $toleranciaOpcion === ''
            ? ProveedorCuentacorrienteConciliacionSupport::TOLERANCIA
            : (float) $toleranciaOpcion;
        $expresion = strtolower(trim((string) ($this->option('expresion') ?: ClienteCuentacorrienteDeudaFichaCuadreSupport::EXPRESION_ORIGEN)));
        if (! in_array($expresion, [
            ClienteCuentacorrienteDeudaFichaCuadreSupport::EXPRESION_ORIGEN,
            ClienteCuentacorrienteDeudaFichaCuadreSupport::EXPRESION_PESOS,
        ], true)) {
            $this->error('expresion debe ser origen o pesos');

            return self::FAILURE;
        }
        $ejecutar = (bool) $this->option('ejecutar');

        $this->line(sprintf(
            'Deuda vs ficha de clientes al %s · expresión %s · %s',
            $fecha,
            $expresion,
            $ejecutar ? 'GRABA el ajuste en la ficha' : 'solo informe, no graba'
        ));
        $this->line('No usa Anita ni el bridge. El ajuste es un renglón en la cuenta corriente, con fecha de corte.');
        $this->line('Las aplicaciones se toman completas, igual que el reporte: un cobro posterior al corte aplicado a una factura anterior baja esa deuda y no está en la ficha hasta el corte.');

        try {
            $informe = $cuadre->cuadrar(
                $fecha,
                $empresaId > 0 ? $empresaId : null,
                $clienteId > 0 ? $clienteId : null,
                $tolerancia,
                $expresion,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $resumen = $informe['resumen'];
        if ($expresion === ClienteCuentacorrienteDeudaFichaCuadreSupport::EXPRESION_ORIGEN) {
            $this->table(['Control', 'Valor'], [
                ['Movimientos leídos', (string) $resumen['movimientos']],
                ['Grupos con diferencia', (string) $resumen['grupos']],
                ['Con diferencia en moneda origen', (string) $resumen['con_diferencia_origen']],
                ['Suma diferencia origen (deuda − ficha)', number_format((float) $resumen['suma_diferencia_origen'], 2, ',', '.')],
                ['Ajustes a grabar', (string) $resumen['ajustes_a_grabar']],
                ['Suma de esos ajustes', number_format((float) $resumen['suma_ajuste_propuesto'], 2, ',', '.')],
                ['Ajustes a borrar', (string) $resumen['ajustes_a_borrar']],
            ]);
        } else {
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
                ['Dólar del corte', ($informe['moneda_dolar_abreviatura'] ?? '').' '.number_format((float) ($informe['cotizacion_dolar_corte'] ?? 0), 2, ',', '.').' (vigente '.$informe['cotizacion_dolar_fecha'].')'],
                ['Diferencia en dólares después del ajuste en pesos', number_format((float) $resumen['suma_dolares_despues'], 2, ',', '.')],
            ]);
        }

        $ruta = $this->escribirCsv($informe);
        $this->info('Detalle: '.$ruta);

        if (! $ejecutar) {
            $this->comment('No se grabó nada. Para persistir: php artisan ventas:cuadrar-deuda-ficha-cliente --fecha='.$informe['fecha'].' --expresion='.$expresion.' --ejecutar');

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
        $expresion = (string) ($informe['expresion'] ?? ClienteCuentacorrienteDeudaFichaCuadreSupport::EXPRESION_ORIGEN);
        $nombre = 'cuadre-deuda-ficha-cliente-'.$expresion.'-'.$informe['fecha'].'.csv';
        $candidatos = [
            storage_path('app/ventas'),
            sys_get_temp_dir().'/anitaERP-ventas',
        ];
        $archivo = false;
        $ruta = '';
        foreach ($candidatos as $directorio) {
            try {
                File::ensureDirectoryExists($directorio);
            } catch (\Throwable) {
                continue;
            }
            $ruta = rtrim($directorio, '/').'/'.$nombre;
            $archivo = @fopen($ruta, 'wb');
            if ($archivo !== false) {
                break;
            }
        }
        if ($archivo === false) {
            throw new \RuntimeException('No se pudo escribir el CSV del cuadre (storage/app/ventas ni /tmp).');
        }

        if ($expresion === ClienteCuentacorrienteDeudaFichaCuadreSupport::EXPRESION_ORIGEN) {
            fputcsv($archivo, [
                'cliente_id', 'codigo', 'cliente', 'empresa_id', 'empresa', 'moneda_id', 'moneda',
                'deuda_origen', 'ficha_origen', 'diferencia_origen', 'ajuste_actual', 'ajuste_propuesto', 'accion',
            ], ';');
            foreach ($informe['grupos'] as $grupo) {
                fputcsv($archivo, [
                    $grupo['cliente_id'],
                    $grupo['cliente_codigo'],
                    $grupo['cliente_nombre'],
                    $grupo['empresa_id'],
                    $grupo['empresa_nombre'],
                    $grupo['moneda_id'],
                    $grupo['moneda_abreviatura'],
                    number_format((float) $grupo['deuda_origen'], 2, '.', ''),
                    number_format((float) $grupo['ficha_origen'], 2, '.', ''),
                    number_format((float) $grupo['diferencia_origen'], 2, '.', ''),
                    number_format((float) $grupo['ajuste_actual'], 2, '.', ''),
                    number_format((float) $grupo['ajuste_propuesto'], 2, '.', ''),
                    $grupo['accion'],
                ], ';');
            }
        } else {
            fputcsv($archivo, [
                'cliente_id', 'codigo', 'cliente', 'empresa_id', 'empresa',
                'deuda_pesos', 'ficha_pesos', 'diferencia_pesos',
                'deuda_usd', 'ficha_usd', 'diferencia_usd',
                'ajuste_actual', 'ajuste_propuesto', 'diferencia_usd_despues', 'usd_incompleto', 'accion',
            ], ';');
            foreach ($informe['grupos'] as $grupo) {
                fputcsv($archivo, [
                    $grupo['cliente_id'],
                    $grupo['cliente_codigo'],
                    $grupo['cliente_nombre'],
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
        }

        fclose($archivo);

        return $ruta;
    }
}
