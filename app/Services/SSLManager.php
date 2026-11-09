<?php

namespace App\Services;

use App\Models\AuditLogModel;
use App\Models\CertificateModel;
use App\Models\ProxyHostModel;
use Config\Nginx as NginxConfig;
use DateTime;
use InvalidArgumentException;

/**
 * Gestor integral de emisión, renovación y ciclo de vida de certificados SSL (Let's Encrypt / ACME).
 */
class SSLManager
{
    protected NginxConfig $config;
    protected CertificateModel $certModel;
    protected ProxyHostModel $proxyModel;
    protected NginxManager $nginxManager;

    public function __construct(
        ?NginxConfig $config = null,
        ?CertificateModel $certModel = null,
        ?ProxyHostModel $proxyModel = null,
        ?NginxManager $nginxManager = null
    ) {
        $this->config       = $config ?? config(NginxConfig::class);
        $this->certModel    = $certModel ?? new CertificateModel();
        $this->proxyModel   = $proxyModel ?? new ProxyHostModel();
        $this->nginxManager = $nginxManager ?? new NginxManager($this->config);
    }

    /**
     * Emite un nuevo certificado SSL con Let's Encrypt para un dominio y aliases opcionales.
     *
     * @param string $domain Dominio principal
     * @param array<string> $aliases Dominios adicionales/subdominios
     * @param string|null $email Correo de contacto para avisos de caducidad
     * @return array{success: bool, error: ?string, certificate_id: ?int, message: ?string}
     */
    public function issueCertificate(string $domain, array $aliases = [], ?string $email = null): array
    {
        $domain = strtolower(trim($domain));
        $this->assertSafeDomain($domain);

        $cleanAliases = [];
        foreach ($aliases as $alias) {
            $alias = strtolower(trim($alias));
            if ($alias !== '' && $alias !== $domain && !in_array($alias, $cleanAliases, true)) {
                $this->assertSafeDomain($alias);
                $cleanAliases[] = $alias;
            }
        }

        if ($email !== null && $email !== '') {
            $email = trim($email);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException("Correo de contacto inválido: {$email}");
            }
        }

        // Flujo en dos pasos:
        // 1. Asegurar que el host exista y responda en HTTP (para responder al reto HTTP-01)
        $proxy = $this->proxyModel->where('domain', $domain)->first();
        if ($proxy && !empty($proxy['enabled'])) {
            // Desplegar host para garantizar el bloque acme-challenge activo
            $this->nginxManager->applyHost($proxy);
        }

        // 2. Invocar emisión vía ejecutor privilegiado
        $executor = $this->config->executorBinary;
        $aliasesArg = !empty($cleanAliases) ? implode(',', $cleanAliases) : '-';
        $emailArg = ($email !== null && $email !== '') ? $email : '-';

        $issuedSuccessfully = false;
        $errorOutput = null;

        if (is_file($executor)) {
            $cmd = ['sudo', $executor, 'issue-cert', $domain, $aliasesArg, $emailArg];

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = @proc_open($cmd, $descriptors, $pipes);
            if (is_resource($process)) {
                fclose($pipes[0]);
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $exitCode = proc_close($process);

                if ($exitCode === 0) {
                    $issuedSuccessfully = true;
                } else {
                    $errorOutput = trim($stdout . "\n" . $stderr);
                }
            }
        } elseif ($this->config->simulateInDev) {
            // Simulación en desarrollo/local: certificados de prueba con validez de 90 días
            $issuedSuccessfully = true;
        } else {
            return [
                'success'        => false,
                'error'          => 'Ejecutor privilegiado no encontrado en el sistema.',
                'certificate_id' => null,
                'message'        => null,
            ];
        }

        if (!$issuedSuccessfully) {
            AuditLogModel::log(
                'ssl_issue_failed',
                "Fallo al emitir certificado SSL para {$domain}: " . ($errorOutput ?? 'Error desconocido de Certbot.')
            );

            return [
                'success'        => false,
                'error'          => 'Certbot no pudo validar el dominio. Verifica que el DNS apunte a este servidor: ' . ($errorOutput ?? ''),
                'certificate_id' => null,
                'message'        => null,
            ];
        }

