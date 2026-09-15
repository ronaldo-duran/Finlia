<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Collection;
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

        // Primera corrida: marca el punto de partida y no reporta el historial.
        $this->artisan('finlia:report-errors')->assertSuccessful();
        $this->assertCount(0, $this->enviados());

        file_put_contents($this->log, implode("\n", [
            '[2026-09-14 11:00:00] production.ERROR: SQLSTATE[HY000] conexión rechazada',
            '#0 /app/vendor/laravel/framework/src/Connection.php(12): traza',
            '[2026-09-14 11:05:00] production.INFO: todo bien',
            '[2026-09-14 11:10:00] production.ERROR: SQLSTATE[HY000] conexión rechazada',
            '[2026-09-14 11:15:00] production.CRITICAL: Brevo rechazó el envío',
        ])."\n", FILE_APPEND);

        $this->artisan('finlia:report-errors')->assertSuccessful();

        $this->assertCount(1, $this->enviados());
        $correo = $this->enviados()->first()->getOriginalMessage();
        $texto = (string) $correo->getTextBody();

        $this->assertSame('Finlia: 3 error(es) nuevo(s) en el log', $correo->getSubject());
        $this->assertSame('dueno@finlia.test', $correo->getTo()[0]->getAddress());
        $this->assertStringContainsString('2× · última: 2026-09-14 11:10:00', $texto);
        $this->assertStringContainsString('CRITICAL: Brevo rechazó el envío', $texto);
        $this->assertStringNotContainsString('Error viejo', $texto);
        $this->assertStringNotContainsString('todo bien', $texto);

        // Nada nuevo desde la última corrida: silencio.
        $this->artisan('finlia:report-errors')->assertSuccessful();
        $this->assertCount(1, $this->enviados());
    }

    private function enviados(): Collection
    {
        return app('mailer')->getSymfonyTransport()->messages();
    }
}
