<?php

namespace App\Controllers;

use App\Models\AuditLogModel;
use App\Models\CertificateModel;
use App\Models\ProxyHostModel;
use App\Services\SSLManager;
use CodeIgniter\HTTP\ResponseInterface;

class CertificateController extends BaseController
{
    protected CertificateModel $certModel;
    protected ProxyHostModel $proxyModel;
    protected SSLManager $sslManager;

    public function __construct()
    {
        $this->certModel  = new CertificateModel();
        $this->proxyModel = new ProxyHostModel();
        $this->sslManager = new SSLManager(null, $this->certModel, $this->proxyModel);
    }

    /**
     * Listado principal de certificados SSL con métricas y búsqueda en tiempo real.
     */
    public function index(): string
    {
        $this->sslManager->syncCertificateStatuses();

        $search = trim((string) $this->request->getGet('q'));
        $filterStatus = $this->request->getGet('status');

        $builder = $this->certModel;
        if ($search !== '') {
            $builder = $builder->like('domain', $search);
        }

        if ($filterStatus === 'valid') {
            $builder = $builder->where('status', 'valid');
        } elseif ($filterStatus === 'expiring_soon') {
            $builder = $builder->where('status', 'expiring_soon');
        } elseif ($filterStatus === 'expired') {
            $builder = $builder->where('status', 'expired');
        }

        $certificates = $builder->orderBy('expires_at', 'ASC')->findAll();

        // Métricas
        $totalCerts   = $this->certModel->countAllResults(false);
        $validCerts   = $this->certModel->where('status', 'valid')->countAllResults(false);
        $expiringSoon = $this->certModel->where('status', 'expiring_soon')->countAllResults(false);
        $expiredCerts = $this->certModel->where('status', 'expired')->countAllResults(false);

        $data = [
            'title'            => 'Certificados SSL',
            'activeMenu'       => 'certificates',
            'certificates'     => $certificates,
            'search'           => $search,
            'filterStatus'     => $filterStatus,
            'totalCerts'       => $totalCerts,
            'validCerts'       => $validCerts,
            'expiringSoon'     => $expiringSoon,
            'expiredCerts'     => $expiredCerts,
        ];

        return view('template/header', $data)
            . view('certificates/index', $data)
            . view('template/footer');
    }

    /**
     * Renueva un certificado existente.
     */
    public function renew(int $id): ResponseInterface
    {
        $result = $this->sslManager->renewCertificate($id);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON($result);
        }

        if (!$result['success']) {
            return redirect()->to(base_url('certificates'))
                ->with('error', $result['error'] ?? 'Error al renovar certificado.');
        }

        return redirect()->to(base_url('certificates'))
            ->with('success', $result['message'] ?? 'Certificado renovado con éxito.');
    }

    /**
     * Ejecuta la renovación periódica de todos los certificados próximos a expirar.
     */
    public function renewAll(): ResponseInterface
    {
        $res = $this->sslManager->renewAllExpiring();

        $count = count($res['renewed']);
        $msg = "Se procesaron {$res['total_checked']} certificados. Renovados con éxito: {$count}.";

        if (!empty($res['errors'])) {
            $msg .= ' Errores detectados en: ' . implode(', ', array_keys($res['errors']));
        }

        return redirect()->to(base_url('certificates'))->with('success', $msg);
    }

    /**
     * Elimina un certificado SSL registrado.
     */
    public function delete(int $id): ResponseInterface
    {
        $deleted = $this->sslManager->deleteCertificate($id);

        if (!$deleted) {
            return redirect()->to(base_url('certificates'))
                ->with('error', 'El certificado no existe o no pudo eliminarse.');
        }

        return redirect()->to(base_url('certificates'))
            ->with('success', 'Certificado SSL eliminado correctamente.');
    }
}

