<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Servicio TOTP (Time-Based One-Time Password - RFC 6238 / RFC 4226) para 2FA (Fase 9).
 * Compatible con Google Authenticator, Authy, 1Password y Microsoft Authenticator.
 */
class TotpService
{
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    public const PERIOD        = 30;
    public const DIGITS        = 6;

    /**
     * Genera un secreto aleatorio codificado en Base32 RFC 4648.
     */
    public function generateSecret(int $length = 16): string
    {
        if ($length < 16 || $length > 64) {
            throw new InvalidArgumentException('La longitud del secreto TOTP debe estar entre 16 y 64 caracteres.');
        }

        $secret = '';
        $maxIdx = strlen(self::BASE32_CHARS) - 1;
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_CHARS[random_int(0, $maxIdx)];
        }

        return $secret;
    }

    /**
     * Calcula el código TOTP de 6 dígitos para un instante de tiempo dado.
     */
    public function getCode(string $secret, ?int $timeSlice = null): string
    {
        if ($timeSlice === null) {
            $timeSlice = (int) floor(time() / self::PERIOD);
        }

        $secretKey = $this->base32Decode($secret);
        if ($secretKey === '') {
            throw new InvalidArgumentException('Secreto Base32 inválido.');
        }

        // Empaquetar contador de 64 bits big-endian
        $timeBytes = pack('N*', 0) . pack('N*', $timeSlice);
        $hash      = hash_hmac('sha1', $timeBytes, $secretKey, true);

        // Truncamiento dinámico según RFC 4226
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value  = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        );

        $modulo = 10 ** self::DIGITS;
        return str_pad((string) ($value % $modulo), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Verifica un código TOTP de 6 dígitos en tiempo constante (hash_equals)
     * permitiendo una ventana de tolerancia de +- $discrepancy intervalos de 30s.
     */
    public function verifyCode(string $secret, string $code, int $discrepancy = 1, ?int $currentTimeSlice = null): bool
    {
        $code = trim($code);
        if (!preg_match('/^[0-9]{6}$/', $code)) {
            return false;
        }

        try {
            if ($currentTimeSlice === null) {
                $currentTimeSlice = (int) floor(time() / self::PERIOD);
            }

            for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
                $calculated = $this->getCode($secret, $currentTimeSlice + $i);
                if (hash_equals($calculated, $code)) {
                    return true;
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }

    /**
     * Genera la URI estándar otpauth://totp/ para aplicaciones de autenticación.
     */
    public function getProvisioningUri(string $email, string $secret, string $issuer = 'Proxy Manager'): string
    {
        $label = rawurlencode($issuer . ':' . $email);
        $query = http_build_query([
            'secret' => strtoupper(trim($secret)),
            'issuer' => $issuer,
            'period' => self::PERIOD,
            'digits' => self::DIGITS,
        ], '', '&', PHP_QUERY_RFC3986);

        return "otpauth://totp/{$label}?{$query}";
    }

    /**
     * Genera el código QR en formato Data URI SVG vectorizado para escaneo directo.
     */
    public function getQrCodeDataUri(string $uri): string
    {
        if ($uri === '') {
            return '';
        }

        try {
            return (new \chillerlan\QRCode\QRCode())->render($uri);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Decodifica una cadena Base32 a binario.
     */
    protected function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        if ($secret === '') {
            return '';
        }

        $binaryString = '';
        $chars = str_split($secret);
        foreach ($chars as $char) {
            $pos = strpos(self::BASE32_CHARS, $char);
            if ($pos === false) {
                return '';
            }
            $binaryString .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $output = '';
        $eightBits = str_split($binaryString, 8);
        foreach ($eightBits as $bits) {
            if (strlen($bits) === 8) {
                $output .= chr((int) bindec($bits));
            }
        }

        return $output;
    }
}

