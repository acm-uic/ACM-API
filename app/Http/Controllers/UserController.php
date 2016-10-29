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
        $userUIN = $request->input("uin");
        $userUserName = $request->input("username");
        $userEmail = $request->input("email");
        try {
            $user = UserController::findOrCreateUser((int)$userUIN, $userUserName, $userEmail);
        }
        catch(ErrorException $e) {
            return response(["error" => "Internal Server Error"], 500);
        }

        $errorResponse = UserController::verifyUser($user);
        if($errorResponse) {
            return $errorResponse;
        }
        $points = TransactionController::getPointsTotal($user->uin);
        $response = [
            "firstName" => $user->first,
            "lastName"  => $user->last,
            "netid"     => $user->netid,
            "points"    => $points
        ];
        return response($response, 200);
    }

    public function retrieveUser(Request $request, $uin) {
        try {
            $user = UserController::findOrCreateUser((int)$uin);
        }
        catch(ErrorException $e) {
            return response(["error" => "Internal Server Error"], 500);
        }

        $errorResponse = UserController::verifyUser($user);
        if($errorResponse) {
            return $errorResponse;
        }
        $points = TransactionController::getPointsTotal($user->uin);
        $response = [
            "firstName" => $user->first,
            "lastName"  => $user->last,
            "netid"     => $user->netid,
            "points"    => $points
        ];
        return response($response, 200);
    }

    public static function verifyActiveUser($user)
    {
        if(!$user) {
            return response(["error" => "UIN not found"], 404);
        } else if(!$user->username) {
            return response(["error" => "unable to find ACM account with only UIN"], 300);
        } else if(!$user->active) {
            return response(["error" => "user is an inactive ACM member"], 403);
        } else {
            return null;
        }
    }

    public static function verifyUser($user) {
        if(!$user) {
            return response(["error" => "UIN not found"], 404);
        } else if(!$user->username) {
            return response(["error" => "unable to find ACM account with only UIN"], 300);
        } else {
            return null;
        }
    }

    public static function findOrCreateUser($uin, $username = null, $email = null)
    {
        $user = User::where('uin', $uin)->get()->first();
        if(!$user) {
            $user = UserController::getInfoFromUIC($uin);
            if($user) {
                $user = UserController::createFromACM($user, $username, $email);
            }
        }
        return $user;
    }


    private static function getInfoFromUIC($uin)
    {
        $user = null;

        $ldapUICUser = getenv('LDAP_UIC_USERNAME');
        $ldapUICPass = getenv('LDAP_UIC_PASSWORD');
        $ldapUICServer = getenv('LDAP_UIC_HOST');
        $ldapUICBase = getenv('LDAP_UIC_GROUP');

        $ldapUICConn = @ldap_connect($ldapUICServer);
        if($ldapUICConn) {
            $ldapUICBind = @ldap_bind($ldapUICConn, $ldapUICUser, $ldapUICPass);
            if ($ldapUICBind) {
                $result = ldap_search($ldapUICConn, $ldapUICBase, "(employeeid=$uin)");
                $data = ldap_get_entries($ldapUICConn, $result);
                $user = new User;
                $user->updateFromUICData($data);
            } else {
                throw new ErrorException("Unable to connect to UIC ad");
            }
        }
        ldap_close($ldapUICConn);
        return $user;
    }

    private static function createFromACM($user, $username, $email)
    {
        $ldapACMUser = getenv('LDAP_ACM_USERNAME');
        $ldapACMPass = getenv('LDAP_ACM_PASSWORD');
        $ldapACMServer = getenv('LDAP_ACM_HOST');
        $ldapACMBase = getenv('LDAP_ACM_GROUP');
        $ldapACMConn = @ldap_connect($ldapACMServer);
        if ($ldapACMConn) {
            $ldapACMBind = @ldap_bind($ldapACMConn, $ldapACMUser, $ldapACMPass);
            if($ldapACMBind) {
                $query = UserController::createQuery($user, $username, $email);
                $result = ldap_search($ldapACMConn, $ldapACMBase, $query);
                $data = ldap_get_entries($ldapACMConn, $result);
                $user->updateFromACMData($data);
            } else {
                throw new ErrorException("Unable to connect to ACM ad");
            }
        }
        ldap_close($ldapACMConn);
        return $user;
    }

    private static function createQuery($user, $username, $email)
    {
        $query = "(&(cn=$user->first $user->last)";
        if($username) {
            $query .= "(samaccountname=$username)";
        }
        if($email) {
            $query .= "(mail=$email)";
        }
        $query .= ")";
        return $query;
    }
}
