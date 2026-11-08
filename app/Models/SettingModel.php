<?php

namespace App\Models;

use CodeIgniter\Model;

class SettingModel extends Model
{
    protected $table            = 'settings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'setting_key',
        'setting_value',
    ];

    /**
     * Obtiene el valor de una configuración por clave.
     */
    public function getVal(string $key, ?string $default = null): ?string
    {
        $row = $this->where('setting_key', $key)->first();
        return $row ? $row['setting_value'] : $default;
    }

    /**
     * Guarda o actualiza una configuración.
     */
    public function setVal(string $key, ?string $value): bool
    {
        $existing = $this->where('setting_key', $key)->first();
        if ($existing) {
            return (bool) $this->update($existing['id'], ['setting_value' => $value]);
        }
        return (bool) $this->insert(['setting_key' => $key, 'setting_value' => $value]);
    }
}
