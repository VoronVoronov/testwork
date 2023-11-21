<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Message extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 5,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'message_type' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
            ],
            'content' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'user_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('messages');
    }

    public function down()
    {
        $this->forge->dropTable('messages');
    }
}
