<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCustomVhostToProxyHosts extends Migration
{
    public function up()
    {
        $this->forge->addColumn('proxy_hosts', [
            'custom_vhost' => [
                'type' => 'TEXT',
                'null' => true,
                'default' => null,
            ],
        ]);
    }

    public function down()
    {
        if ($this->db->DBDriver === 'SQLite3') {
            return;
        }

        $this->forge->dropColumn('proxy_hosts', 'custom_vhost');
    }
}