        // 3. Registrar o actualizar en la tabla certificates
        $certPath = "/etc/letsencrypt/live/{$domain}/fullchain.pem";
        $keyPath  = "/etc/letsencrypt/live/{$domain}/privkey.pem";
        $expiresAt = date('Y-m-d H:i:s', strtotime('+90 days'));
        $issuer    = "Let's Encrypt";

        // Si el archivo físico existe, leer metadatos reales de OpenSSL
        if (file_exists($certPath)) {
            $meta = $this->parseCertificateFile($certPath);
            if ($meta) {
                $expiresAt = $meta['expires_at'];
                $issuer    = $meta['issuer'];
            }
        }

        $existingCert = $this->certModel->where('domain', $domain)->first();
        $certData = [
            'domain'           => $domain,
            'certificate_path' => $certPath,
            'private_key_path' => $keyPath,
            'issuer'           => $issuer,
            'expires_at'       => $expiresAt,
            'auto_renew'       => 1,
            'status'           => 'valid',
        ];

        if ($existingCert) {
            $this->certModel->update($existingCert['id'], $certData);
            $certId = (int) $existingCert['id'];
        } else {
            $certId = (int) $this->certModel->insert($certData);
        }

        // 4. Si existe el proxy host, activar SSL y regenerar Nginx
        if ($proxy) {
            $this->proxyModel->update($proxy['id'], ['ssl_enabled' => 1]);
            $updatedProxy = $this->proxyModel->find($proxy['id']);
            $this->nginxManager->applyHost($updatedProxy);
        }

        AuditLogModel::log(
            'ssl_issue_success',
            "Certificado SSL emitido con éxito para {$domain} (Válido hasta {$expiresAt})."
        );

