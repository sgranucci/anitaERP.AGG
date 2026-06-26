<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\DescuentoGastronomia;

class DescuentoGastronomiaRepository implements DescuentoGastronomiaRepositoryInterface
{
    protected $model;

    public function __construct(DescuentoGastronomia $descuentoGastronomia)
    {
        $this->model = $descuentoGastronomia;
    }

    public function all()
    {
        return $this->model->with('cliente')->orderBy('nombre')->orderBy('codigo')->get();
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        return $this->model->findOrFail($id)->update($data);
    }

    public function delete($id)
    {
        return $this->model->destroy($id);
    }

    public function find($id)
    {
        return $this->model->find($id);
    }

    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }

    public function existeRegistro(): bool
    {
        return $this->model->query()->exists();
    }

    public function consultaDescuento(string $consulta): string
    {
        $consulta = strtoupper(trim($consulta));

        $query = $this->model->newQuery()->with('cliente');
        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('descuento_gastronomia.id', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('descuento_gastronomia.nombre', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('descuento_gastronomia.codigo', 'LIKE', '%'.$consulta.'%')
                    ->orWhereHas('cliente', function ($cq) use ($consulta) {
                        $cq->where('nombre', 'LIKE', '%'.$consulta.'%')
                            ->orWhere('codigo', 'LIKE', '%'.$consulta.'%');
                    });
            });
        }

        $data = $query
            ->orderByRaw('CAST(descuento_gastronomia.codigo AS UNSIGNED) ASC')
            ->orderBy('descuento_gastronomia.codigo')
            ->limit(200)
            ->get();

        $output = ['data' => ''];
        if ($data->isEmpty()) {
            $output['data'] = '<tr><td colspan="7">Sin resultados</td></tr>';
        } else {
            foreach ($data as $row) {
                $cli = $row->cliente;
                $cliTxt = $cli
                    ? e(trim((string) $cli->codigo).' — '.trim((string) $cli->nombre))
                    : '';
                $tipoTxt = e($row->etiquetaTipoValor().' ('.$row->tipovalor.')');
                $output['data'] .= '<tr>';
                $output['data'] .= '<td class="id">'.e($row->id).'</td>';
                $output['data'] .= '<td class="nombre">'.e($row->nombre).'</td>';
                $output['data'] .= '<td class="codigo">'.e($row->codigo).'</td>';
                $output['data'] .= '<td class="tipovalor">'.$tipoTxt.'</td>';
                $output['data'] .= '<td class="valor text-right">'.e(number_format((float) $row->valor, 4, ',', '.')).'</td>';
                $output['data'] .= '<td class="cliente_descuento">'.$cliTxt.'</td>';
                $output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultadescuento">Elegir</a></td>';
                $output['data'] .= '</tr>';
            }
        }

        return json_encode($output, JSON_UNESCAPED_UNICODE);
    }

    public function findPorCodigo(string $codigo): ?DescuentoGastronomia
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        $descuento = $this->model->newQuery()
            ->with('cliente')
            ->where('codigo', $codigo)
            ->first();

        if ($descuento) {
            return $descuento;
        }

        $alt = ltrim($codigo, '0');
        if ($alt !== '' && $alt !== $codigo) {
            return $this->model->newQuery()
                ->with('cliente')
                ->where('codigo', $alt)
                ->first();
        }

        return null;
    }
}
