<?php
/**
 * Created by PhpStorm.
 * User: ezalenski
 * Date: 10/22/16
 * Time: 5:12 PM
 */

namespace App\Http\Controllers;

use Carbon\Carbon;
use Google_Service_Calendar;
use Google_Client;
use Illuminate\Http\Request;
use App\Event;

class EventController extends Controller
{
    public function retrieveEvents() {

        $response = [];
        $response['events'] = [];
        $currentEvents = EventController::findOngoingEvents();
        foreach($currentEvents as $event) {
            $eventResponse = [];
            $eventResponse['event'] = $event->name;
            array_push($response['events'], $eventResponse);
        }

        return json_encode($response);
    }

    public function signinEvent(Request $request) {
        $userUIN = $request->input("uin");
        $eventName = $request->input("event");

        //get user data from database based off of UIN
        try {
            $user = UserController::findOrCreateUser((int)$userUIN);
        }
        catch(ErrorException $e) {
            return response(["error" => "Internal Server Error"], 500);
        }

        $errorResponse = UserController::verifyActiveUser($user);
        if($errorResponse) {
            return $errorResponse;
        }
        //verify if the event is currently going on
        $ongoingEvents = EventController::findOngoingEvents();
        $event = array_first($ongoingEvents, function($event, $_) use ($eventName) {
            return $event->name === $eventName;
        });
        if(!$event) {
            $response["error"] = "event is not ongoing";
            return response($response, 403);
        }
        //update user points
        if(TransactionController::userSignedIn($user->uin, $event)) {
            $response["error"] = "user already signed into this event";
            return response($response, 403);
        }
        TransactionController::createTransaction($user->uin,$event->id);
        //return status code + user info
        $points = TransactionController::getPointsTotal($user->uin);
        $response = [
            "firstName" => $user->first,
            "lastName"  => $user->last,
            "netid"     => $user->netid,
            "points"    => $points
        ];
        return response($response, 200);
    }

    public static function findOngoingEvents() {
        $current = Carbon::now();
        $nextDay = Carbon::tomorrow();
        $events = EventController::getEventsInInterval($current, $nextDay);

        $ongoingEvents = [];
        while(true) {
            foreach ($events->getItems() as $event) {
                if($event->getStart() != null && $event->getEnd() != null) {
                    if ($event->getStart()->getDateTime() == "") { //all day event
                        $eventStart = Carbon::createFromFormat(DATE_RFC3339, $event->getStart()->getDate());
                        $eventEnd = Carbon::createFromFormat(DATE_RFC3339, $event->getEnd()->getDate());;
                    } else {
                        $eventStart = Carbon::createFromFormat(DATE_RFC3339, $event->getStart()->getDateTime());
                        $eventEnd = Carbon::createFromFormat(DATE_RFC3339, $event->getEnd()->getDateTime());
                    }
                    if ($eventStart <= $current && $current <= $eventEnd) {
                        $eventName = $event->getSummary();
                        $newEvent = Event::where('name', $eventName)->get()->first();
                        if(!$newEvent) {
                            $newEvent = new Event;
                            $newEvent->name = $eventName;
                            $newEvent->active = true;
                            $newEvent->save();
                        }
                        $newEvent->startTime = $eventStart;
                        $newEvent->endTime = $eventEnd;
                        array_push($ongoingEvents, $newEvent);
                    }
                }
            }

            $pageToken = $events->getNextPageToken();
            if ($pageToken) {
                $events = EventController::getNextPage($pageToken);
            } else {
                break;
            }
        }

        return $ongoingEvents;
    }

    private static function getEventsInInterval($startDateTime, $endDateTime)
    {
        $client = new Google_Client();
        $client->useApplicationDefaultCredentials();
        $client->addScope(Google_Service_Calendar::CALENDAR);
        $service = new Google_Service_Calendar($client);
        $calendarID = getenv('ACM_CALENDAR_ID');

        $optParams = array(
            'singleEvents' => 'true',
            'timeMin' => $startDateTime->toRfc3339String(),
            'timeMax' => $endDateTime->toRfc3339String());
        $response = $service->events->listEvents($calendarID, $optParams);
        return $response;
    }


    private static function getNextPage($pageToken)
    {
        $client = new Google_Client();
        $client->useApplicationDefaultCredentials();
        $client->addScope(Google_Service_Calendar::CALENDAR);
        $service = new Google_Service_Calendar($client);
        $calendarID = getenv('ACM_CALENDAR_ID');

        /* this might be buggy, I'm not totally sure if the interval is needed when getting the next page */
        $optParams = array(
            'singleEvents' => 'true',
            'pageToken' => $pageToken);
        $response = $service->events->listEvents($calendarID, $optParams);
        return $response;
    }
}