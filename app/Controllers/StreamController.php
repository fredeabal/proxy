<?php

namespace App\Controllers;

use App\Models\AuditLogModel;
use App\Models\StreamModel;
use App\Services\NginxConfigGenerator;
use App\Services\NginxManager;
use CodeIgniter\HTTP\ResponseInterface;

class StreamController extends BaseController
{
    protected StreamModel $streamModel;
    protected NginxManager $nginxManager;

    public function __construct()
    {
        $this->streamModel  = new StreamModel();
        $this->nginxManager = new NginxManager();
    }

    public function index(): string
    {
        $streams = $this->streamModel->orderBy('incoming_port', 'ASC')->findAll();

        $data = [
            'title'      => 'Streams TCP/UDP',
            'activeMenu' => 'streams',
            'streams'    => $streams,
        ];

        return view('template/header', $data)
            . view('streams/index', $data)
            . view('template/footer');
    }

    public function new(): string
    {
        $data = [
            'title'      => 'Nuevo Stream TCP/UDP',
            'activeMenu' => 'streams',
            'stream'     => [
                'id'            => null,
                'incoming_port' => '',
                'forward_host'  => '',
                'forward_port'  => '',
                'protocol'      => 'tcp',
                'enabled'       => 1,
                'description'   => '',
            ],
            'isEdit' => false,
        ];

        return view('template/header', $data)
            . view('streams/form', $data)
            . view('template/footer');
    }

    public function create(): ResponseInterface
    {
        $data = [
            'incoming_port' => (int) $this->request->getPost('incoming_port'),
            'forward_host'  => trim((string) $this->request->getPost('forward_host')),
            'forward_port'  => (int) $this->request->getPost('forward_port'),
            'protocol'      => strtolower(trim((string) ($this->request->getPost('protocol') ?: 'tcp'))),
            'enabled'       => $this->request->getPost('enabled') !== null ? ($this->request->getPost('enabled') ? 1 : 0) : 1,
            'description'   => trim((string) $this->request->getPost('description')) ?: null,
        ];

        if (!$this->streamModel->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->streamModel->errors());
        }

        $newId = (int) $this->streamModel->getInsertID();
        $data['id'] = $newId;

        $nginxResult = $this->nginxManager->applyStream($data);
        if (!$nginxResult['success']) {
            $this->streamModel->delete($newId);
            return redirect()->back()->withInput()
                ->with('error', 'Error al aplicar el stream en Nginx: ' . ($nginxResult['error'] ?? 'Desconocido'));
        }

        AuditLogModel::log(
            'stream_create',
            "Creado Stream [ID: {$newId}] :{$data['incoming_port']} ({$data['protocol']}) -> {$data['forward_host']}:{$data['forward_port']}"
        );

        return redirect()->to(base_url('streams'))
            ->with('success', "Stream en puerto {$data['incoming_port']} creado correctamente.");
    }

    public function edit(int $id): string|ResponseInterface
    {
        $stream = $this->streamModel->find($id);
        if (!$stream) {
            return redirect()->to(base_url('streams'))
                ->with('error', 'El Stream solicitado no existe.');
        }

        $generator = new NginxConfigGenerator();
        $preview   = $generator->generateStream($stream);

        $data = [
            'title'      => 'Editar Stream TCP/UDP',
            'activeMenu' => 'streams',
            'stream'     => $stream,
            'preview'    => $preview,
            'isEdit'     => true,
        ];

        return view('template/header', $data)
            . view('streams/form', $data)
            . view('template/footer');
    }

    public function update(int $id): ResponseInterface
    {
        $existing = $this->streamModel->find($id);
        if (!$existing) {
            return redirect()->to(base_url('streams'))
                ->with('error', 'El Stream solicitado no existe.');
        }

        $data = [
            'id'            => $id,
            'incoming_port' => (int) $this->request->getPost('incoming_port'),
            'forward_host'  => trim((string) $this->request->getPost('forward_host')),
            'forward_port'  => (int) $this->request->getPost('forward_port'),
            'protocol'      => strtolower(trim((string) ($this->request->getPost('protocol') ?: 'tcp'))),
            'enabled'       => $this->request->getPost('enabled') ? 1 : 0,
            'description'   => trim((string) $this->request->getPost('description')) ?: null,
        ];

        $this->streamModel->setValidationRule(
            'incoming_port',
            "required|is_natural_no_zero|less_than_equal_to[65535]|is_unique[streams.incoming_port,id,{$id}]"
        );

        if (!$this->streamModel->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->streamModel->errors());
        }

        $nginxResult = $this->nginxManager->applyStream($data);
        if (!$nginxResult['success']) {
            $this->streamModel->update($id, $existing);
            $this->nginxManager->applyStream($existing);
            return redirect()->back()->withInput()
                ->with('error', 'Error al aplicar el stream en Nginx: ' . ($nginxResult['error'] ?? 'Desconocido'));
        }

        AuditLogModel::log(
            'stream_update',
            "Actualizado Stream [ID: {$id}] :{$data['incoming_port']} ({$data['protocol']}) -> {$data['forward_host']}:{$data['forward_port']}"
        );

        return redirect()->to(base_url('streams'))
            ->with('success', "Stream en puerto {$data['incoming_port']} actualizado correctamente.");
    }

    public function toggle(int $id): ResponseInterface
    {
        $stream = $this->streamModel->find($id);
        if (!$stream) {
            return redirect()->to(base_url('streams'))->with('error', 'Stream no encontrado.');
        }

        $newState = $this->streamModel->toggleStatus($id);
        $stream['enabled'] = $newState ? 1 : 0;
        $this->nginxManager->applyStream($stream);

        AuditLogModel::log('stream_toggle', "Stream [ID: {$id}] :{$stream['incoming_port']} " . ($newState ? 'habilitado' : 'deshabilitado'));

        return redirect()->to(base_url('streams'))
            ->with('success', 'Estado del Stream actualizado.');
    }

    public function delete(int $id): ResponseInterface
    {
        $stream = $this->streamModel->find($id);
        if (!$stream) {
            return redirect()->to(base_url('streams'))->with('error', 'Stream no encontrado.');
        }

        $this->nginxManager->removeStream($id);
        $this->streamModel->delete($id);

        AuditLogModel::log('stream_delete', "Eliminado Stream [ID: {$id}] :{$stream['incoming_port']}");

        return redirect()->to(base_url('streams'))
            ->with('success', "Stream en puerto {$stream['incoming_port']} eliminado.");
    }
}
