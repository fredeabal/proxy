<?php

namespace App\Models;

use CodeIgniter\Model;

class ProxyUpstreamModel extends Model
{
    protected $table            = 'proxy_upstreams';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'proxy_host_id',
        'server_host',
        'server_port',
        'weight',
        'max_fails',
        'fail_timeout',
        'is_backup',
        'is_down',
    ];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'id'            => 'permit_empty|is_natural_no_zero',
        'proxy_host_id' => 'required|is_natural_no_zero',
        'server_host'   => 'required|min_length[1]|max_length[253]|valid_forward_host',
        'server_port'   => 'required|is_natural_no_zero|less_than_equal_to[65535]',
        'weight'        => 'permit_empty|is_natural_no_zero|less_than_equal_to[100]',
        'max_fails'     => 'permit_empty|is_natural|less_than_equal_to[100]',
        'fail_timeout'  => 'permit_empty|is_natural_no_zero|less_than_equal_to[3600]',
        'is_backup'     => 'permit_empty|in_list[0,1]',
        'is_down'       => 'permit_empty|in_list[0,1]',
    ];

    protected $validationMessages = [
        'server_host' => [
            'required'           => 'La dirección IP o host del servidor es obligatoria.',
            'valid_forward_host' => 'El host de destino no es válido o está restringido por seguridad.',
        ],
        'server_port' => [
            'required'           => 'El puerto del servidor es obligatorio.',
            'is_natural_no_zero' => 'El puerto debe ser un número entero mayor a 0.',
            'less_than_equal_to' => 'El puerto no puede ser superior a 65535.',
        ],
    ];

    /**
     * Obtiene todos los upstreams configurados para un Proxy Host específico.
     *
     * @param int $proxyHostId
     * @return array
     */
    public function getForProxy(int $proxyHostId): array
    {
        return $this->where('proxy_host_id', $proxyHostId)
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    /**
     * Sincroniza atómicamente la lista de upstreams de un Proxy Host.
     *
     * @param int $proxyHostId
     * @param array $servers
     * @return bool
     */
    public function syncForProxy(int $proxyHostId, array $servers): bool
    {
        $this->db->transStart();

        // Borrar upstreams previos del host
        $this->where('proxy_host_id', $proxyHostId)->delete();

        foreach ($servers as $srv) {
            $data = [
                'proxy_host_id' => $proxyHostId,
                'server_host'   => trim((string) ($srv['server_host'] ?? '')),
                'server_port'   => (int) ($srv['server_port'] ?? 80),
                'weight'        => isset($srv['weight']) && $srv['weight'] !== '' ? (int) $srv['weight'] : 1,
                'max_fails'     => isset($srv['max_fails']) && $srv['max_fails'] !== '' ? (int) $srv['max_fails'] : 3,
                'fail_timeout'  => isset($srv['fail_timeout']) && $srv['fail_timeout'] !== '' ? (int) $srv['fail_timeout'] : 10,
                'is_backup'     => !empty($srv['is_backup']) ? 1 : 0,
                'is_down'       => !empty($srv['is_down']) ? 1 : 0,
            ];

            if (!$this->insert($data)) {
                $this->db->transRollback();
                return false;
            }
        }

        $this->db->transComplete();

        return $this->db->transStatus();
    }
}

