<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRateLimitToProxyHosts extends Migration
{
    public function up()
    {
        $columns = [
            'rate_limit_enabled' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
            ],
            'rate_limit_requests' => [
                'type'    => 'INTEGER',
                'null'    => false,
                'default' => 10,
            ],
            'rate_limit_unit' => [
                'type'       => 'VARCHAR',
                'constraint' => 8,
                'null'       => false,
                'default'    => 'r/s',
            ],
            'rate_limit_burst' => [
                'type'    => 'INTEGER',
                'null'    => false,
                'default' => 20,
            ],
            'rate_limit_nodelay' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 1,
            ],
        ];

        $missing = [];
        foreach ($columns as $name => $definition) {
            if (!$this->db->fieldExists($name, 'proxy_hosts')) {
                $missing[$name] = $definition;
            }
        }

        if ($missing !== []) {
            $this->forge->addColumn('proxy_hosts', $missing);
        }
    }

    public function down()
    {
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->forge->dropColumn('proxy_hosts', [
                'rate_limit_enabled',
                'rate_limit_requests',
                'rate_limit_unit',
                'rate_limit_burst',
                'rate_limit_nodelay',
            ]);
        }
    }
}
