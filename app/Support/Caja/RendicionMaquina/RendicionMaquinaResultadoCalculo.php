<?php

namespace App\Support\Caja\RendicionMaquina;

/**
 * Resultado inmutable de un cálculo de rendición.
 *
 * @phpstan-type PasoRastro array{codigo: string, destino: string, expresion: string, valor: float, seccion: string}
 */
final class RendicionMaquinaResultadoCalculo
{
    /**
     * @param  array<string, float|int|string|bool|null>  $variables
     * @param  list<PasoRastro>  $rastro
     */
    public function __construct(
        public readonly array $variables,
        public readonly array $rastro,
        public readonly string $turno,
        public readonly string $modoWigos,
    ) {}

    public function get(string $ruta, float $default = 0.0): float
    {
        $v = $this->variables[$ruta] ?? null;
        if ($v === null || $v === '') {
            return $default;
        }

        return (float) $v;
    }

    /**
     * Totales de cierre para UI / sticky footer.
     *
     * @return array<string, float>
     */
    public function totalesCierre(): array
    {
        return [
            'fondo_inicial' => $this->get('inputs.fondo_inicial'),
            'comprobante' => $this->get('calc.comprobante'),
            'fondo_fijo' => $this->get('calc.fondo_fijo'),
            'total_ingreso' => $this->get('calc.total_ingreso'),
            'total_salida' => $this->get('calc.total_salida'),
            'resultado_turno' => $this->get('calc.resultado_turno'),
            'fondo_cierre' => $this->get('calc.fondo_cierre'),
            'transferencia' => $this->get('calc.transferencia'),
            'saldo_ingreso' => $this->get('calc.saldo_ingreso'),
            'dif_caja' => $this->get('calc.dif_caja'),
            'deposito_efectivo' => $this->get('calc.deposito_efectivo'),
            'deposito_pesos' => $this->get('calc.deposito_pesos'),
            'gastos_total' => $this->get('gastos.total'),
            'valores_total' => $this->get('valores.total'),
        ];
    }

    /**
     * @return array<string, float>
     */
    public function totalesIngresos(): array
    {
        return [
            'drop_bill_rodillo' => $this->get('calc.drop_bill_rodillo'),
            'drop_bill_ruleta' => $this->get('calc.drop_bill_ruleta'),
            'dropem_rodillo' => $this->get('inputs.dropem_rodillo'),
            'dropem_ruleta' => $this->get('inputs.dropem_ruleta'),
            'dropqr_rodillo' => $this->get('inputs.dropqr_rodillo'),
            'dropqr_ruleta' => $this->get('inputs.dropqr_ruleta'),
            'entrada_ruleta' => $this->get('calc.entrada_ruleta'),
            'venta_ficha' => $this->get('calc.venta_ficha'),
            'sobrante_supervisor' => $this->get('calc.sobrante_supervisor'),
            'total_ingreso' => $this->get('calc.total_ingreso'),
        ];
    }

    /**
     * @return array<string, float>
     */
    public function totalesSalidas(): array
    {
        return [
            'tito_rodillo' => $this->get('calc.tito_rodillo'),
            'tito_ruleta' => $this->get('calc.tito_ruleta'),
            'vale_rep_fondo' => $this->get('calc.vale_rep_fondo'),
            'salida_ruleta' => $this->get('inputs.salida_ruleta'),
            'pago_manual' => $this->get('inputs.pago_manual'),
            'deposito_efectivo' => $this->get('calc.deposito_efectivo'),
            'hopper' => $this->get('inputs.hopper'),
            'total_salida' => $this->get('calc.total_salida'),
        ];
    }
}
