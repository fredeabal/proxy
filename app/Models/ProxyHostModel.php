<?php

namespace App\Models;

use CodeIgniter\Model;

class ProxyHostModel extends Model
{
    protected $table            = 'proxy_hosts';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'domain',
        'domain_names',
        'scheme',
        'forward_host',
        'forward_port',
        'ssl_enabled',
        'websocket_enabled',
        'force_https',
        'enabled',
        'custom_headers',
        'certificate_id',
        'proxy_connect_timeout',
        'proxy_read_timeout',
        'proxy_send_timeout',
        'client_max_body_size',
        'proxy_ssl_verify',
        'hsts_enabled',
        'custom_vhost',
        'load_balancing_enabled',
        'lb_algorithm',
    ];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'id'                    => 'permit_empty|is_natural_no_zero',
        'domain'                => 'required|min_length[3]|max_length[253]|valid_rfc1123_domain|is_unique[proxy_hosts.domain,id,{id}]',
        'domain_names'          => 'permit_empty|valid_domain_aliases',
        'scheme'                => 'required|in_list[http,https]',
        'forward_host'          => 'required|min_length[1]|max_length[253]|valid_forward_host',
        'forward_port'          => 'required|is_natural_no_zero|less_than_equal_to[65535]',
        'custom_headers'        => 'permit_empty|valid_custom_headers',
        'proxy_connect_timeout' => 'permit_empty|is_natural_no_zero|less_than_equal_to[3600]',
        'proxy_read_timeout'    => 'permit_empty|is_natural_no_zero|less_than_equal_to[3600]',
        'proxy_send_timeout'    => 'permit_empty|is_natural_no_zero|less_than_equal_to[3600]',
        'client_max_body_size'  => 'permit_empty|is_natural|less_than_equal_to[10240]',
        'proxy_ssl_verify'      => 'permit_empty|in_list[0,1]',
        'hsts_enabled'          => 'permit_empty|in_list[0,1]',
        'load_balancing_enabled' => 'permit_empty|in_list[0,1]',
        'lb_algorithm'          => 'permit_empty|in_list[round_robin,least_conn,ip_hash]',
    ];

    protected $validationMessages = [
        'domain' => [
            'required'               => 'El dominio principal es obligatorio.',
            'is_unique'              => 'Este dominio ya está configurado en otro Proxy Host.',
            'valid_rfc1123_domain'   => 'El dominio no cumple con el estándar RFC 1123 o contiene caracteres no válidos.',
        ],
        'domain_names' => [
            'valid_domain_aliases'   => 'Uno o más aliases contienen errores o están duplicados.',
        ],
        'scheme' => [
            'required' => 'El protocolo es obligatorio.',
            'in_list'  => 'El protocolo debe ser http o https.',
        ],
        'forward_host' => [
            'required'           => 'La dirección o IP de destino es obligatoria.',
            'valid_forward_host' => 'El host de destino no es válido o está restringido por seguridad.',
        ],
        'forward_port' => [
            'required'            => 'El puerto de destino es obligatorio.',
            'is_natural_no_zero'  => 'El puerto debe ser un número entero mayor a 0.',
            'less_than_equal_to'  => 'El puerto no puede ser superior a 65535.',
        ],
        'custom_headers' => [
            'valid_custom_headers' => 'Una o más cabeceras personalizadas no están en la lista blanca o contienen caracteres prohibidos.',
        ],
        'proxy_connect_timeout' => [
            'is_natural_no_zero' => 'El timeout de conexión debe ser de al menos 1 segundo.',
            'less_than_equal_to' => 'El timeout de conexión no puede superar los 3600 segundos.',
        ],
        'proxy_read_timeout' => [
            'is_natural_no_zero' => 'El timeout de lectura debe ser de al menos 1 segundo.',
            'less_than_equal_to' => 'El timeout de lectura no puede superar los 3600 segundos.',
        ],
        'proxy_send_timeout' => [
            'is_natural_no_zero' => 'El timeout de envío debe ser de al menos 1 segundo.',
            'less_than_equal_to' => 'El timeout de envío no puede superar los 3600 segundos.',
        ],
        'client_max_body_size' => [
            'is_natural'         => 'El tamaño máximo de subida debe ser un número entero mayor o igual a 0.',
            'less_than_equal_to' => 'El tamaño máximo de subida no puede superar 10240 MB (10 GB).',
        ],
    ];

    /**
     * Devuelve una lista limpia de aliases como array de strings.
     */
    public function getAliasesList(array $proxyHost): array
    {
        if (empty($proxyHost['domain_names'])) {
            return [];
        }

        $list = preg_split('/[\s,]+/', trim($proxyHost['domain_names']), -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_unique(array_map('trim', $list)));
    }

    /**
     * Comprueba si el destino apunta a localhost o interfaz de loopback.
     */
    public function isLoopbackTarget(string $host): bool
    {
        $h = strtolower(trim($host));
        return in_array($h, ['localhost', '127.0.0.1', '::1', '0.0.0.0', '127.0.1.1'], true) 
            || str_starts_with($h, '127.');
    }

    /**
     * Alterna el estado activo/inactivo de un Proxy Host.
     */
    public function toggleStatus(int $id): ?bool
    {
        $host = $this->find($id);
        if (!$host) {
            return null;
        }

        $newStatus = $host['enabled'] ? 0 : 1;
        $this->update($id, ['enabled' => $newStatus]);
        return (bool) $newStatus;
    }
}
