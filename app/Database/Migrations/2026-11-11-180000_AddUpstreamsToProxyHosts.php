<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUpstreamsToProxyHosts extends Migration
{
    public function up()
    {
        // 1. Agregar columnas a proxy_hosts
        $this->forge->addColumn('proxy_hosts', [
            'load_balancing_enabled' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
            ],
            'lb_algorithm' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
                'default'    => 'round_robin',
            ],
        ]);

        // 2. Crear tabla proxy_upstreams
        $this->forge->addField([
            'id' => [
                'type'           => 'INTEGER',
                'auto_increment' => true,
            ],
            'proxy_host_id' => [
                'type' => 'INTEGER',
                'null' => false,
            ],
            'server_host' => [
                'type'       => 'VARCHAR',
                'constraint' => 253,
                'null'       => false,
            ],
            'server_port' => [
                'type' => 'INTEGER',
                'null' => false,
            ],
            'weight' => [
                'type'    => 'INTEGER',
                'null'    => false,
                'default' => 1,
            ],
            'max_fails' => [
                'type'    => 'INTEGER',
                'null'    => false,
                'default' => 3,
            ],
            'fail_timeout' => [
                'type'    => 'INTEGER',
                'null'    => false,
                'default' => 10,
            ],
            'is_backup' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
            ],
            'is_down' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('proxy_host_id');
        $this->forge->createTable('proxy_upstreams', true);
    }

    public function down()
    {
        $this->forge->dropTable('proxy_upstreams', true);

        if ($this->db->DBDriver !== 'SQLite3') {
            $this->forge->dropColumn('proxy_hosts', ['load_balancing_enabled', 'lb_algorithm']);
        }
    }
}