        return [
            'success'        => true,
            'error'          => null,
            'certificate_id' => $certId,
            'message'        => "Certificado SSL emitido correctamente para \"{$domain}\".",
        ];
    }

    /**
     * Renueva un certificado existente por su ID.
     */
    public function renewCertificate(int $certId): array
    {
        $cert = $this->certModel->find($certId);
        if (!$cert) {
            return ['success' => false, 'error' => 'Certificado no encontrado.'];
        }

        $domain = $cert['domain'];
        $executor = $this->config->executorBinary;

        $renewed = false;
        $errorOutput = null;

        if (is_file($executor)) {
            $cmd = ['sudo', $executor, 'issue-cert', $domain, '-', '-'];
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = @proc_open($cmd, $descriptors, $pipes);
            if (is_resource($process)) {
                fclose($pipes[0]);
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $exitCode = proc_close($process);

                if ($exitCode === 0) {
                    $renewed = true;
                } else {
                    $errorOutput = trim($stdout . "\n" . $stderr);
                }
            }
        } elseif ($this->config->simulateInDev) {
            $renewed = true;
        }

        if (!$renewed) {
            AuditLogModel::log('ssl_renew_failed', "Fallo al renovar certificado para {$domain}: " . ($errorOutput ?? ''));
            return ['success' => false, 'error' => 'Fallo al renovar: ' . ($errorOutput ?? 'Desconocido')];
        }

        $newExpiresAt = date('Y-m-d H:i:s', strtotime('+90 days'));
        if (file_exists($cert['certificate_path'])) {
            $meta = $this->parseCertificateFile($cert['certificate_path']);
            if ($meta) {
                $newExpiresAt = $meta['expires_at'];
            }
        }

        $this->certModel->update($certId, [
            'expires_at' => $newExpiresAt,
            'status'     => 'valid',
        ]);

        $this->nginxManager->reload();

        AuditLogModel::log('ssl_renew_success', "Certificado renovado para {$domain} (Nueva caducidad: {$newExpiresAt}).");

        return [
            'success' => true,
            'error'   => null,
            'message' => "Certificado para \"{$domain}\" renovado con éxito.",
        ];
    }

    /**
     * Proceso automático para renovar todos los certificados próximos a caducar (< 30 días).
     *
     * @return array{total_checked: int, renewed: array<string>, errors: array<string>}
     */
    public function renewAllExpiring(): array
    {
        $threshold = date('Y-m-d H:i:s', strtotime('+30 days'));
        $expiring = $this->certModel
            ->where('auto_renew', 1)
            ->where('expires_at <=', $threshold)
            ->findAll();

        $renewed = [];
        $errors  = [];

        foreach ($expiring as $cert) {
            $res = $this->renewCertificate((int) $cert['id']);
            if ($res['success']) {
                $renewed[] = $cert['domain'];
            } else {
                $errors[$cert['domain']] = $res['error'] ?? 'Error desconocido';
            }
        }

        return [
            'total_checked' => count($expiring),
            'renewed'       => $renewed,
            'errors'        => $errors,
        ];
    }

    /**
     * Elimina un certificado y actualiza el Proxy Host si estaba vinculado.
     */
    public function deleteCertificate(int $certId): bool
    {
        $cert = $this->certModel->find($certId);
        if (!$cert) {
            return false;
        }

        $domain = $cert['domain'];

        // Desactivar SSL en el proxy si corresponde
        $proxy = $this->proxyModel->where('domain', $domain)->first();
        if ($proxy) {
            $this->proxyModel->update($proxy['id'], [
                'ssl_enabled' => 0,
                'force_https' => 0,
            ]);
            $updatedProxy = $this->proxyModel->find($proxy['id']);
            $this->nginxManager->applyHost($updatedProxy);
        }

        $this->certModel->delete($certId);

        AuditLogModel::log('ssl_delete', "Eliminado certificado SSL para {$domain}.");
        return true;
    }

    /**
     * Lee metadatos reales de un certificado X.509 utilizando OpenSSL.
     */
    public function parseCertificateFile(string $filePath): ?array
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        if (!$content) {
            return null;
        }

        $parsed = openssl_x509_parse($content);
        if (!$parsed) {
            return null;
        }

        $validToTimestamp = (int) ($parsed['validTo_time_t'] ?? 0);
        $issuerName = $parsed['issuer']['O'] ?? ($parsed['issuer']['CN'] ?? "Let's Encrypt");

        return [
            'issuer'     => $issuerName,
            'expires_at' => date('Y-m-d H:i:s', $validToTimestamp),
            'subject'    => $parsed['subject']['CN'] ?? '',
        ];
    }

    /**
     * Sincroniza y recalcula el estado dinámico (valid, expiring_soon, expired).
     */
    public function syncCertificateStatuses(): void
    {
        $all = $this->certModel->findAll();
        $now = time();

        foreach ($all as $cert) {
            if (empty($cert['expires_at'])) {
                continue;
            }

            $expiresTs = strtotime($cert['expires_at']);
            $daysLeft  = round(($expiresTs - $now) / 86400);

            $newStatus = 'valid';
            if ($daysLeft <= 0) {
                $newStatus = 'expired';
            } elseif ($daysLeft <= 15) {
                $newStatus = 'expiring_soon';
            }

            if ($cert['status'] !== $newStatus) {
                $this->certModel->update($cert['id'], ['status' => $newStatus]);
            }
        }
    }

    /**
     * Valida nombres de dominio según RFC 1123 y previene inyección en CLI.
     */
    protected function assertSafeDomain(string $domain): void
    {
        if (!preg_match('/^(?!\-)(?:[a-zA-Z0-9\-]{1,63}\.)+[a-zA-Z]{2,63}$/', $domain)) {
            if ($domain !== 'localhost') {
                throw new InvalidArgumentException("Nombre de dominio inválido o inseguro: {$domain}");
            }
        }

        if (preg_match('/[;\r\n\s{}\"\'\`\$\<\>\&\!\?]/', $domain)) {
            throw new InvalidArgumentException("El dominio contiene caracteres prohibidos: {$domain}");
        }

        if (str_starts_with($domain, '-')) {
            throw new InvalidArgumentException("El dominio no puede comenzar con guion: {$domain}");
        }
    }
}

