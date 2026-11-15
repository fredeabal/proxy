<?php

namespace App\Controllers;

use App\Models\AccessListModel;
use App\Models\AuditLogModel;
use App\Models\ProxyHostModel;
use App\Services\NginxManager;
use App\Validation\ProxyHostRules;
use CodeIgniter\HTTP\ResponseInterface;

class AccessListController extends BaseController
{
    protected AccessListModel $accessListModel;
    protected ProxyHostModel $proxyModel;
    protected NginxManager $nginxManager;

    public function __construct()
    {
        $this->accessListModel = new AccessListModel();
        $this->proxyModel      = new ProxyHostModel();
        $this->nginxManager    = new NginxManager();
    }

    public function index(): string
    {
        $lists = $this->accessListModel->orderBy('id', 'DESC')->findAll();
        foreach ($lists as &$list) {
            $id = (int) $list['id'];
            $list['clients']    = $this->accessListModel->getClients($id);
            $list['users']      = $this->accessListModel->getAuthUsers($id);
            $list['host_count'] = $this->proxyModel->where('access_list_id', $id)->countAllResults();
        }
        unset($list);

        $data = [
            'title'       => 'Access Lists',
            'activeMenu'  => 'access-lists',
            'accessLists' => $lists,
        ];

        return view('template/header', $data)
            . view('access-lists/index', $data)
            . view('template/footer');
    }

    public function new(): string
    {
        $data = [
            'title'      => 'Nueva Access List',
            'activeMenu' => 'access-lists',
            'accessList' => [
                'id'          => null,
                'name'        => '',
                'satisfy_any' => 0,
                'pass_auth'   => 0,
                'clients'     => [],
                'users'       => [],
            ],
            'isEdit' => false,
        ];

        return view('template/header', $data)
            . view('access-lists/form', $data)
            . view('template/footer');
    }

