<?php

namespace App\Controllers;

use App\Models\AuditLogModel;
use App\Services\BackupManager;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;

class BackupController extends BaseController
{
    protected BackupManager $backupManager;

    public function __construct()
    {
        $this->backupManager = new BackupManager();
    }

    // ---------------------------------------------------------------------
    // Vista principal de Copias de Seguridad (Backups)
    // ---------------------------------------------------------------------
    public function index(): string
    {
        $backups        = $this->backupManager->listBackups();
        $retentionCount = $this->backupManager->getRetentionCount();
        $totalSize      = array_sum(array_column($backups, 'size'));

        $data = [
            'title'          => 'Copias de Seguridad',
            'activeMenu'     => 'backups',
            'backups'        => $backups,
            'retentionCount' => $retentionCount,
            'totalBackups'   => count($backups),
            'totalSizeHuman' => $this->backupManager->formatBytes((int) $totalSize),
            'extraScripts'   => ['assets/js/backups-manager.js'],
        ];

        return view('template/header', $data)
            . view('backups/index', $data)
            . view('template/footer', $data);
    }

    // ---------------------------------------------------------------------
    // Actualiza la política de retención de backups
    // ---------------------------------------------------------------------
    public function updateRetention(): ResponseInterface
    {
        $rules = [
            'backup_retention_count' => 'required|is_natural_no_zero|less_than_equal_to[100]',
        ];

        $messages = [
            'backup_retention_count' => [
                'required'           => 'El número de copias a retener es obligatorio.',
                'is_natural_no_zero' => 'La retención debe ser de al menos 1 copia de seguridad.',
                'less_than_equal_to' => 'La retención máxima permitida es de 100 copias locales.',
            ],
        ];

        if (!$this->validate($rules, $messages)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $retention = (int) $this->request->getPost('backup_retention_count');
        $this->backupManager->setRetentionCount($retention);

        AuditLogModel::log(
            'backup_retention_update',
            "Actualizada retención de copias de seguridad: {$retention} copias"
        );

        return redirect()->to(base_url('backups'))
            ->with('success', 'Política de retención actualizada y aplicada correctamente.');
    }

    // ---------------------------------------------------------------------
    // Genera una nueva copia de seguridad atómica
    // ---------------------------------------------------------------------
    public function create(): ResponseInterface
    {
        $result = $this->backupManager->createBackup();

        if (!$result['success']) {
            return redirect()->to(base_url('backups'))
                ->with('error', 'No se pudo generar la copia de seguridad: ' . ($result['error'] ?? 'Error desconocido.'));
        }

        return redirect()->to(base_url('backups'))
            ->with('success', "Copia de seguridad \"{$result['filename']}\" creada correctamente.");
    }

    // ---------------------------------------------------------------------
    // Restaura una copia de seguridad previa validación de integridad
    // ---------------------------------------------------------------------
    public function restore(): ResponseInterface
    {
        $filename = trim((string) $this->request->getPost('filename'));
        if ($filename === '') {
            return redirect()->to(base_url('backups'))
                ->with('error', 'No se especificó el archivo de copia de seguridad a restaurar.');
        }

        $result = $this->backupManager->restoreBackup($filename);

        if (!$result['success']) {
            return redirect()->to(base_url('backups'))
                ->with('error', $result['error'] ?? 'Error al restaurar la copia de seguridad.');
        }

        return redirect()->to(base_url('backups'))
            ->with('success', $result['message'] ?? 'Copia de seguridad restaurada con éxito.');
    }

    // ---------------------------------------------------------------------
    // Elimina una copia de seguridad local
    // ---------------------------------------------------------------------
    public function delete(): ResponseInterface
    {
        $filename = trim((string) $this->request->getPost('filename'));
        if ($filename === '') {
            return redirect()->to(base_url('backups'))
                ->with('error', 'Nombre de archivo no especificado.');
        }

        try {
            if (!$this->backupManager->deleteBackup($filename)) {
                return redirect()->to(base_url('backups'))
                    ->with('error', 'La copia de seguridad solicitada no existe.');
            }
        } catch (InvalidArgumentException $e) {
            return redirect()->to(base_url('backups'))
                ->with('error', $e->getMessage());
        }

        return redirect()->to(base_url('backups'))
            ->with('success', "Copia de seguridad \"{$filename}\" eliminada.");
    }

    // ---------------------------------------------------------------------
    // Descarga segura de un archivo de backup
    // ---------------------------------------------------------------------
    public function download(string $filename): ResponseInterface
    {
        try {
            $safePath = $this->backupManager->resolveSafeBackupPath($filename);
            if (!is_file($safePath)) {
                return redirect()->to(base_url('backups'))
                    ->with('error', 'El archivo de copia de seguridad no existe.');
            }

            AuditLogModel::log('backup_download', "Descargada copia de seguridad: {$filename}");

            return $this->response->download($safePath, null);
        } catch (InvalidArgumentException $e) {
            return redirect()->to(base_url('backups'))
                ->with('error', $e->getMessage());
        }
    }
}

