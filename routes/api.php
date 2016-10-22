<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/events', function (Request $request) {
    $client = new Google_Client();
    $client->useApplicationDefaultCredentials();
    $client->addScope(Google_Service_Calendar::CALENDAR);
    $service = new Google_Service_Calendar($client);
    $current = new DateTime("2016-10-26T17:35:00-05:00");
    $nextweek = new DateTime("2016-10-26T17:35:00-05:00");
    $nextweek->add(new DateInterval('P7D'));
    $optParams = array('singleEvents' => 'true', 'timeMin' => $current->format(DATE_RFC3339), 'timeMax' => $nextweek->format(DATE_RFC3339));
    $events = $service->events->listEvents('kc72g1ctfg8b88df34qqb62d1s@group.calendar.google.com', $optParams);

    $response = [];
    $response['Events'] = [];
    while(true) {
        foreach ($events->getItems() as $event) {
            if($event->getStart() != null && $event->getEnd() != null) {
                if ($event->getStart()->getDateTime() == "") { //all day event
                    $event_start = new DateTime($event->getStart()->getDate());
                    $event_end = new DateTime($event->getEnd()->getDate());
                } else {
                    $event_start = new DateTime($event->getStart()->getDateTime());
                    $event_end = new DateTime($event->getEnd()->getDateTime());
                }
                if ($event_start <= $current && $current <= $event_end) {
                    $event_response = [];
                    $event_response['Name'] = $event->getSummary();
                    array_push($response['Events'], $event_response);
                }
            }
        }

        $pageToken = $events->getNextPageToken();
        if ($pageToken) {
            $optParams = array('singleEvents' => 'true', 'pageToken' => $pageToken, 'timeMin' => $current->format(DATE_RFC3339));
            $events = $service->events->listEvents('kc72g1ctfg8b88df34qqb62d1s@group.calendar.google.com', $optParams);
        } else {
            break;
        }
    }

    return json_encode($response);
});

