<?php

namespace App\Helpers;

class GeoIpHelper
{
    /**
     * Devuelve el listado completo de países ISO 3166-1 alpha-2 con nombre en español y emoji de bandera.
     *
     * @return array<string, array{name: string, flag: string}>
     */
    public static function getCountryList(): array
    {
        return [
            'ES' => ['name' => 'España', 'flag' => '🇪🇸'],
            'US' => ['name' => 'Estados Unidos', 'flag' => '🇺🇸'],
            'MX' => ['name' => 'México', 'flag' => '🇲🇽'],
            'AR' => ['name' => 'Argentina', 'flag' => '🇦🇷'],
            'CO' => ['name' => 'Colombia', 'flag' => '🇨🇴'],
            'CL' => ['name' => 'Chile', 'flag' => '🇨🇱'],
            'PE' => ['name' => 'Perú', 'flag' => '🇵🇪'],
            'FR' => ['name' => 'Francia', 'flag' => '🇫🇷'],
            'DE' => ['name' => 'Alemania', 'flag' => '🇩🇪'],
            'IT' => ['name' => 'Italia', 'flag' => '🇮🇹'],
            'PT' => ['name' => 'Portugal', 'flag' => '🇵🇹'],
            'GB' => ['name' => 'Reino Unido', 'flag' => '🇬🇧'],
            'CA' => ['name' => 'Canadá', 'flag' => '🇨🇦'],
            'BR' => ['name' => 'Brasil', 'flag' => '🇧🇷'],
            'RU' => ['name' => 'Rusia', 'flag' => '🇷🇺'],
            'CN' => ['name' => 'China', 'flag' => '🇨🇳'],
            'KP' => ['name' => 'Corea del Norte', 'flag' => '🇰🇵'],
            'KR' => ['name' => 'Corea del Sur', 'flag' => '🇰🇷'],
            'JP' => ['name' => 'Japón', 'flag' => '🇯🇵'],
            'IN' => ['name' => 'India', 'flag' => '🇮🇳'],
            'IR' => ['name' => 'Irán', 'flag' => '🇮🇷'],
            'UA' => ['name' => 'Ucrania', 'flag' => '🇺🇦'],
            'NL' => ['name' => 'Países Bajos', 'flag' => '🇳🇱'],
            'BE' => ['name' => 'Bélgica', 'flag' => '🇧🇪'],
            'CH' => ['name' => 'Suiza', 'flag' => '🇨🇭'],
            'AT' => ['name' => 'Austria', 'flag' => '🇦🇹'],
            'SE' => ['name' => 'Suecia', 'flag' => '🇸🇪'],
            'NO' => ['name' => 'Noruega', 'flag' => '🇳🇴'],
            'DK' => ['name' => 'Dinamarca', 'flag' => '🇩🇰'],
            'FI' => ['name' => 'Finlandia', 'flag' => '🇫🇮'],
            'PL' => ['name' => 'Polonia', 'flag' => '🇵🇱'],
            'IE' => ['name' => 'Irlanda', 'flag' => '🇮🇪'],
            'GR' => ['name' => 'Grecia', 'flag' => '🇬🇷'],
            'TR' => ['name' => 'Turquía', 'flag' => '🇹🇷'],
            'RO' => ['name' => 'Rumanía', 'flag' => '🇷🇴'],
            'CZ' => ['name' => 'República Checa', 'flag' => '🇨🇿'],
            'HU' => ['name' => 'Hungría', 'flag' => '🇭🇺'],
            'BG' => ['name' => 'Bulgaria', 'flag' => '🇧🇬'],
            'IL' => ['name' => 'Israel', 'flag' => '🇮🇱'],
            'SA' => ['name' => 'Arabia Saudita', 'flag' => '🇸🇦'],
            'AE' => ['name' => 'Emiratos Árabes Unidos', 'flag' => '🇦🇪'],
            'EG' => ['name' => 'Egipto', 'flag' => '🇪🇬'],
            'MA' => ['name' => 'Marruecos', 'flag' => '🇲🇦'],
            'ZA' => ['name' => 'Sudáfrica', 'flag' => '🇿🇦'],
            'NG' => ['name' => 'Nigeria', 'flag' => '🇳🇬'],
            'AU' => ['name' => 'Australia', 'flag' => '🇦🇺'],
            'NZ' => ['name' => 'Nueva Zelanda', 'flag' => '🇳🇿'],
            'SG' => ['name' => 'Singapur', 'flag' => '🇸🇬'],
            'HK' => ['name' => 'Hong Kong', 'flag' => '🇭🇰'],
            'TW' => ['name' => 'Taiwán', 'flag' => '🇹🇼'],
            'VN' => ['name' => 'Vietnam', 'flag' => '🇻🇳'],
            'ID' => ['name' => 'Indonesia', 'flag' => '🇮🇩'],
            'TH' => ['name' => 'Tailandia', 'flag' => '🇹🇭'],
            'MY' => ['name' => 'Malasia', 'flag' => '🇲🇾'],
            'PH' => ['name' => 'Filipinas', 'flag' => '🇵🇭'],
            'PK' => ['name' => 'Pakistán', 'flag' => '🇵🇰'],
            'BD' => ['name' => 'Bangladés', 'flag' => '🇧🇩'],
            'VE' => ['name' => 'Venezuela', 'flag' => '🇻🇪'],
            'UY' => ['name' => 'Uruguay', 'flag' => '🇺🇾'],
            'PY' => ['name' => 'Paraguay', 'flag' => '🇵🇾'],
            'BO' => ['name' => 'Bolivia', 'flag' => '🇧🇴'],
            'EC' => ['name' => 'Ecuador', 'flag' => '🇪🇨'],
            'CR' => ['name' => 'Costa Rica', 'flag' => '🇨🇷'],
            'PA' => ['name' => 'Panamá', 'flag' => '🇵🇦'],
            'DO' => ['name' => 'República Dominicana', 'flag' => '🇩🇴'],
            'GT' => ['name' => 'Guatemala', 'flag' => '🇬🇹'],
            'CU' => ['name' => 'Cuba', 'flag' => '🇨🇺'],
            'SV' => ['name' => 'El Salvador', 'flag' => '🇸🇻'],
            'HN' => ['name' => 'Honduras', 'flag' => '🇭🇳'],
            'NI' => ['name' => 'Nicaragua', 'flag' => '🇳🇮'],
            'BY' => ['name' => 'Bielorrusia', 'flag' => '🇧🇾'],
            'KZ' => ['name' => 'Kazajistán', 'flag' => '🇰🇿'],
            'SY' => ['name' => 'Siria', 'flag' => '🇸🇾'],
            'IQ' => ['name' => 'Irak', 'flag' => '🇮🇶'],
            'AF' => ['name' => 'Afganistán', 'flag' => '🇦🇫'],
        ];
    }
}

