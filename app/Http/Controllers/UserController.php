<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;

class UserController extends Controller
{
    public function retrieveUser(Request $request, $uin) {
        //check with AD user info
        $data = [];
        $data["UIN"] = $uin;
        return json_encode($data);
    }
}
