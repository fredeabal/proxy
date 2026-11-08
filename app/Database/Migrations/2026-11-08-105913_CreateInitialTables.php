<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInitialTables extends Migration
{
    public function up()
    {
        // 1. Tabla users
        $this->forge->addField([
            'id' => [
                'type'           => 'INTEGER',
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
                'unique'     => true,
            ],
            'password' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'role' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'default'    => 'admin',
            ],
            'two_factor_secret' => [
                'type'       => 'TEXT',
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
        $this->forge->createTable('users', true);

        // 2. Tabla proxy_hosts
        $this->forge->addField([
            'id' => [
                'type'           => 'INTEGER',
                'auto_increment' => true,
            ],
            'domain' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'unique'     => true,
            ],
            'domain_names' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'scheme' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'default'    => 'http',
            ],
            'forward_host' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'forward_port' => [
                'type' => 'INTEGER',
            ],
            'ssl_enabled' => [
                'type'       => 'BOOLEAN',
                'default'    => false,
            ],
            'websocket_enabled' => [
                'type'       => 'BOOLEAN',
                'default'    => false,
            ],
            'force_https' => [
                'type'       => 'BOOLEAN',
                'default'    => false,
            ],
            'enabled' => [
                'type'       => 'BOOLEAN',
                'default'    => true,
            ],
            'custom_headers' => [
                'type' => 'TEXT',
                'null' => true,
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
        $this->forge->createTable('proxy_hosts', true);

        // 3. Tabla certificates
        $this->forge->addField([
            'id' => [
                'type'           => 'INTEGER',
                'auto_increment' => true,
            ],
            'domain' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'certificate_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
            ],
            'private_key_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
            ],
            'issuer' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'default'    => "Let's Encrypt",
            ],
            'expires_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'auto_renew' => [
                'type'    => 'BOOLEAN',
                'default' => true,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'valid',
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
        $this->forge->createTable('certificates', true);

        // 4. Tabla settings
        $this->forge->addField([
            'id' => [
                'type'           => 'INTEGER',
                'auto_increment' => true,
            ],
            'setting_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'unique'     => true,
            ],
            'setting_value' => [
                'type' => 'TEXT',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('settings', true);

        // 5. Tabla audit_logs
        $this->forge->addField([
            'id' => [
                'type'           => 'INTEGER',
                'auto_increment' => true,
            ],
            'user_id' => [
                'type' => 'INTEGER',
                'null' => true,
            ],
            'action' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'ip_address' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('audit_logs', true);
    }

    public function down()
    {
        $this->forge->dropTable('audit_logs', true);
        $this->forge->dropTable('settings', true);
        $this->forge->dropTable('certificates', true);
        $this->forge->dropTable('proxy_hosts', true);
        $this->forge->dropTable('users', true);
    }
}
