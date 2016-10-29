<?php

namespace App\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\User;

class UserController extends Controller
{
    public function retrieveUser(Request $request, $uin) {
        $response = [];
        $user = UserController::findOrCreateUser((int)$uin);
        if($user) {
            //maybe check if user is active? or just return that information with response
            $response["firstName"] = $user->first;
            $response["lastName"] = $user->last;
            $response["netid"] = $user->netid;
            $points = TransactionController::getPointsTotal($user->uin);
            $response["points"] = $points;
        } else {
            //throw error for user not found
        }
        return json_encode($response);
    }

    public static function findOrCreateUser($uin)
    {
        $user = User::where('uin', $uin)->get()->first();
        if($user) {
            return $user;
        } else {
            //break uic and acm queries into separate functions
            $ldapUICUser = getenv('LDAP_UIC_USERNAME');
            $ldapUICPass = getenv('LDAP_UIC_PASSWORD');
            $ldapUICServer = getenv('LDAP_UIC_HOST');
            $ldapUICBase = getenv('LDAP_UIC_GROUP');
            $ldapACMUser = getenv('LDAP_ACM_USERNAME');
            $ldapACMPass = getenv('LDAP_ACM_PASSWORD');
            $ldapACMServer = getenv('LDAP_ACM_HOST');
            $ldapACMBase = getenv('LDAP_ACM_GROUP');

            $ldapUICConn = ldap_connect($ldapUICServer) or die("Could not connect to UIC LDAP server.");
            $ldapACMConn = ldap_connect($ldapACMServer) or die("Could not connect to ACM LDAP server.");
            if ($ldapUICConn && $ldapACMConn) {
                $ldapUICBind = ldap_bind($ldapUICConn, $ldapUICUser, $ldapUICPass) or die ("Error trying to bind UIC: " . ldap_error($ldapUICConn));
                $ldapACMBind = ldap_bind($ldapACMConn, $ldapACMUser, $ldapACMPass) or die("Error trying to bind ACM: ". ldap_error($ldapACMConn));
                if ($ldapUICBind) {
                    $result = ldap_search($ldapUICConn, $ldapUICBase, "(employeeid=$uin)") or die ("Error in search query: " . ldap_error($ldapUICConn));
                    $data = ldap_get_entries($ldapUICConn, $result);

                    for ($i = 0; $i < $data["count"]; $i++) {
                        $user = new User;

                        $userFirstName = $data[$i]["givenname"]["0"];
                        $userLastName = $data[$i]["sn"]["0"];
                        $userNetID = $data[$i]["cn"]["0"];

                        $user->uin = $uin;
                        $user->netid = $userNetID;
                        $user->first = $userFirstName;
                        $user->last = $userLastName;
                    }
                } else {
                    echo "LDAP bind failed...";
                }

                if($ldapACMBind && $user) {
                    $result = ldap_search($ldapACMConn, $ldapACMBase, "(cn=$user->first $user->last)") or die ("Error in search query: " . ldap_error($ldapACMConn));
                    $data = ldap_get_entries($ldapACMConn, $result);

                    for($i = 0; $i < $data["count"]; $i++) {
                        $userEmail = $data[$i]["mail"]["0"];
                        $userUserName = $data[$i]["samaccountname"]["0"];
                        $userGroupsString = "";
                        for($j = 0; $j < $data[$i]["memberof"]["count"]; $j++) {
                            $userGroupString = $data[$i]["memberof"][$j];
                            $userGroupsString .= $userGroupString;
                        }
                        $userGroups = str_getcsv($userGroupsString);
                        foreach($userGroups as $group) {
                            if(str_contains($group, "ACMPaid") == true)
                                $user->active = true;
                        }

                        $user->email = $userEmail;
                        $user->username = $userUserName;

                        $user->save();
                    }
                } else {
                    //acm bind failed or user was not found probably should check for which?
                }
            }

            ldap_close($ldapUICConn);
            ldap_close($ldapACMConn);

            return $user;
        }
    }
}
