<?php

namespace App\Models;

use CodeIgniter\Model;

class ProxyLocationModel extends Model
{
    protected $table            = 'proxy_locations';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'proxy_host_id',
        'path',
        'scheme',
        'forward_host',
        'forward_port',
        'websocket_enabled',
        'custom_headers',
        'sort_order',
    ];

    protected bool $allowEmptyInserts = false;

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'proxy_host_id'     => 'required|is_natural_no_zero',
        'path'              => 'required|max_length[255]',
        'scheme'            => 'required|in_list[http,https]',
        'forward_host'      => 'required|min_length[1]|max_length[253]|valid_forward_host',
        'forward_port'      => 'required|is_natural_no_zero|less_than_equal_to[65535]',
        'websocket_enabled' => 'permit_empty|in_list[0,1]',
        'custom_headers'    => 'permit_empty|valid_custom_headers',
        'sort_order'        => 'permit_empty|is_natural',
    ];

    /**
     * Devuelve todas las rutas personalizadas de un Proxy Host ordenadas.
     *
     * @return list<array>
     */
    public function getForProxy(int $proxyHostId): array
    {
        return $this->where('proxy_host_id', $proxyHostId)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    /**
     * Reemplaza todas las rutas personalizadas de un Proxy Host.
     *
     * @param list<array> $locations
     */
    public function replaceForProxy(int $proxyHostId, array $locations): void
    {
        $this->where('proxy_host_id', $proxyHostId)->delete();

        $order = 0;
        foreach ($locations as $loc) {
            $this->insert([
                'proxy_host_id'     => $proxyHostId,
                'path'              => trim((string) ($loc['path'] ?? '')),
                'scheme'            => strtolower(trim((string) ($loc['scheme'] ?? 'http'))),
                'forward_host'      => trim((string) ($loc['forward_host'] ?? '')),
                'forward_port'      => (int) ($loc['forward_port'] ?? 80),
                'websocket_enabled' => !empty($loc['websocket_enabled']) ? 1 : 0,
                'custom_headers'    => !empty($loc['custom_headers']) ? trim((string) $loc['custom_headers']) : null,
                'sort_order'        => $order++,
            ]);
        }
    }
}
