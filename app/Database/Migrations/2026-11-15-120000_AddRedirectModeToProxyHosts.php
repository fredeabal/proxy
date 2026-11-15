<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRedirectModeToProxyHosts extends Migration
{
    public function up()
    {
        $this->forge->addColumn('proxy_hosts', [
            'redirect_enabled' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
            ],
            'redirect_url' => [
                'type'       => 'VARCHAR',
                'constraint' => 2048,
                'null'       => true,
                'default'    => null,
            ],
            'redirect_code' => [
                'type'    => 'INTEGER',
                'null'    => false,
                'default' => 301,
            ],
            'redirect_preserve_path' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 1,
            ],
        ]);
    }

    public function down()
    {
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->forge->dropColumn('proxy_hosts', [
                'redirect_enabled',
                'redirect_url',
                'redirect_code',
                'redirect_preserve_path',
            ]);
        }
    }
}
