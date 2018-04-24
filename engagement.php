<?php

// error_reporting(E_ALL & ~E_NOTICE);
require "vendor/autoload.php";
session_start();

// contains arrays $p1 thorugh $p6 + auth details
require "departments.php";

// if we don't have a token, get one
if (!isset($_SESSION['token'])) {

    header('Location: ' . 'auth.php');
    exit;

} else {

    try {

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
        // The provider provides a way to get an authenticated API request for
        // the service, using the access token; it returns an object conforming
        // to Psr\Http\Message\RequestInterface.

        // these are the types of "actions" we are recording + are available through Basecamp 3's API
        $type = array('Comment', 'Document', 'Message', 'Question::Answer', 'Schedule::Entry', 'Todo', 'Todolist', 'Upload');
       
        // create array of all patrols
        $members = array($p1, $p2, $p3, $p4, $p5, $p6);

        // just looping through all the types of actions, they are all the same as far as we are concerned
        foreach($type as $record) {

            $request = $provider->getAuthenticatedRequest(
                'GET',
                'https://3.basecampapi.com/3797219/projects/recordings.json?type=' . $record,
                $_SESSION['token']);

            do{

                $response = $provider->getParsedResponse($request);

                foreach($response as $c) {

                	// we only need the name + time of the action
                    $name = str_replace( "/\r|\n/", "", strip_tags($c['creator']['name']));
                    $when = str_replace( "/\r|\n/", "", strip_tags($c['created_at']));
                    
                    // we only track stats for the current month
                    if (substr_count($when, date("Y-m")) == 1){

                    	// look through the arrays to see which department the name is from
                        foreach($members as &$p) {

                            if (array_key_exists($name, $p)) {
                               
                                // increment the # of actions by one for this member
                                $p[$name]++;
                                break;
                            }
                        }
                    }     
            }

            // get the next page of recordings
            $nextstr = $provider->getResponse($request)->getHeader('Link')[0];
            $next = substr($nextstr, 1, strcspn($nextstr, '>')-1);
            $request = $provider->getAuthenticatedRequest(
                'GET',
                $next,
                $_SESSION['token']
            );

        } while ($next);
    }

    echo "<div id=\"left\">";
    echo "<a href=\"events.php\">&larr; Back to Events</a><br><br>";
    echo "Number of Actions on Basecamp for " . date("Y-m") . "<br>";

    // variables to track stats
    $numengaged = 0;
    $numrovers = 0;

    foreach($members as $patrol) {
        
        echo "<br>";
        $isFirst = true;

        foreach($patrol as $x => $x_value) {

        	// print out the department name
            if ($isFirst) {

            	echo "<strong>" . $x . "</strong>" . "<br>";

            } else {

	            echo $x . ": ";

	            for($i = 25; $i > strlen($x); $i--) {
	                echo "&nbsp;";
	            }

	            echo $x_value . "<br>";

	            $numrovers++;
	            if ($x_value >= 2) { // rovers count as engaged if they have performed at least two actions
	                $numengaged++;

	            }
        	}
        	$isFirst = false;
        }
    }

    // print stats
    echo "<br>Active Rovers (2+ Actions): " . $numengaged . "/" . $numrovers . "=" . number_format($numengaged/$numrovers*100, 2, '.', ',')   . "%";
    echo "</div>";
    echo "</html>";
        
    } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {

        // Failed to get the access token or user details.
        exit($e->getMessage());

    }

}

?>
