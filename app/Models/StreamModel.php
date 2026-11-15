<?php

namespace App\Models;

use CodeIgniter\Model;

class StreamModel extends Model
{
    protected $table            = 'streams';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'incoming_port',
        'forward_host',
        'forward_port',
        'protocol',
        'enabled',
        'description',
    ];

    protected bool $allowEmptyInserts = false;

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'id'            => 'permit_empty|is_natural_no_zero',
        'incoming_port' => 'required|is_natural_no_zero|less_than_equal_to[65535]|is_unique[streams.incoming_port,id,{id}]',
        'forward_host'  => 'required|min_length[1]|max_length[253]|valid_forward_host',
        'forward_port'  => 'required|is_natural_no_zero|less_than_equal_to[65535]',
        'protocol'      => 'required|in_list[tcp,udp,tcp_udp]',
        'enabled'       => 'permit_empty|in_list[0,1]',
        'description'   => 'permit_empty|max_length[255]',
    ];

    protected $validationMessages = [
        'incoming_port' => [
            'required'           => 'El puerto de entrada es obligatorio.',
            'is_natural_no_zero' => 'El puerto debe ser mayor a 0.',
            'less_than_equal_to' => 'El puerto no puede superar 65535.',
            'is_unique'          => 'Ya existe un stream escuchando en este puerto.',
        ],
        'forward_host' => [
            'required'           => 'El host de destino es obligatorio.',
            'valid_forward_host' => 'El host de destino no es válido o está restringido por seguridad.',
        ],
        'forward_port' => [
            'required'           => 'El puerto de destino es obligatorio.',
            'is_natural_no_zero' => 'El puerto de destino debe ser mayor a 0.',
            'less_than_equal_to' => 'El puerto de destino no puede superar 65535.',
        ],
        'protocol' => [
            'required' => 'El protocolo es obligatorio.',
            'in_list'  => 'El protocolo debe ser tcp, udp o tcp_udp.',
        ],
    ];

    /**
     * Alterna el estado activo/inactivo de un Stream.
     */
    public function toggleStatus(int $id): ?bool
    {
        $stream = $this->find($id);
        if (!$stream) {
            return null;
        }

        $newStatus = $stream['enabled'] ? 0 : 1;
        $this->update($id, ['enabled' => $newStatus]);
        return (bool) $newStatus;
    }
}
