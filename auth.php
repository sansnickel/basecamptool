<?php

require "vendor/autoload.php";
session_start();
require "patrols.php"; // contains the auth details needed

$provider = new \League\OAuth2\Client\Provider\GenericProvider([
    'clientId'                => $clientId,    // The client ID assigned to you by the provider
    'clientSecret'            => $clientSecret,   // The client password assigned to you by the provider
    'redirectUri'             => 'http://pccrovers.com/tools/basecamp/auth.php',
    'urlAuthorize'            => 'https://launchpad.37signals.com/authorization/new?type=web_server',
    'urlAccessToken'          => 'https://launchpad.37signals.com/authorization/token?type=web_server',
    'urlResourceOwnerDetails' => 'https://launchpad.37signals.com/authorization.json'
]);

// If we don't have an authorization code then get one
if (!isset($_GET['code'])) {

    // Fetch the authorization URL from the provider; this returns the
    // urlAuthorize option and generates and applies any necessary parameters
    // (e.g. state).
    $authorizationUrl = $provider->getAuthorizationUrl();

    // Get the state generated for you and store it to the session.
    $_SESSION['oauth2state'] = $provider->getState();

    // Redirect the user to the authorization URL.
    header('Location: ' . $authorizationUrl);
    exit;

// Check given state against previously stored one to mitigate CSRF attack
} elseif (empty($_GET['state']) || (isset($_SESSION['oauth2state']) && $_GET['state'] !== $_SESSION['oauth2state'])) {

    if (isset($_SESSION['oauth2state'])) {
        unset($_SESSION['oauth2state']);
    }

    exit('Invalid state');

} else {

    try {

        // Try to get an access token using the authorization code grant.
        $accessToken = $provider->getAccessToken('authorization_code', [
            'code' => $_GET['code']
        ]);

        $_SESSION['token'] = $accessToken;
        // We have an access token, which we may use in authenticated
        // requests against the service provider's API.

        /*
        echo 'Access Token: ' . $accessToken->getToken() . "<br>";
        echo 'Refresh Token: ' . $accessToken->getRefreshToken() . "<br>";
        echo 'Expired in: ' . $accessToken->getExpires() . "<br>";
        echo 'Already expired? ' . ($accessToken->hasExpired() ? 'expired' : 'not expired') . "<br>";
        */

        // Using the access token, we may look up details about the
        // resource owner.
        $resourceOwner = $provider->getResourceOwner($accessToken);

        // The provider provides a way to get an authenticated API request for
        // the service, using the access token; it returns an object conforming
        // to Psr\Http\Message\RequestInterface.
        
        /*
        $request = $provider->getAuthenticatedRequest(
            'GET',
            'https://3.basecampapi.com/3797219/projects.json',
            $_SESSION['token']
        );

        $response = $provider->getParsedResponse($request);
                
        echo "<pre>"; 
        print_r($response); 
        echo "/<pre>"; 
        */
        header('Location: ' . 'events.php');
        exit;

    } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {

        // Failed to get the access token or user details.
        exit($e->getMessage());

    }

}

?>
