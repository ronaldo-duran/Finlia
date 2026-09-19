<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Interruptores de funciones sujetas a plan (Épica 12).
 *
 * La comprobación es SIEMPRE en backend contra la suscripción activa del
 * hogar ([docs/SECURITY.md §8](../../docs/SECURITY.md), [AGENTS.md §2.7](../../AGENTS.md)).
 * Un flag del cliente NO desbloquea nada.
 *
 * v0.39 deja el enum listo pero ninguna feature Premium está activada
 * todavía: se encienden en versiones siguientes (chat IA con BYOK, PDF
 * de reportes, autoconocimiento avanzado…). Añadir una nueva feature es
 * añadir un caso aquí y una entrada en el mapa `features` del seeder.
 */
enum PlanFeature: string
{
    case PdfReports = 'pdf_reports';
    case ChatAi = 'chat_ai';
    case CompulsiveInsights = 'compulsive_insights';
    case ExtendedHistory = 'extended_history';
    case UnlimitedSurveys = 'unlimited_surveys';

    public function label(): string
    {
        return match ($this) {
            self::PdfReports => 'Exportación PDF de reportes',
            self::ChatAi => 'Chat con IA sobre tus finanzas',
            self::CompulsiveInsights => 'Insights de autoconocimiento',
            self::ExtendedHistory => 'Historial extendido en reportes',
            self::UnlimitedSurveys => 'Encuestas de compras ilimitadas',
        };
    }
}
