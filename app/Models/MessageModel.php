<?php namespace App\Models;

use CodeIgniter\Model;

class MessageModel extends Model
{
    protected $table = 'messages';
    protected $allowedFields = ['message_type', 'content', 'payload', 'user_id', 'created_at'];
}
