<?php

// error_reporting(E_ALL & ~E_NOTICE);
require "vendor/autoload.php";
session_start();

// contains arrays $p1 through $a1 + auth details
require "patrols.php";

// if we don't have a token, get one
if (!isset($_SESSION['token'])) {

    header('Location: ' . 'auth.php');
    exit;

} else {

    try {

    	// if we don't have a specific event, return to events page
        if(!isset($_GET["id"])) {
            header('Location: ' . 'events.php');
            exit;
        }

        $id = $_GET["id"];

        echo "<html>";
        echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"style.css\">";

        $provider = new \League\OAuth2\Client\Provider\GenericProvider([
            'clientId'                => $clientId,    // The client ID assigned to you by the provider
            'clientSecret'            => $clientSecret,   // The client password assigned to you by the provider
            'redirectUri'             => 'http://pccrovers.com/tools/basecamp/auth.php',
            'urlAuthorize'            => 'https://launchpad.37signals.com/authorization/new?type=web_server',
            'urlAccessToken'          => 'https://launchpad.37signals.com/authorization/token?type=web_server',
            'urlResourceOwnerDetails' => 'https://launchpad.37signals.com/authorization.json'
        ]);

        // retrieve all comments on the evenvt page
        $request = $provider->getAuthenticatedRequest(
            'GET',
            'https://3.basecampapi.com/3797219/buckets/4286291/recordings/' . $id . '/comments.json',
            $_SESSION['token']
        );

        // create array of all patrols
        $members = array($p1, $p2, $p3, $p4, $p5, $p6, $a1);
        
        do{
            // response in json
            $response = $provider->getParsedResponse($request);
            
            // for every comment
            foreach($response as $c) {

            	// retrieve the name and content without extraneous formatting
                $name = str_replace( "/\r|\n/", "", strip_tags($c['creator']['name']));
                $content = preg_replace('/\s+/', " ", preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", " ", strip_tags($c['content'])));         
                
                // look through all patrols
                foreach($members as &$p) {

                	// associate comment with matching name in the patrols
                    if (array_key_exists($name, $p)) {
                        $p[$name] .= $content;
                        break;
                    }             
                }
            }
            
            // get the next page of comments
            $nextstr = $provider->getResponse($request)->getHeader('Link')[0];
            $next = substr($nextstr, 1, strcspn($nextstr, '>')-1);
            $request = $provider->getAuthenticatedRequest(
                'GET',
                $next,
                $_SESSION['token']
            );

        } while ($next); // loop if there is a next page of comments

        echo "<div id=\"left\">";
        echo "<a href=\"events.php\">&larr; Back to Events</a><br><br>";

        // get the title of the event and display it

        $request = $provider->getAuthenticatedRequest(
            'GET',
            'https://3.basecampapi.com/3797219/buckets/4286291/schedule_entries/' . $id . '.json',
            $_SESSION['token']
        );
        $response = $provider->getParsedResponse($request);

        echo "<strong>" . $response["title"] . '</strong><br>';

        // print the names of members and their respective response
        foreach($members as $patrol) {

            echo "<br>";
            $isFirst = true;
            
            foreach($patrol as $x => $x_value) {

            	// we want to print the patrol leader for separation
                if($isFirst) {
                    if(array_keys($patrol) == array_keys($a1)) {
                        echo "<strong>Advisors</strong><br>";
                    } else {
                        echo "<strong>" . $x . "'s Patrol</strong>" . "<br>";
                    }
                }

                echo $x . ": ";

                // print spaces so the comments are aligned. use a monospace font!
                for($i = 25; $i > strlen($x); $i--) {
                    echo "&nbsp;";
                }

                echo substr($x_value, 0, 35) . "<br>";
                $isFirst = false;
            }
        }
        echo "</div>";
        echo "<div id=\"right\">";
        echo "<br><br>Not yet RSVP'd: <br>";

        // variables to track stats
        $numyes=0;
        $nummembers=0;
        $numrovers=0;
        $numroversnotrsvp=0;
        $rsvpno = array();

        // for each patrol
        foreach($members as $patrol) {

            echo "<br>";
            $numnotrsvp=0;
            $isFirst = true;
            
            // we use this to temporarily hold members in each patrol who have not RSVP'd
            $notrsvp = array();
            
            foreach($patrol as $x => $x_value) {

                // once again we want to print the patrol leader as a heading
                if($isFirst) {
                    if(array_keys($patrol) == array_keys($a1)) {
                        echo "<strong>Advisors</strong>: ";
                    } else {
                        echo "<strong>" . $x . "'s Patrol</strong>" . ": ";
                    }
                }

                // if there is no comment, the member has not RSVP'd
                if ($x_value == "") {

                	// add to list of members who haven ot RSVP'd
                    array_push($notrsvp, $x);
                    $numnotrsvp++;

                    // we are only tracking rovers, not advisors
                    if(array_keys($patrol) != array_keys($a1)) { 
                        $numroversnotrsvp++;
                    }
                } else { // there is a comment i.e. they have RSVP'd
                	// if response contains yes
                    if (stripos($x_value, "yes") !== false) {
                        $numyes++;
                    // if it contains no
                    } elseif (stripos($x_value, "no") !== false) { 
                        array_push($rsvpno, $x);
                    }
                }
                // if the response has an asterisk, they are not counted in our stats for a reason (eg. OOT, LOA)
                if (stripos($x_value, "*") === false) {
                    $nummembers++;
                }
                
                // we are only tracking rovers, not advisors
                if (array_keys($patrol) != array_keys($a1)) {
                    $numrovers++;
                }
                $isFirst = false;

            }
            
            // print the number of members yet to RSVP
            echo $numnotrsvp . "/" . sizeof($patrol) . "<br>";
            
            // print the names of the members yet to RSVP
            for($i = 0, $size = count($notrsvp); $i < $size; ++$i) {

                if ($i == 0) echo ' (';
                echo $notrsvp[$i];
                if ($i != $size-1) {
                    echo ', ';
                } else {
                    echo ')<br>';
                }

            }
        }

        // print out useful stats
        echo "<br><br><b>Rovers not yet RSVP'd: </b>" . $numroversnotrsvp . "/" . $numrovers . " = " . number_format($numroversnotrsvp/$numrovers*100, 2, '.', ',')   . "%";
        echo "<br><br><b>Going thus far: </b>" . $numyes . "/" . $nummembers;
        echo "<br><br><b>RSVP'd No:</b><br>";

        // print out names of members who RSVP'd no
        for($i = 0, $size = count($rsvpno); $i < $size; ++$i) {

            echo $rsvpno[$i];
            if ($i != $size-1){
                echo ', ';
            }

        }

        echo "</div>";
        echo "</html>";
        
    } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {

        // Failed to get the access token or user details.
        exit($e->getMessage());

    }
}

?>
