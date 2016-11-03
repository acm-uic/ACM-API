<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class User extends Model
{
    use Notifiable;

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'remember_token'
    ];

    protected $table = 'users';

    public function updateFromACMData($data)
    {
        if($data["count"]) {
            $userEmail = $data[0]["mail"][0];
            $userUserName = $data[0]["samaccountname"][0];

            $userGroupsString = "";
            for($j = 0; $j < $data[0]["memberof"]["count"]; $j++) {
                $userGroupString = $data[0]["memberof"][$j];
                $userGroupsString .= $userGroupString;
            }

            $userGroups = str_getcsv($userGroupsString);
            foreach($userGroups as $group) {
                if(str_contains($group, "ACMPaid") == true)
                    $this->active = true;
            }

            $this->email = $userEmail;
            $this->username = $userUserName;
        }
    }

    public function updateFromUICData($data)
    {
        if($data["count"]) {
            $this->uin = $data[0]["employeeid"][0];
            $this->netid = $data[0]["cn"][0];
            $this->first = $data[0]["givenname"][0];
            $this->last = $data[0]["sn"][0];
        }
    }
}
