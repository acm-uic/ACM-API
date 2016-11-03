<?php

namespace App\Http\Controllers;

use DB;
use ErrorException;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\User;

class UserController extends Controller
{
    public function linkUser(Request $request) {
        $userUIN = (int)$request->input("uin");
        $userUserName = $request->input("username");
        $userEmail = $request->input("email");
        echo $userUIN;
        $user = User::where('uin', $userUIN)->get()->first();
        if(!$user) {
            try {
                $user = UserController::createUser($userUIN, $userUserName, $userEmail);
            } catch (ErrorException $e) {
                Log::error($e);
                return response(["error" => "Internal Server Error"], 500);
            }
        } else if((!$user->username || !$user->email) &&
                  ($userUserName || $userEmail)) {
            $user = UserController::createFromACM($user, $userUserName, $userEmail);
            $user->save();
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
        $user = User::where('uin', (int)$uin)->get()->first();
        if(!$user) {
            try {
                $user = UserController::createUser((int)$uin);
            } catch (ErrorException $e) {
                Log::error($e);
                return response(["error" => "Internal Server Error"], 500);
            }
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

    public static function verifyUser($user) {
        if(!$user) {
            return response(["error" => "UIN not found"], 404);
        } else {
            return null;
        }
    }

    public static function createUser($uin, $username = null, $email = null)
    {
        $user = UserController::getInfoFromUIC($uin);
        if($user) {
            $user = UserController::createFromACM($user, $username, $email);
            $user->save();
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
                if($data["count"]) {
                    $user = new User;
                    $user->updateFromUICData($data);
                }
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
