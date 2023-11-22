<?php

namespace App\Controllers;

use App\Models\MessageModel;

class Home extends BaseController
{
    public function index()
    {
        $messages = new MessageModel();
        $data = [
            'messages' => $messages->paginate(10),
            'pager' => $messages->pager,
        ];
        return view('welcome_message', $data);
    }
}
