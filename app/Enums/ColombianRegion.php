<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Regiones de Colombia para el perfil del usuario (Plan 04, ADR-0032):
 * los 32 departamentos + Bogotá D.C. (distrito capital, subdivisión
 * propia en ISO 3166-2:CO — omitirla dejaría fuera a gran parte del
 * mercado). Lista FIJA en código, no tabla: no es dato del usuario sino
 * catálogo estable, y así el select y la validación comparten una sola
 * fuente.
 *
 * El valor almacenado es el slug (estable ante cambios de etiqueta);
 * label() da el nombre para mostrar. Solo para analítica de mercado:
 * nada de lógica financiera por región.
 */
enum ColombianRegion: string
{
    case Amazonas = 'amazonas';
    case Antioquia = 'antioquia';
    case Arauca = 'arauca';
    case Atlantico = 'atlantico';
    case BogotaDc = 'bogota-dc';
    case Bolivar = 'bolivar';
    case Boyaca = 'boyaca';
    case Caldas = 'caldas';
    case Caqueta = 'caqueta';
    case Casanare = 'casanare';
    case Cauca = 'cauca';
    case Cesar = 'cesar';
    case Choco = 'choco';
    case Cordoba = 'cordoba';
    case Cundinamarca = 'cundinamarca';
    case Guainia = 'guainia';
    case Guaviare = 'guaviare';
    case Huila = 'huila';
    case LaGuajira = 'la-guajira';
    case Magdalena = 'magdalena';
    case Meta = 'meta';
    case Narino = 'narino';
    case NorteDeSantander = 'norte-de-santander';
    case Putumayo = 'putumayo';
    case Quindio = 'quindio';
    case Risaralda = 'risaralda';
    case SanAndresYProvidencia = 'san-andres-y-providencia';
    case Santander = 'santander';
    case Sucre = 'sucre';
    case Tolima = 'tolima';
    case ValleDelCauca = 'valle-del-cauca';
    case Vaupes = 'vaupes';
    case Vichada = 'vichada';

    public function label(): string
    {
        return match ($this) {
            self::Amazonas => 'Amazonas',
            self::Antioquia => 'Antioquia',
            self::Arauca => 'Arauca',
            self::Atlantico => 'Atlántico',
            self::BogotaDc => 'Bogotá D.C.',
            self::Bolivar => 'Bolívar',
            self::Boyaca => 'Boyacá',
            self::Caldas => 'Caldas',
            self::Caqueta => 'Caquetá',
            self::Casanare => 'Casanare',
            self::Cauca => 'Cauca',
            self::Cesar => 'Cesar',
            self::Choco => 'Chocó',
            self::Cordoba => 'Córdoba',
            self::Cundinamarca => 'Cundinamarca',
            self::Guainia => 'Guainía',
            self::Guaviare => 'Guaviare',
            self::Huila => 'Huila',
            self::LaGuajira => 'La Guajira',
            self::Magdalena => 'Magdalena',
            self::Meta => 'Meta',
            self::Narino => 'Nariño',
            self::NorteDeSantander => 'Norte de Santander',
            self::Putumayo => 'Putumayo',
            self::Quindio => 'Quindío',
            self::Risaralda => 'Risaralda',
            self::SanAndresYProvidencia => 'San Andrés y Providencia',
            self::Santander => 'Santander',
            self::Sucre => 'Sucre',
            self::Tolima => 'Tolima',
            self::ValleDelCauca => 'Valle del Cauca',
            self::Vaupes => 'Vaupés',
            self::Vichada => 'Vichada',
        };
    }

    /**
     * Opciones para un <select>: [slug => etiqueta] ordenadas alfabéticamente
     * por la etiqueta (así se lee en español: "Bogotá D.C." junto a "Boyacá").
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $labels = [];
        foreach (self::cases() as $case) {
            $labels[$case->value] = $case->label();
        }

        return collect($labels)->sort()->all();
    }
}
