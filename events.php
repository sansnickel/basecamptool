<?php

error_reporting(E_ALL & ~E_NOTICE);
require "vendor/autoload.php";
session_start();
require "patrols.php";

// functions to be used with usort
// they are used to sort past events with most recent on top
//                       upcoming events with soonest on top

function cmp($a, $b)
{
    if ($a["starts_at"] == $b["starts_at"]) {
        return 0;
    }
    return ($a["starts_at"] < $b["starts_at"]) ? -1 : 1;
}

function cmp2($a, $b)
{
    if ($a["starts_at"] == $b["starts_at"]) {
        return 0;
    }
    return ($a["starts_at"] < $b["starts_at"]) ? 1 : -1;
}

// if we don't have a token, get one
if (!isset($_SESSION['token'])) {

    header('Location: ' . 'auth.php');
    exit;

} else {

    try {

        echo "<html>";
        echo "<link rel=\"stylesheet\" href=\"https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css\">";
        echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"style.css\">";

        $provider = new \League\OAuth2\Client\Provider\GenericProvider([
            'clientId'                => $clientId,    // The client ID assigned to you by the provider
            'clientSecret'            => $clientSecret,   // The client password assigned to you by the provider
            'redirectUri'             => 'http://pccrovers.com/tools/basecamp/auth.php',
            'urlAuthorize'            => 'https://launchpad.37signals.com/authorization/new?type=web_server',
            'urlAccessToken'          => 'https://launchpad.37signals.com/authorization/token?type=web_server',
            'urlResourceOwnerDetails' => 'https://launchpad.37signals.com/authorization.json'
        ]);
        // The provider provides a way to get an authenticated API request for
        // the service, using the access token; it returns an object conforming
        // to Psr\Http\Message\RequestInterface.

        // we want to get all the events in our calendar
        $request = $provider->getAuthenticatedRequest(
            'GET',
            'https://3.basecampapi.com/3797219/buckets/4286291/schedules/593917827/entries.json',
            $_SESSION['token']
        );

        // separate upcoming events and past events
        $events = array();
        $pastevents = array();
        
        do{

            $response = $provider->getParsedResponse($request);

            foreach($response as $s) {

                // if it is a past event
                if ($s["starts_at"] < date("Y-m-d")) {

                    // we are storing the title, when the event starts, and its id
                    array_push($pastevents, array("summary" => $s["summary"], "starts_at" => $s["starts_at"], "id" => $s["id"]));

                } else { // it is an upcoming event

                    array_push($events, array("summary" => $s["summary"], "starts_at" => $s["starts_at"], "id" => $s["id"]));

                }

            }

            // get the next page of events
            $nextstr = $provider->getResponse($request)->getHeader('Link')[0];
            $next = substr($nextstr, 1, strcspn($nextstr, ">"));
            $request = $provider->getAuthenticatedRequest(
                'GET',
                $next,
                $_SESSION['token']
            );

        } while ($next);

        // sort in events in the appropriate order
        usort($events, "cmp");
        usort($pastevents, "cmp2");

        echo "<div class=\"row\">";
        echo "<div class=\"col-sm-3\"><a href=\"engagement.php\">Basecamp 3 Engagement Statistics</a></div>";
        echo "<div class=\"col-sm-6\">";

        echo "<h2>Upcoming Events</h2><br>";
        echo "<div class=\"list-group\">";

        // list upcoming events
        foreach($events as $s) {

            echo "<a href=\"reporting.php?id=" . $s["id"] . "\" class=\"list-group-item\">";
            echo "<span class=\"center\">" . $s["summary"] . "</span>" . "<span class=\"right\">" . substr($s["starts_at"], 0, 10) . "</span>";
            echo "</a>";

        }

        echo "</div>"; // list-group
        echo "<br><h2>Past Events</h2><br>";
        echo "<div class=\"list-group\">";

        // list past events
        foreach($pastevents as $s) {

            echo "<a href=\"reporting.php?id=" . $s["id"] . "\" class=\"list-group-item\">";
            echo "<span class=\"center\">" . $s["summary"] . "</span>" . "<span class=\"right\">" . substr($s["starts_at"], 0, 10) . "</span>";
            echo "</a>";

        }

        echo "</div>"; // list-group
        echo "</div>"; // col-sm-6
        echo "<div class=\"col-sm-3\"></div>";
        echo "</html>";

    } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {

        // Failed to get the access token or user details.
        exit($e->getMessage());

    }

}

?>