    public function create(): ResponseInterface
    {
        $name       = trim((string) $this->request->getPost('name'));
        $satisfyAny = $this->request->getPost('satisfy_any') ? 1 : 0;
        $passAuth   = $this->request->getPost('pass_auth') ? 1 : 0;

        $clientsResult = $this->parseClientsFromRequest();
        if ($clientsResult['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $clientsResult['error']);
        }

        $usersResult = $this->parseUsersFromRequest();
        if ($usersResult['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $usersResult['error']);
        }

        if (!$this->accessListModel->insert([
            'name'        => $name,
            'satisfy_any' => $satisfyAny,
            'pass_auth'   => $passAuth,
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->accessListModel->errors());
        }

        $newId = (int) $this->accessListModel->getInsertID();
        $this->accessListModel->replaceClients($newId, $clientsResult['clients']);
        $this->accessListModel->replaceAuthUsers($newId, $usersResult['users']);

        $htpasswd = $this->accessListModel->buildHtpasswdContent($newId);
        $this->nginxManager->syncAccessList($newId, $htpasswd);

        AuditLogModel::log('access_list_create', "Creada Access List [ID: {$newId}] {$name}");

        return redirect()->to(base_url('access-lists'))
            ->with('success', "Access List \"{$name}\" creada correctamente.");
    }

    public function edit(int $id): string|ResponseInterface
    {
        $accessList = $this->accessListModel->getWithRelations($id);
        if (!$accessList) {
            return redirect()->to(base_url('access-lists'))
                ->with('error', 'La Access List solicitada no existe.');
        }

        $data = [
            'title'      => 'Editar Access List',
            'activeMenu' => 'access-lists',
            'accessList' => $accessList,
            'isEdit'     => true,
        ];

        return view('template/header', $data)
            . view('access-lists/form', $data)
            . view('template/footer');
    }

    public function update(int $id): ResponseInterface
    {
        $existing = $this->accessListModel->getWithRelations($id);
        if (!$existing) {
            return redirect()->to(base_url('access-lists'))
                ->with('error', 'La Access List solicitada no existe.');
        }

        $name       = trim((string) $this->request->getPost('name'));
        $satisfyAny = $this->request->getPost('satisfy_any') ? 1 : 0;
        $passAuth   = $this->request->getPost('pass_auth') ? 1 : 0;

        $clientsResult = $this->parseClientsFromRequest();
        if ($clientsResult['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $clientsResult['error']);
        }

        $existingHashes = [];
        foreach ($existing['users'] as $u) {
            $existingHashes[$u['username']] = $u['password_hash'];
        }

        $usersResult = $this->parseUsersFromRequest($existingHashes);
        if ($usersResult['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $usersResult['error']);
        }

        if (!$this->accessListModel->update($id, [
            'name'        => $name,
            'satisfy_any' => $satisfyAny,
            'pass_auth'   => $passAuth,
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->accessListModel->errors());
        }

        $this->accessListModel->replaceClients($id, $clientsResult['clients']);
        $this->accessListModel->replaceAuthUsers($id, $usersResult['users']);

        $htpasswd = $this->accessListModel->buildHtpasswdContent($id);
        $this->nginxManager->syncAccessList($id, $htpasswd);

        // Re-desplegar Proxy Hosts que usan esta Access List
        $linkedHosts = $this->proxyModel->where('access_list_id', $id)->findAll();
        foreach ($linkedHosts as $host) {
            $this->nginxManager->applyHost($host);
        }

        AuditLogModel::log('access_list_update', "Actualizada Access List [ID: {$id}] {$name}");

        return redirect()->to(base_url('access-lists'))
            ->with('success', "Access List \"{$name}\" actualizada correctamente.");
    }

    public function delete(int $id): ResponseInterface
    {
        $existing = $this->accessListModel->find($id);
        if (!$existing) {
            return redirect()->to(base_url('access-lists'))
                ->with('error', 'La Access List solicitada no existe.');
        }

        // Desvincular hosts asociados antes de eliminar
        $linkedHosts = $this->proxyModel->where('access_list_id', $id)->findAll();
        if (!empty($linkedHosts)) {
            $this->proxyModel->where('access_list_id', $id)->set(['access_list_id' => null])->update();
            foreach ($linkedHosts as $host) {
                $host['access_list_id'] = null;
                $this->nginxManager->applyHost($host);
            }
        }

        $this->accessListModel->delete($id);
        $this->nginxManager->deleteAccessList($id);

        AuditLogModel::log('access_list_delete', "Eliminada Access List [ID: {$id}] {$existing['name']}");

        return redirect()->to(base_url('access-lists'))
            ->with('success', "Access List \"{$existing['name']}\" eliminada.");
    }

    /**
     * @return array{error: ?string, clients: list<array{directive: string, address: string}>}
     */
    protected function parseClientsFromRequest(): array
    {
        $rawClients = $this->request->getPost('clients');
        if (!is_array($rawClients) || empty($rawClients)) {
            return ['error' => null, 'clients' => []];
        }

        $clients = [];
        foreach ($rawClients as $idx => $c) {
            $directive = strtolower(trim((string) ($c['directive'] ?? 'allow')));
            $address   = trim((string) ($c['address'] ?? ''));

            if ($address === '') {
                continue;
            }

            if (!in_array($directive, ['allow', 'deny'], true)) {
                return ['error' => 'Regla IP #' . ($idx + 1) . ': Directiva inválida.', 'clients' => []];
            }

            $err = null;
            if (!ProxyHostRules::validCidrOrIp($address, $err)) {
                return ['error' => 'Regla IP #' . ($idx + 1) . ": {$err}", 'clients' => []];
            }

            $clients[] = [
                'directive' => $directive,
                'address'   => $address,
            ];
        }

        return ['error' => null, 'clients' => $clients];
    }

    /**
     * @param array<string, string> $existingHashes
     * @return array{error: ?string, users: list<array{username: string, password_hash: string}>}
     */
    protected function parseUsersFromRequest(array $existingHashes = []): array
    {
        $rawUsers = $this->request->getPost('auth_users');
        if (!is_array($rawUsers) || empty($rawUsers)) {
            return ['error' => null, 'users' => []];
        }

        $users = [];
        $seen  = [];
        foreach ($rawUsers as $idx => $u) {
            $username = trim((string) ($u['username'] ?? ''));
            $password = (string) ($u['password'] ?? '');

            if ($username === '' && $password === '') {
                continue;
            }

            if (!preg_match('/^[a-zA-Z0-9._\-]{1,64}$/', $username)) {
                return [
                    'error' => 'Usuario #' . ($idx + 1) . ': El nombre de usuario solo puede contener letras, números, puntos, guiones y guiones bajos.',
                    'users' => [],
                ];
            }

            if (isset($seen[$username])) {
                return ['error' => "El usuario \"{$username}\" está duplicado.", 'users' => []];
            }
            $seen[$username] = true;

            if ($password !== '') {
                $hash = AccessListModel::hashHtpasswd($password);
            } elseif (isset($existingHashes[$username])) {
                $hash = $existingHashes[$username];
            } else {
                return [
                    'error' => 'Usuario #' . ($idx + 1) . " ({$username}): La contraseña es obligatoria.",
                    'users' => [],
                ];
            }

            $users[] = [
                'username'      => $username,
                'password_hash' => $hash,
            ];
        }

        return ['error' => null, 'users' => $users];
    }
}
