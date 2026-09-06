<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MovementSummaryService;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Agrupación por mes en cada motor de base de datos.
 *
 * Existe por un fallo real: la primera versión asumía «o SQLite, o MySQL» y
 * mandaba `DATE_FORMAT` con acentos graves a **PostgreSQL**, que no tiene esa
 * función ni acepta ese delimitador. La suite corre en SQLite, así que pasó
 * limpia y el error apareció al abrir el panel en producción.
 *
 * De ahí las dos cosas que se comprueban aquí:
 *  - cada motor recibe SU función (ninguno hereda la de otro por descarte), y
 *  - un motor desconocido falla de inmediato con un mensaje accionable, en
 *    vez de generar SQL inválido a mitad de una consulta.
 */
class MonthKeyExpressionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function motores(): array
    {
        return [
            'sqlite' => ['sqlite', '"date"', 'strftime'],
            'mysql' => ['mysql', '`date`', 'DATE_FORMAT'],
            'mariadb' => ['mariadb', '`date`', 'DATE_FORMAT'],
            'postgres' => ['pgsql', '"date"', 'to_char'],
            'sql server' => ['sqlsrv', '[date]', 'FORMAT'],
        ];
    }

    #[DataProvider('motores')]
    public function test_cada_motor_recibe_su_propia_funcion(string $driver, string $columna, string $funcion): void
    {
        $expresion = MovementSummaryService::monthKeyFor($driver, $columna);

        $this->assertStringContainsString($funcion, $expresion);
        $this->assertStringContainsString($columna, $expresion);
    }

    public function test_un_motor_desconocido_falla_con_un_mensaje_accionable(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/no sabe agrupar por mes en «oracle»/');

        MovementSummaryService::monthKeyFor('oracle', '"date"');
    }

    /**
     * El identificador nunca se escribe a mano: lo cita el grammar de cada
     * motor. Escribirlo con acentos graves fue la otra mitad del fallo —
     * MySQL y SQLite los aceptan, PostgreSQL no.
     */
    public function test_el_identificador_lo_cita_el_grammar_de_cada_motor(): void
    {
        $esperado = [
            SQLiteGrammar::class => '"date"',
            MySqlGrammar::class => '`date`',
            PostgresGrammar::class => '"date"',
        ];

        foreach ($esperado as $grammar => $citado) {
            $this->assertSame(
                $citado,
                (new $grammar(DB::connection()))->wrap('date'),
                "El grammar {$grammar} no cita como se esperaba.",
            );
        }
    }

    /**
     * La expresión de la conexión real de la suite tiene que ser ejecutable:
     * cierra el círculo entre la función pura y el motor de verdad.
     */
    public function test_la_expresion_de_la_conexion_actual_produce_sql_valido(): void
    {
        $driver = DB::connection()->getDriverName();
        $columna = DB::connection()->getQueryGrammar()->wrap('date');

        $expresion = MovementSummaryService::monthKeyFor($driver, $columna);

        $fila = DB::table('incomes')
            ->selectRaw("{$expresion} as ym")
            ->limit(1)
            ->get();

        // Sin filas la consulta igual se ejecuta: lo que se prueba es que el
        // motor acepta la expresión, no que haya datos.
        $this->assertNotNull($fila);
    }
}
