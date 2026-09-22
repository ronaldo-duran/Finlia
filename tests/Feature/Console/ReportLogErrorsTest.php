<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * Aviso de errores en producción (plan de lanzamiento T2, ADR-0044).
 *
 * `Mail::fake()` no registra `Mail::raw()`, así que se envía por el transporte
 * `array` —marcado como entregable— y se inspeccionan los mensajes.
 */
class ReportLogErrorsTest extends TestCase
{
    private string $log;

    protected function setUp(): void
    {
        parent::setUp();

        $this->log = (string) tempnam(sys_get_temp_dir(), 'finlia-log');

        config([
            'logging.channels.single.path' => $this->log,
            'finlia.contact.inbox' => 'dueno@finlia.test',
            'mail.default' => 'array',
            'finlia.mail.fake_transports' => [],
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->log);
        @unlink($this->log.'.offset');

        parent::tearDown();
    }

    public function test_avisa_una_vez_de_los_errores_nuevos_agrupados(): void
    {
        file_put_contents($this->log, "[2026-09-14 10:00:00] production.ERROR: Error viejo\n");

        $this->artisan('finlia:report-errors')->assertSuccessful();
        $this->assertCount(0, $this->enviados());

        $this->anadir(
            '[2026-09-14 11:00:00] production.ERROR: SQLSTATE[HY000] conexión rechazada []',
            '#0 /app/vendor/laravel/framework/src/Connection.php(12): traza',
            '[2026-09-14 11:05:00] production.INFO: todo bien',
            '[2026-09-14 11:10:00] production.ERROR: SQLSTATE[HY000] conexión rechazada []',
            '[2026-09-14 11:15:00] production.CRITICAL: Brevo rechazó el envío',
        );

        $this->artisan('finlia:report-errors')->assertSuccessful();

        $this->assertCount(1, $this->enviados());
        $correo = $this->enviados()->first()->getOriginalMessage();
        $texto = (string) $correo->getTextBody();

        $this->assertSame('Finlia: 3 error(es) nuevo(s) en el log', $correo->getSubject());
        $this->assertSame('dueno@finlia.test', $correo->getTo()[0]->getAddress());
        $this->assertStringContainsString("2× · última: 2026-09-14 11:10:00\nERROR: SQLSTATE[HY000] conexión rechazada", $texto);
        $this->assertStringContainsString('CRITICAL: Brevo rechazó el envío', $texto);
        $this->assertStringNotContainsString('Error viejo', $texto);
        $this->assertStringNotContainsString('todo bien', $texto);

        $this->artisan('finlia:report-errors')->assertSuccessful();
        $this->assertCount(1, $this->enviados());
    }

    public function test_de_una_excepcion_no_saca_del_hosting_los_datos_del_mensaje(): void
    {
        $this->marcarInicio();

        $sql = 'insert into users (email, amount) values (ana@gmail.com, 1250000.00)';
        $this->anadir(
            "[2026-09-14 12:00:00] production.ERROR: SQLSTATE[23000]: Duplicate entry 'ana@gmail.com' for key 'users_email_unique' (Connection: mysql, SQL: {$sql}) "
            .'{"exception":"[object] (Illuminate\\\\Database\\\\QueryException(code: 23000): SQLSTATE[23000]: Duplicate entry '
            ."'ana@gmail.com' (Connection: mysql, SQL: {$sql}) at /app/vendor/laravel/framework/src/Illuminate/Database/Connection.php:838)",
            '[stacktrace]',
            '#0 /app/vendor/laravel/framework/src/Illuminate/Database/Connection.php(791): traza"}',
        );

        $this->artisan('finlia:report-errors')->assertSuccessful();

        $texto = (string) $this->enviados()->first()->getOriginalMessage()->getTextBody();

        $this->assertStringContainsString('ERROR: Illuminate\Database\QueryException en /app/vendor/laravel/framework/src/Illuminate/Database/Connection.php:838', $texto);
        $this->assertStringNotContainsString('ana@gmail.com', $texto);
        $this->assertStringNotContainsString('1250000', $texto);
    }

    public function test_detecta_un_log_reemplazado_aunque_ya_sea_mas_largo_que_la_marca(): void
    {
        file_put_contents($this->log, "[2026-09-14 09:00:00] production.INFO: arranque\n");
        $this->artisan('finlia:report-errors')->assertSuccessful();

        file_put_contents($this->log, implode("\n", [
            '[2026-09-15 00:00:01] production.ERROR: Fallo tras la rotación []',
            '[2026-09-15 00:00:02] production.INFO: '.str_repeat('relleno ', 20),
        ])."\n");

        $this->artisan('finlia:report-errors')->assertSuccessful();

        $this->assertCount(1, $this->enviados());
        $this->assertStringContainsString('Fallo tras la rotación', (string) $this->enviados()->first()->getOriginalMessage()->getTextBody());
    }

    public function test_no_avisa_de_una_linea_a_medias_ni_la_repite_al_completarse(): void
    {
        $this->marcarInicio();

        file_put_contents($this->log, '[2026-09-14 13:00:00] production.ERROR: Fallo largo []', FILE_APPEND);
        $this->artisan('finlia:report-errors')->assertSuccessful();
        $this->assertCount(0, $this->enviados());

        file_put_contents($this->log, "\n", FILE_APPEND);
        $this->artisan('finlia:report-errors')->assertSuccessful();
        $this->artisan('finlia:report-errors')->assertSuccessful();

        $this->assertCount(1, $this->enviados());
    }

    public function test_un_fallo_de_envio_no_avanza_la_marca(): void
    {
        $this->marcarInicio();
        $this->anadir('[2026-09-14 14:00:00] production.ERROR: Algo se rompió []');
        $marcaAntes = file_get_contents($this->log.'.offset');

        Mail::shouldReceive('raw')->once()->andThrow(new RuntimeException('Cuota diaria agotada'));

        $this->artisan('finlia:report-errors')->assertFailed();

        $this->assertSame($marcaAntes, file_get_contents($this->log.'.offset'));
    }

    private function marcarInicio(): void
    {
        file_put_contents($this->log, "[2026-09-14 08:00:00] production.INFO: arranque\n");
        $this->artisan('finlia:report-errors')->assertSuccessful();
    }

    private function anadir(string ...$lineas): void
    {
        file_put_contents($this->log, implode("\n", $lineas)."\n", FILE_APPEND);
    }

    private function enviados(): Collection
    {
        return app('mailer')->getSymfonyTransport()->messages();
    }
}
