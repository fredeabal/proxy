<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBannedIpsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INTEGER',
                'auto_increment' => true,
            ],
            'ip_address' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'unique'     => true,
            ],
            'reason' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'default'    => 'Bloqueo de seguridad',
            ],
            'failed_attempts' => [
                'type'    => 'INTEGER',
                'default' => 0,
            ],
            'is_permanent' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'expires_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_by' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'system',
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
        $this->forge->addKey('ip_address');
        $this->forge->createTable('banned_ips', true);
    }

    public function down()
    {
        $this->forge->dropTable('banned_ips', true);
    }
}

