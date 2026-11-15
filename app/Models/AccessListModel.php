<?php

namespace App\Models;

use CodeIgniter\Model;

class AccessListModel extends Model
{
    protected $table            = 'access_lists';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'name',
        'satisfy_any',
        'pass_auth',
    ];

    protected bool $allowEmptyInserts = false;

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'id'          => 'permit_empty|is_natural_no_zero',
        'name'        => 'required|min_length[2]|max_length[100]',
        'satisfy_any' => 'permit_empty|in_list[0,1]',
        'pass_auth'   => 'permit_empty|in_list[0,1]',
    ];

    protected $validationMessages = [
        'name' => [
            'required'   => 'El nombre de la lista de acceso es obligatorio.',
            'min_length' => 'El nombre debe tener al menos 2 caracteres.',
            'max_length' => 'El nombre no puede superar los 100 caracteres.',
        ],
    ];

    /**
     * Obtiene las reglas de IP/CIDR de una lista de acceso.
     *
     * @return list<array>
     */
    public function getClients(int $accessListId): array
    {
        return $this->db->table('access_list_clients')
            ->where('access_list_id', $accessListId)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Obtiene los usuarios de autenticación básica de una lista de acceso.
     *
     * @return list<array>
     */
    public function getAuthUsers(int $accessListId): array
    {
        return $this->db->table('access_list_auth')
            ->where('access_list_id', $accessListId)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Obtiene una lista de acceso completa con sus clientes IP y usuarios auth.
     */
    public function getWithRelations(int $accessListId): ?array
    {
        $list = $this->find($accessListId);
        if (!$list) {
            return null;
        }

        $list['clients'] = $this->getClients($accessListId);
        $list['users']   = $this->getAuthUsers($accessListId);

        return $list;
    }

    /**
     * Reemplaza las reglas de IP/CIDR de una lista de acceso.
     *
     * @param list<array{directive: string, address: string}> $clients
     */
    public function replaceClients(int $accessListId, array $clients): void
    {
        $table = $this->db->table('access_list_clients');
        $table->where('access_list_id', $accessListId)->delete();

        $now = date('Y-m-d H:i:s');
        foreach ($clients as $c) {
            $table->insert([
                'access_list_id' => $accessListId,
                'directive'      => $c['directive'],
                'address'        => $c['address'],
                'created_at'     => $now,
            ]);
        }
    }

    /**
     * Reemplaza los usuarios de autenticación básica de una lista de acceso.
     *
     * @param list<array{username: string, password_hash: string}> $users
     */
    public function replaceAuthUsers(int $accessListId, array $users): void
    {
        $table = $this->db->table('access_list_auth');
        $table->where('access_list_id', $accessListId)->delete();

        $now = date('Y-m-d H:i:s');
        foreach ($users as $u) {
            $table->insert([
                'access_list_id' => $accessListId,
                'username'       => $u['username'],
                'password_hash'  => $u['password_hash'],
                'created_at'     => $now,
            ]);
        }
    }

    /**
     * Genera el hash compatible con Nginx (.htpasswd {SHA}) para una contraseña en texto plano.
     */
    public static function hashHtpasswd(string $plainPassword): string
    {
        return '{SHA}' . base64_encode(sha1($plainPassword, true));
    }

    /**
     * Genera el contenido completo del archivo .htpasswd para una lista de acceso.
     */
    public function buildHtpasswdContent(int $accessListId): string
    {
        $users = $this->getAuthUsers($accessListId);
        $lines = [];
        foreach ($users as $u) {
            $lines[] = $u['username'] . ':' . $u['password_hash'];
        }
        return !empty($lines) ? implode("\n", $lines) . "\n" : '';
    }
}
