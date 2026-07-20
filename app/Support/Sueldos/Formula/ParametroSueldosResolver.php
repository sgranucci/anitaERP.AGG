<?php

namespace App\Support\Sueldos\Formula;

use Illuminate\Support\Facades\DB;

/**
 * Resuelve los parametros globales vigentes a una fecha dada, en una sola
 * consulta. Prioriza el valor especifico de la empresa por sobre el global.
 */
class ParametroSueldosResolver
{
    /** @var array<string, float> */
    private array $numeros = [];

    /** @var array<string, string> */
    private array $textos = [];

    public function __construct(?int $empresaId, string $fecha)
    {
        $this->cargar($empresaId, $fecha);
    }

    private function cargar(?int $empresaId, string $fecha): void
    {
        // Ultimo valor vigente por parametro (<= fecha), luego elegimos empresa > global.
        $filas = DB::table('parametro_sueldos as p')
            ->join('parametro_valor_sueldos as v', 'v.parametro_id', '=', 'p.id')
            ->where('p.activo', true)
            ->where('v.fecha_vigencia', '<=', $fecha)
            ->when($empresaId, function ($q) use ($empresaId) {
                $q->where(function ($w) use ($empresaId) {
                    $w->whereNull('p.empresa_id')->orWhere('p.empresa_id', $empresaId);
                });
            }, function ($q) {
                $q->whereNull('p.empresa_id');
            })
            ->orderBy('v.fecha_vigencia')
            ->get(['p.codigo', 'p.empresa_id', 'p.tipo', 'v.valor', 'v.valor_texto', 'v.fecha_vigencia']);

        // Recorremos en orden de vigencia ascendente; nos quedamos con el ultimo
        // (mas reciente). La empresa especifica pisa al global.
        $especifico = [];
        foreach ($filas as $f) {
            $cod = strtoupper((string) $f->codigo);
            $esEmpresa = $f->empresa_id !== null;
            if (isset($especifico[$cod]) && $especifico[$cod] && ! $esEmpresa) {
                continue; // ya hay un valor especifico de empresa; no lo pisa el global
            }
            if ($f->tipo === 'texto') {
                $this->textos[$cod] = (string) $f->valor_texto;
                $this->numeros[$cod] = 0.0;
            } else {
                $this->numeros[$cod] = (float) $f->valor;
            }
            $especifico[$cod] = $esEmpresa;
        }
    }

    public function tiene(string $codigo): bool
    {
        $c = strtoupper($codigo);

        return array_key_exists($c, $this->numeros) || array_key_exists($c, $this->textos);
    }

    /**
     * @return float|string
     */
    public function valor(string $codigo)
    {
        $c = strtoupper($codigo);
        if (array_key_exists($c, $this->textos)) {
            return $this->textos[$c];
        }

        return $this->numeros[$c] ?? 0.0;
    }
}
