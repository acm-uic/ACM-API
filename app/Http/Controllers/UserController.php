<?php

namespace App\Http\Controllers;

use DB;
use ErrorException;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\User;

class UserController extends Controller
{
    public function createUser(Request $request) {
        $response = [];
        $userUIN = $request->input("uin");
        $userUserName = $request->input("username");
        $userEmail = $request->input("email");

        try {
            $user = UserController::findOrCreateUser((int)$userUIN, $userUserName, $userEmail);
        }
        catch(ErrorException $e) {
            return response(["error" => $e->getTraceAsString()], 500);
        }
        if(!$user) {
            $response["error"] = "UIN not found";
            return response($response, 404);
        } else if(!$user->username) {
            $response["error"] = "unable to find ACM account with information given";
            return response($response, 300);
        }

        $response["firstName"] = $user->first;
        $response["lastName"] = $user->last;
        $response["netid"] = $user->netid;
        $points = TransactionController::getPointsTotal($user->uin);
        $response["points"] = $points;
        return response($response, 200);
    }

    public function retrieveUser(Request $request, $uin) {
        $response = [];

        try {
            $user = UserController::findOrCreateUser((int)$uin);
        }
        catch(ErrorException $e) {
            return response(["error" => "Internal Server Error"], 500);
        }

        if($user) {
            if($user->username) {
                $response["firstName"] = $user->first;
                $response["lastName"] = $user->last;
                $response["netid"] = $user->netid;
                $points = TransactionController::getPointsTotal($user->uin);
                $response["points"] = $points;
                return response($response, 200);
            } else {
                $response["error"] = "unable to find ACM account with only UIN";
                return response($response, 300);
            }
        } else {
            $response["error"] = "UIN not found";
            return response($response, 404);
        }
    }

    public static function findOrCreateUser($uin, $username = null, $email = null)
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
            $ldapUICConn = @ldap_connect($ldapUICServer);
            $ldapACMConn = @ldap_connect($ldapACMServer);
            if ($ldapUICConn && $ldapACMConn) {
                $ldapUICBind = @ldap_bind($ldapUICConn, $ldapUICUser, $ldapUICPass);
                $ldapACMBind = @ldap_bind($ldapACMConn, $ldapACMUser, $ldapACMPass);
                if ($ldapUICBind) {
                    $result = ldap_search($ldapUICConn, $ldapUICBase, "(employeeid=$uin)");
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
                    throw new ErrorException("Unable to connect to UIC ad");
                }

                if($ldapACMBind && $user) {
                    $query = "(&(cn=$user->first $user->last)";
                    if($username) {
                        $query .= "(samaccountname=$username)";
                    }
                    if($email) {
                        $query .= "(mail=$email)";
                    }
                    $query .= ")";
                    
                    $result = ldap_search($ldapACMConn, $ldapACMBase, $query);
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
                    if(!$ldapACMBind) {
                        throw new ErrorException("Unable to connect to ACM ad");
                    }
                }
            }

            ldap_close($ldapUICConn);
            ldap_close($ldapACMConn);

            return $user;
        }
    }
}
