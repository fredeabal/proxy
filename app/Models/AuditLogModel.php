<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditLogModel extends Model
{
    protected $table            = 'audit_logs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'action',
        'description',
        'ip_address',
        'created_at',
    ];

    /**
     * Registra una acción de auditoría de manera rápida.
     */
    public static function log(string $action, ?string $description = null, ?int $userId = null, ?string $ip = null): void
    {
        $model = new self();
        $model->insert([
            'user_id'     => $userId ?? session()->get('user_id'),
            'action'      => $action,
            'description' => $description,
            'ip_address'  => $ip ?? service('request')->getIPAddress(),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Devuelve el historial de auditoría junto con el nombre y correo del usuario.
     *
     * @return list<array<string, mixed>>
     */
    public function getLogsWithUsers(int $limit = 200): array
    {
        return $this->select('audit_logs.*, users.name as user_name, users.email as user_email')
            ->join('users', 'users.id = audit_logs.user_id', 'left')
            ->orderBy('audit_logs.id', 'DESC')
            ->findAll($limit);
    }
}
