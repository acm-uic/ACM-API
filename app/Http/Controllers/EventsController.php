<?php
/**
 * Created by PhpStorm.
 * User: ezalenski
 * Date: 10/22/16
 * Time: 5:12 PM
 */

namespace App\Http\Controllers;

use DateInterval;
use DateTime;
use Google_Service_Calendar;
use Google_Client;
use Illuminate\Http\Request;

class EventsController extends Controller
{
    public function retrieveEvents() {
        $client = new Google_Client();
        $client->useApplicationDefaultCredentials();
        $client->addScope(Google_Service_Calendar::CALENDAR);
        $service = new Google_Service_Calendar($client);
        $current = new DateTime("now");
        $nextWeek = new DateTime("now");
        $nextWeek->add(new DateInterval('P7D'));
        $optParams = array('singleEvents' => 'true',
            'timeMin' => $current->format(DATE_RFC3339),
            'timeMax' => $nextWeek->format(DATE_RFC3339));
        $events = $service->events->listEvents('kc72g1ctfg8b88df34qqb62d1s@group.calendar.google.com', $optParams);

        $response = [];
        $response['Events'] = [];
        while(true) {
            foreach ($events->getItems() as $event) {
                if($event->getStart() != null && $event->getEnd() != null) {
                    if ($event->getStart()->getDateTime() == "") { //all day event
                        $eventState = new DateTime($event->getStart()->getDate());
                        $eventEnd = new DateTime($event->getEnd()->getDate());
                    } else {
                        $eventState = new DateTime($event->getStart()->getDateTime());
                        $eventEnd = new DateTime($event->getEnd()->getDateTime());
                    }
                    if ($eventState <= $current && $current <= $eventEnd) {
                        $event_response = [];
                        $event_response['Name'] = $event->getSummary();
                        array_push($response['Events'], $event_response);
                    }
                }
            }

            $pageToken = $events->getNextPageToken();
            if ($pageToken) {
                $optParams = array('singleEvents' => 'true',
                    'pageToken' => $pageToken,
                    'timeMin' => $current->format(DATE_RFC3339),
                    'timeMax' => $nextWeek->format(DATE_RFC3339));
                $events = $service->events->listEvents('kc72g1ctfg8b88df34qqb62d1s@group.calendar.google.com', $optParams);
            } else {
                break;
            }
        }

        return json_encode($response);
    }

    public function signinEvent(Request $request) {
        $data = [];
        $data["UIN"] = $request->input("uin");
        $data["Event"] = $request->input("event");
        //get user data from database based off of UIN
        //verify if the event is currently going on
        //update user points
        //return status code + user info
        return json_encode($data);
    }
}