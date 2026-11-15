<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAdvancedProxyFeatures extends Migration
{
    public function up()
    {
        // 1. Nuevas columnas en proxy_hosts (Redirection Hosts, Rate Limiting, Access Lists)
        $this->forge->addColumn('proxy_hosts', [
            'host_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
                'default'    => 'proxy',
            ],
            'redirect_url' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
                'default'    => null,
            ],
            'redirect_code' => [
                'type'    => 'INTEGER',
                'null'    => false,
                'default' => 301,
            ],
            'preserve_path' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 1,
            ],
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
                'constraint' => 5,
                'null'       => false,
                'default'    => 's',
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
            'access_list_id' => [
                'type'    => 'INTEGER',
                'null'    => true,
                'default' => null,
            ],
        ]);

        // 2. Tabla access_lists
        $this->forge->addField([
            'id' => [
                'type'           => 'INTEGER',
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'satisfy_any' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
            ],
            'pass_auth' => [
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
        $this->forge->createTable('access_lists', true);

        // 3. Tabla access_list_clients (reglas allow/deny por IP/CIDR)
        $this->forge->addField([
            'id' => [
                'type'           => 'INTEGER',
                'auto_increment' => true,
            ],
            'access_list_id' => [
                'type' => 'INTEGER',
                'null' => false,
            ],
            'directive' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => false,
                'default'    => 'allow',
            ],
            'address' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('access_list_id');
        $this->forge->addForeignKey('access_list_id', 'access_lists', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('access_list_clients', true);

        // 4. Tabla access_list_auth (usuarios .htpasswd)
        $this->forge->addField([
            'id' => [
                'type'           => 'INTEGER',
                'auto_increment' => true,
            ],
            'access_list_id' => [
                'type' => 'INTEGER',
                'null' => false,
            ],
            'username' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'password_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('access_list_id');
        $this->forge->addForeignKey('access_list_id', 'access_lists', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('access_list_auth', true);

        // 5. Tabla proxy_locations (múltiples bloques location por host)
        $this->forge->addField([
            'id' => [
                'type'           => 'INTEGER',
                'auto_increment' => true,
            ],
            'proxy_host_id' => [
                'type' => 'INTEGER',
                'null' => false,
            ],
            'path' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'scheme' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => false,
                'default'    => 'http',
            ],
            'forward_host' => [
                'type'       => 'VARCHAR',
                'constraint' => 253,
                'null'       => false,
            ],
            'forward_port' => [
                'type' => 'INTEGER',
                'null' => false,
            ],
            'websocket_enabled' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
            ],
            'custom_headers' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'sort_order' => [
                'type'    => 'INTEGER',
                'null'    => false,
                'default' => 0,
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
        $this->forge->addForeignKey('proxy_host_id', 'proxy_hosts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('proxy_locations', true);

        // 6. Tabla streams (proxy TCP/UDP)
        $this->forge->addField([
            'id' => [
                'type'           => 'INTEGER',
                'auto_increment' => true,
            ],
            'incoming_port' => [
                'type' => 'INTEGER',
                'null' => false,
            ],
            'forward_host' => [
                'type'       => 'VARCHAR',
                'constraint' => 253,
                'null'       => false,
            ],
            'forward_port' => [
                'type' => 'INTEGER',
                'null' => false,
            ],
            'protocol' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => false,
                'default'    => 'tcp',
            ],
            'enabled' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 1,
            ],
            'description' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
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
        $this->forge->addUniqueKey('incoming_port');
        $this->forge->createTable('streams', true);
    }

    public function down()
    {
        $this->forge->dropTable('streams', true);
        $this->forge->dropTable('proxy_locations', true);
        $this->forge->dropTable('access_list_auth', true);
        $this->forge->dropTable('access_list_clients', true);
        $this->forge->dropTable('access_lists', true);

        if ($this->db->DBDriver !== 'SQLite3') {
            $this->forge->dropColumn('proxy_hosts', [
                'host_type',
                'redirect_url',
                'redirect_code',
                'preserve_path',
                'rate_limit_enabled',
                'rate_limit_requests',
                'rate_limit_unit',
                'rate_limit_burst',
                'rate_limit_nodelay',
                'access_list_id',
            ]);
        }
    }
}
