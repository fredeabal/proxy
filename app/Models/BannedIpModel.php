<?php

namespace App\Models;

use CodeIgniter\Model;
use InvalidArgumentException;

class BannedIpModel extends Model
{
    protected $table            = 'banned_ips';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'ip_address',
        'reason',
        'failed_attempts',
        'is_permanent',
        'expires_at',
        'created_by',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Valida si una cadena es una dirección IP individual (IPv4/IPv6) o un rango CIDR válido (ej. 203.0.113.0/24).
     */
    public static function isValidIpOrCidr(string $target): bool
    {
        $target = trim($target);
        if ($target === '') {
            return false;
        }

        if (filter_var($target, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        if (! str_contains($target, '/')) {
            return false;
        }

        [$subnet, $prefix] = explode('/', $target, 2);
        if (! ctype_digit($prefix)) {
            return false;
        }

        $prefixInt = (int) $prefix;
        if (filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $prefixInt >= 8 && $prefixInt <= 32;
        }

        if (filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return $prefixInt >= 16 && $prefixInt <= 128;
        }

        return false;
    }

    /**
     * Comprueba si una dirección IP individual coincide con una IP exacta o pertenece a un rango CIDR.
     */
    public static function ipMatchesTarget(string $ip, string $target): bool
    {
        $ip     = trim($ip);
        $target = trim($target);

        if ($ip === $target) {
            return true;
        }

        if (! str_contains($target, '/')) {
            return false;
        }

        [$subnet, $prefix] = explode('/', $target, 2);
        $ipBin     = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $prefixLen = (int) $prefix;
        $maxBits   = strlen($ipBin) * 8;
        if ($prefixLen < 0 || $prefixLen > $maxBits) {
            return false;
        }

        $fullBytes = intdiv($prefixLen, 8);
        $remBits   = $prefixLen % 8;

        if ($fullBytes > 0) {
            if (substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
                return false;
            }
        }

        if ($remBits > 0) {
            $mask = (0xFF << (8 - $remBits)) & 0xFF;
            if ((ord($ipBin[$fullBytes]) & $mask) !== (ord($subnetBin[$fullBytes]) & $mask)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Comprueba si una dirección IP está actualmente bloqueada (por IP exacta o por rango CIDR).
     * Limpia automáticamente el bloqueo si ya expiró.
     */
    public function isBanned(string $ip): ?array
    {
        $ip = trim($ip);
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        try {
            $row = $this->where('ip_address', $ip)->first();
        } catch (\Throwable $e) {
            return null;
        }

        if ($row) {
            if ((int) $row['is_permanent'] === 1) {
                return $row;
            }

            if (! empty($row['expires_at'])) {
                if (strtotime((string) $row['expires_at']) > time()) {
                    return $row;
                }

                // El baneo temporal ha expirado: eliminar registro
                $this->delete((int) $row['id']);
            }
        }

        // Comprobar si la IP pertenece a algún rango CIDR activo bloqueado
        try {
            $cidrRows = $this->like('ip_address', '/', 'both')->findAll();
            foreach ($cidrRows as $cidrRow) {
                $isActive = ((int) $cidrRow['is_permanent'] === 1)
                    || (! empty($cidrRow['expires_at']) && strtotime((string) $cidrRow['expires_at']) > time());

                if (! $isActive) {
                    if (! empty($cidrRow['expires_at']) && strtotime((string) $cidrRow['expires_at']) <= time()) {
                        $this->delete((int) $cidrRow['id']);
                    }
                    continue;
                }

                if (self::ipMatchesTarget($ip, (string) $cidrRow['ip_address'])) {
                    return $cidrRow;
                }
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    /**
     * Registra un intento fallido de autenticación y bloquea automáticamente la IP
     * al alcanzar el umbral (por defecto 5 intentos -> 30 minutos de bloqueo).
     *
     * @return bool True si la IP acaba de ser bloqueada en este intento.
     */
    public function recordFailedAttempt(
        string $ip,
        string $reason = '5 intentos fallidos de inicio de sesión',
        int $maxAttempts = 5,
        int $banMinutes = 30
    ): bool {
        $ip = trim($ip);
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        try {
            $existing = $this->where('ip_address', $ip)->first();
        } catch (\Throwable $e) {
            return false;
        }

        $now = date('Y-m-d H:i:s');

        if (! $existing) {
            $attempts = 1;
            $isBannedNow = $attempts >= $maxAttempts;
            $this->insert([
                'ip_address'      => $ip,
                'reason'          => $reason,
                'failed_attempts' => $attempts,
                'is_permanent'    => 0,
                'expires_at'      => $isBannedNow ? date('Y-m-d H:i:s', time() + ($banMinutes * 60)) : null,
                'created_by'      => 'system',
            ]);

            return $isBannedNow;
        }

        // Si el bloqueo previo ya expiró, reiniciar contador a 1
        if ((int) $existing['is_permanent'] === 0 && ! empty($existing['expires_at']) && strtotime((string) $existing['expires_at']) <= time()) {
            $attempts = 1;
        } else {
            $attempts = ((int) $existing['failed_attempts']) + 1;
        }

        $isBannedNow = $attempts >= $maxAttempts;
        $updateData = [
            'failed_attempts' => $attempts,
            'reason'          => $reason,
            'updated_at'      => $now,
        ];

        if ($isBannedNow && (int) $existing['is_permanent'] === 0) {
            $updateData['expires_at'] = date('Y-m-d H:i:s', time() + ($banMinutes * 60));
            $updateData['created_by'] = 'system';
        }

        $this->update((int) $existing['id'], $updateData);

        return $isBannedNow;
    }

    /**
     * Limpia el contador de intentos fallidos tras un inicio de sesión exitoso.
     */
    public function clearAttempts(string $ip): void
    {
        $ip = trim($ip);
        if ($ip === '') {
            return;
        }

        try {
            $existing = $this->where('ip_address', $ip)->first();
            if ($existing && (int) $existing['is_permanent'] === 0) {
                $this->delete((int) $existing['id']);
            }
        } catch (\Throwable $e) {
            // Ignorar si la tabla aún no existe en algún entorno de test aislado
        }
    }

    /**
     * Bloquea una dirección IP manual o permanentemente.
     *
     * @param string $ip Dirección IPv4 o IPv6
     * @param string $reason Motivo del bloqueo
     * @param int $durationMinutes Duración en minutos (0 = permanente)
     * @param string $createdBy Origen ('admin' o 'system')
     */
    public function banIp(string $ip, string $reason, int $durationMinutes = 0, string $createdBy = 'admin'): array
    {
        $ip = trim($ip);
        if (! self::isValidIpOrCidr($ip)) {
            throw new InvalidArgumentException("La dirección IP o rango CIDR '{$ip}' no es válido.");
        }

        $reason = trim($reason) !== '' ? trim($reason) : 'Bloqueo manual por administrador';
        $isPermanent = $durationMinutes <= 0 ? 1 : 0;
        $expiresAt   = $isPermanent ? null : date('Y-m-d H:i:s', time() + ($durationMinutes * 60));

        $existing = $this->where('ip_address', $ip)->first();

        $payload = [
            'ip_address'      => $ip,
            'reason'          => mb_substr($reason, 0, 250),
            'failed_attempts' => $existing ? max(1, (int) $existing['failed_attempts']) : 0,
            'is_permanent'    => $isPermanent,
            'expires_at'      => $expiresAt,
            'created_by'      => $createdBy,
        ];

        if ($existing) {
            $this->update((int) $existing['id'], $payload);
            return (array) $this->find((int) $existing['id']);
        }

        $id = $this->insert($payload);
        return (array) $this->find((int) $id);
    }

    /**
     * Elimina el bloqueo de una IP por su ID.
     */
    public function unbanById(int $id): ?array
    {
        $row = $this->find($id);
        if (! $row) {
            return null;
        }

        $this->delete($id);
        return $row;
    }

    /**
     * Limpia de la base de datos todos los bloqueos temporales cuya fecha ya haya expirado.
     */
    public function cleanExpiredBans(): int
    {
        $now = date('Y-m-d H:i:s');
        try {
            $this->where('is_permanent', 0)
                ->where('expires_at IS NOT NULL', null, false)
                ->where('expires_at <=', $now)
                ->delete();

            return $this->db->affectedRows();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Devuelve todas las IPs actualmente bloqueadas (permanentes o con expiración futura).
     */
    public function getActiveBans(): array
    {
        $this->cleanExpiredBans();
        $now = date('Y-m-d H:i:s');

        try {
            return $this->groupStart()
                    ->where('is_permanent', 1)
                    ->orGroupStart()
                        ->where('expires_at IS NOT NULL', null, false)
                        ->where('expires_at >', $now)
                    ->groupEnd()
                ->groupEnd()
                ->orderBy('updated_at', 'DESC')
                ->orderBy('id', 'DESC')
                ->findAll();
        } catch (\Throwable $e) {
            return [];
        }
    }
}

