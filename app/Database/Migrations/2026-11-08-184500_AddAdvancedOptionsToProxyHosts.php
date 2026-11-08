<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAdvancedOptionsToProxyHosts extends Migration
{
    public function up()
    {
        $this->forge->addColumn('proxy_hosts', [
            'proxy_connect_timeout' => [
                'type'    => 'INTEGER',
                'null'    => false,
                'default' => 60,
            ],
            'proxy_read_timeout' => [
                'type'    => 'INTEGER',
                'null'    => false,
                'default' => 60,
            ],
            'proxy_send_timeout' => [
                'type'    => 'INTEGER',
                'null'    => false,
                'default' => 60,
            ],
            'client_max_body_size' => [
                'type'    => 'INTEGER',
                'null'    => false,
                'default' => 10,
            ],
            'proxy_ssl_verify' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
            ],
            'hsts_enabled' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
            ],
        ]);
    }

    public function down()
    {
        if ($this->db->DBDriver === 'SQLite3') {
            return;
        }

        $this->forge->dropColumn('proxy_hosts', [
            'proxy_connect_timeout',
            'proxy_read_timeout',
            'proxy_send_timeout',
            'client_max_body_size',
            'proxy_ssl_verify',
            'hsts_enabled',
        ]);
    }
}

