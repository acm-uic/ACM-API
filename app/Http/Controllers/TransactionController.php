<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Transaction;
use App\User;
use App\Event;

class TransactionController extends Controller
{
    //
    public static function getPointsTotal($uin)
    {
        $totalPoints = Transaction::where('uin', $uin)->sum('point');
        return $totalPoints;
    }

    public static function createTransaction($uin, $id)
    {
        $user = User::where('uin', $uin)->get()->first();
        if(!$user) {
            echo 'createTransaction - User sanity check failed.';
            return;
        }
        $event = Event::where('id', $id)->get()->first();
        if(!$event) {
            echo 'createTransaction - Event sanity check failed.';
            return;
        }
        $transactionCount = Transaction::where([
            ['eid', $id],
            ['uin', $uin]
        ])->count();

        $transaction = new Transaction;
        $transaction->eid = $id;
        $transaction->uin = $uin;
        $transaction->point = $event->value;
        if(($transactionCount + 1) % $event->bonus_interval === 0) {
            $transaction->point += $event->bonus_value;
        }
        $transaction->save();
    }

    public static function userSignedIn($uin, $event)
    {
        $user = User::where('uin', $uin)->get()->first();
        if(!$user) {
            echo 'createTransaction - User sanity check failed.';
            return false;
        }
        $transactionCount = Transaction::where([
            ['eid', $event->id],
            ['uin', $uin]
        ])
            ->whereBetween('created_at', array($event->startTime, $event->endTime))
            ->count();
        if($transactionCount > 0) {
            return true;
        }
        return false;
    }
}
