<?php

namespace App\Repositories\Sueldos;

interface Concepto_SueldosRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function leeConcepto($filtros, $flPaginando = null);

    public function findPorCodigo(int $codigo);

    /** @return \Illuminate\Support\Collection<int, \App\Models\Sueldos\Concepto_Sueldos> */
    public function listadoParaConsulta(string $consulta, int $limit = 60);

    public function findActivoPorCodigo(int $codigo): ?\App\Models\Sueldos\Concepto_Sueldos;

    /**
     * Pull Anita haberes + habformula → ERP (insert / refresh de seeds sin líneas).
     *
     * @return array{
     *     en_anita: int,
     *     importados: int,
     *     actualizados: int,
     *     omitidos: int,
     *     con_formula: int,
     *     traducidos: int,
     *     pendientes_traduccion: int,
     *     errores: list<string>
     * }
     */
    public function sincronizarConAnita();

    /**
     * Reaplica AnitaFormulaTraductor sobre las líneas ya guardadas en
     * concepto_formula_sueldos y actualiza formula / formula_cantidad / formula_valor.
     *
     * @return array{
     *     procesados: int,
     *     traducidos: int,
     *     pendientes_traduccion: int,
     *     sin_lineas: int,
     *     errores: list<string>
     * }
     */
    public function retraducirFormulasDesdeLineas();
}
