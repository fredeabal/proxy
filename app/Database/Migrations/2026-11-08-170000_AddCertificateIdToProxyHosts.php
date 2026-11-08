<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCertificateIdToProxyHosts extends Migration
{
    public function up()
    {
        $this->forge->addColumn('proxy_hosts', [
            'certificate_id' => [
                'type'    => 'INTEGER',
                'null'    => true,
                'default' => null,
            ],
        ]);
    }

    public function down()
    {
        // SQLite no soporta DROP COLUMN de forma fiable; la columna es nullable e inocua.
        if ($this->db->DBDriver === 'SQLite3') {
            return;
        }

        $this->forge->dropColumn('proxy_hosts', 'certificate_id');
    }
}
