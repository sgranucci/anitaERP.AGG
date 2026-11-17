<?php

declare(strict_types=1);

namespace Tests\Unit\Support\ReglaNegocio;

use App\Models\Configuracion\ReglaNegocio;
use App\Models\Configuracion\ReglaNegocioConfig;
use App\Services\Configuracion\ReglaNegocioMotorService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Requiere MySQL + migración. Con phpunit.xml (sqlite :memory:) se salta.
 */
class ReglaNegocioMotorServiceTest extends TestCase
{
    private ?ReglaNegocioMotorService $motor = null;

    private bool $motorPrev = false;

    private bool $listo = false;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            if (config('database.default') === 'sqlite'
                || (string) config('database.connections.'.config('database.default').'.database') === ':memory:') {
                $this->markTestSkipped('Motor sync: saltado bajo sqlite de phpunit.xml');
            }
            if (! Schema::hasTable('regla_negocio') || ! Schema::hasTable('regla_negocio_config')) {
                $this->markTestSkipped('Tablas regla_negocio* no disponibles.');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB no disponible: '.$e->getMessage());
        }

        Config::set('regla_negocio.habilitado_entorno', true);
        Config::set('regla_negocio.log_ejecuciones', false);
        Config::set('regla_negocio_acciones.catalogo', [
            'noop' => ['etiqueta' => 'noop'],
            'deny' => ['etiqueta' => 'deny'],
            'set_dato' => ['etiqueta' => 'set_dato'],
            'require_approval' => ['etiqueta' => 'require_approval'],
            'enviar_aviso' => ['etiqueta' => 'enviar_aviso'],
        ]);

        $cfg = ReglaNegocioConfig::actual();
        $this->motorPrev = (bool) $cfg->motor_habilitado;
        $cfg->motor_habilitado = true;
        $cfg->save();

        ReglaNegocio::query()->where('codigo', 'like', 'UT_RN_%')->delete();
        $this->motor = app(ReglaNegocioMotorService::class);
        $this->listo = true;
    }

    protected function tearDown(): void
    {
        if ($this->listo) {
            try {
                ReglaNegocio::query()->where('codigo', 'like', 'UT_RN_%')->delete();
                $cfg = ReglaNegocioConfig::actual();
                $cfg->motor_habilitado = $this->motorPrev;
                $cfg->save();
            } catch (\Throwable) {
                //
            }
        }
        parent::tearDown();
    }

    public function test_motor_off_devuelve_no_evaluado(): void
    {
        ReglaNegocioConfig::actual()->update(['motor_habilitado' => false]);
        $d = $this->motor->decidir('demo.punto.piloto', ['monto' => 1]);
        $this->assertFalse($d->evaluado);
        $this->assertTrue($d->permitido);
    }

    public function test_sin_match_devuelve_no_evaluado_para_legacy(): void
    {
        $this->crearRegla('UT_RN_DENY_ALTO', 'demo.punto.piloto', [
            'logic' => 'and',
            'rules' => [['campo' => 'monto', 'op' => 'gte', 'valor' => 9999]],
        ], [['tipo' => 'deny', 'mensaje' => 'Demasiado']]);

        $d = $this->motor->decidir('demo.punto.piloto', ['monto' => 10]);
        $this->assertFalse($d->evaluado);
        $this->assertTrue($d->permitido);
    }

    public function test_deny_bloquea(): void
    {
        $this->crearRegla('UT_RN_DENY', 'demo.punto.piloto', [
            'logic' => 'and',
            'rules' => [['campo' => 'monto', 'op' => 'gte', 'valor' => 1000]],
        ], [['tipo' => 'deny', 'mensaje' => 'Monto alto']]);

        $d = $this->motor->decidir('demo.punto.piloto', ['monto' => 1500]);
        $this->assertTrue($d->evaluado);
        $this->assertFalse($d->permitido);
        $this->assertSame('Monto alto', $d->mensaje);
        $this->assertContains('UT_RN_DENY', $d->reglas);
    }

    public function test_set_dato_y_require_approval(): void
    {
        $this->crearRegla('UT_RN_SET', 'stock.tm.requiere_aprobacion', [
            'logic' => 'and',
            'rules' => [['campo' => 'tipo', 'op' => 'eq', 'valor' => 'TRA']],
        ], [
            ['tipo' => 'set_dato', 'clave' => 'modo', 'valor' => 'ferli'],
            ['tipo' => 'require_approval', 'mensaje' => 'Pedir OK TM'],
        ], 'stock');

        $d = $this->motor->decidir('stock.tm.requiere_aprobacion', ['tipo' => 'TRA']);
        $this->assertTrue($d->evaluado);
        $this->assertTrue($d->permitido);
        $this->assertSame('ferli', $d->datos['modo']);
        $this->assertTrue($d->datos['requiere_aprobacion']);
    }

    public function test_stop_on_match_no_evalua_siguiente(): void
    {
        $this->crearRegla('UT_RN_PRIMERA', 'demo.punto.piloto', [
            'logic' => 'and', 'rules' => [],
        ], [['tipo' => 'set_dato', 'clave' => 'quien', 'valor' => 'primera']], 'configuracion', 1, true);

        $this->crearRegla('UT_RN_SEGUNDA', 'demo.punto.piloto', [
            'logic' => 'and', 'rules' => [],
        ], [['tipo' => 'set_dato', 'clave' => 'quien', 'valor' => 'segunda']], 'configuracion', 2, true);

        $d = $this->motor->decidir('demo.punto.piloto', []);
        $this->assertSame(['UT_RN_PRIMERA'], $d->reglas);
        $this->assertSame('primera', $d->datos['quien']);
    }

    /**
     * @param  array<string, mixed>  $condiciones
     * @param  list<array<string, mixed>>  $acciones
     */
    private function crearRegla(
        string $codigo,
        string $punto,
        array $condiciones,
        array $acciones,
        string $dominio = 'configuracion',
        int $prioridad = 10,
        bool $stop = true,
    ): ReglaNegocio {
        return ReglaNegocio::query()->create([
            'codigo' => $codigo,
            'nombre' => $codigo,
            'dominio' => $dominio,
            'punto' => $punto,
            'activo' => true,
            'prioridad' => $prioridad,
            'stop_on_match' => $stop,
            'condiciones' => $condiciones,
            'acciones' => $acciones,
        ]);
    }
}
