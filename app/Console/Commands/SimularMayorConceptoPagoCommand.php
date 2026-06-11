<?php

namespace App\Console\Commands;

use App\Support\Contable\MayorConcepto\MayorConceptoMemoriaMotor;
use Illuminate\Console\Command;

class SimularMayorConceptoPagoCommand extends Command
{
    protected $signature = 'contable:simular-mayor-concepto-pago
                            {--empresa=1 : ID empresa Anita}
                            {--tipo=OPP : Tipo comprobante pago}
                            {--letra=A : Letra comprobante}
                            {--sucursal=1 : Sucursal}
                            {--nro=122118 : Número OP (ej. pago INC S.A. 09/04/2026)}
                            {--fecha=20260409 : Fecha AAAAMMDD}';

    protected $description = 'Simula imputación mayor por concepto para un pago OPP vía bridge Anita';

    public function handle(MayorConceptoMemoriaMotor $motor): int
    {
        $empresa = (int) $this->option('empresa');
        $tipo = strtoupper((string) $this->option('tipo'));
        $letra = (string) $this->option('letra');
        $sucursal = (int) $this->option('sucursal');
        $nro = (int) $this->option('nro');
        $fecha = (int) $this->option('fecha');

        $this->info(sprintf(
            'Simulación mayor por concepto — %s %s-%04d-%d (empresa %d, fecha %d)',
            $tipo,
            $letra !== '' ? $letra : ' ',
            $sucursal,
            $nro,
            $empresa,
            $fecha
        ));

        $resultado = $motor->simularPago($empresa, $tipo, $letra, $sucursal, $nro, $fecha);

        foreach ($resultado['errores_bridge'] as $error) {
            $this->error('Bridge: '.$error);
        }

        $this->newLine();
        $this->info('Subdiario OP ('.count($resultado['lineas_subdiario']).' líneas):');
        $filasSub = [];
        foreach ($resultado['lineas_subdiario'] as $linea) {
            $cuenta = (int) $linea->subd_cuenta;
            $filasSub[] = [
                $motor->esDisponibilidad($cuenta) ? 'DISP' : 'OTRO',
                $motor->formatearCodigoCuenta($cuenta),
                $motor->formatearCodigoCuenta((int) $linea->subd_contrapartida),
                $linea->subd_tipo_mov,
                number_format((float) $linea->subd_importe, 2, '.', ''),
                $linea->subd_desc_mov ?? '',
            ];
        }
        $this->table(['Tipo', 'Cuenta', 'Contra', 'D/H', 'Importe', 'Descripción'], $filasSub);

        $this->newLine();
        $this->info('Auxpag ('.count($resultado['auxpag']).' aplicaciones):');
        $filasAxp = [];
        foreach ($resultado['auxpag'] as $axp) {
            $filasAxp[] = [
                $axp->axp_tipo_ap ?? '',
                $axp->axp_pro ?? '',
                $axp->axp_sucursal ?? '',
                $axp->axp_nro ?? '',
                number_format((float) ($axp->axp_monto_ap ?? 0), 2, '.', ''),
                $axp->axp_banco ?? '',
            ];
        }
        $this->table(['Tipo_ap', 'Prov', 'Suc', 'Nro', 'Monto', 'Banco'], $filasAxp);

        $this->newLine();
        $this->info('Imputaciones por concepto:');
        $filasImp = [];
        foreach ($resultado['imputaciones'] as $imp) {
            $filasImp[] = [
                $imp['concepto_id'],
                $imp['concepto_nombre'],
                $imp['cuenta_codigo'],
                $imp['cuenta_nombre'],
                number_format($imp['monto'], 2, '.', ''),
                $imp['d_h'],
                $imp['origen'],
            ];
        }
        $this->table(['Conc', 'Concepto', 'Cuenta', 'Nombre cuenta', 'Monto', 'D/H', 'Origen'], $filasImp);

        $tot = $resultado['totales'];
        $this->newLine();
        $this->line('Total haber OP: '.number_format($tot['haber_op'], 2, '.', ''));
        $this->line('Total imputado:  '.number_format($tot['imputado'], 2, '.', ''));
        $this->line('Diferencia:      '.number_format($tot['diferencia'], 2, '.', ''));
        $this->line($tot['cuadra'] ? '<fg=green>Cuadra con mayor analítico</>' : '<fg=red>No cuadra</>');

        $ref = $resultado['referencia_anita'];
        $this->newLine();
        $this->comment('Referencia Anita (l_mayorconc abril/26):');
        $this->line(sprintf(
            '  Concepto %d — cuenta %s — Debe %.2f (%s)',
            $ref['concepto'],
            $motor->formatearCodigoCuenta($ref['cuenta']),
            $ref['debe_esperado'],
            $ref['comprobante']
        ));

        foreach ($resultado['trazas'] as $traza) {
            $this->line('  '.$traza);
        }

        return $tot['cuadra'] ? self::SUCCESS : self::FAILURE;
    }
}
