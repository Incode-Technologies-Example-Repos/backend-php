<?php
require __DIR__ . '/vendor/autoload.php';
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

// Load environment variables from .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Call Incode's `omni/start` API to create an Incode session which will include a
// token in the JSON response.
function start() {
    $header = [
        'Accept' => 'application/json',
        'Content-Type' => "application/json",
        'api-version' => '1.0',
        'x-api-key' => $_ENV['API_KEY']
    ];
    $body = [
        'configurationId' => $_ENV['FLOW_ID'],
        'countryCode' => 'ALL',
        'language' => 'en-US'
    ];

    $Client = new Client(['base_uri' => $_ENV['API_URL']]);
    $Request = new Request('POST', '/omni/start', $header, json_encode($body));
    $Response = $Client->sendAsync($Request)->wait();
    
    $Data = json_decode($Response->getBody());
    $session = ['token'=> $Data->token, 'interviewId' => $Data->interviewId];
    echo json_encode($session);
}

// Calls incodes `omni/start` and then with the token calls `0/omni/onboarding-url`
// to retrieve the unique onboarding-url for the newly created session.
function onboardingUrl() {
    $header = [
        'Accept' => 'application/json',
        'Content-Type' => "application/json",
        'api-version' => '1.0',
        'x-api-key' => $_ENV['API_KEY']
    ];
    $body = [
        'configurationId' => $_ENV['FLOW_ID'],
        'countryCode' => 'ALL',
        'language' => 'en-US'
    ];
    
    // Enable the server to receive the url to redirect at the end of the flow
    $redirectionUrl = isset($_GET['redirectionUrl'])?$_GET['redirectionUrl']:'';
    if($redirectionUrl !=='') { 
        $body['redirectionUrl'] = $redirectionUrl;
    }

    $Client = new Client(['base_uri' => $_ENV['API_URL']]);
    $Request = new Request('POST', '/omni/start', $header, json_encode($body));
    $Response = $Client->sendAsync($Request)->wait();
    $startData = json_decode($Response->getBody());
    
    $onboardingHeader = [
        'Accept' => 'application/json',
        'Content-Type'=> "application/json",
        'X-Incode-Hardware-Id'=> $startData->token,
        'api-version'=> '1.0',
        'query'=> 'clientId='.urlEncode($_ENV['CLIENT_ID'])
    ];

    $Request = new Request('GET', '/0/omni/onboarding-url', $onboardingHeader);
    $Response = $Client->sendAsync($Request)->wait();
    
    $OnboardingUrlData = json_decode($Response->getBody());
    $session = ['token'=> $startData->token, 'interviewId' => $startData->interviewId, 'url'=> $OnboardingUrlData->url];
    echo json_encode($session);
}

// Webhook to receive onboarding status, configure it in
// incode dasboard >settings > webhook >onboarding status
function webhook() {
    // We receive raw json data
    $payload = file_get_contents('php://input');
    $data = json_decode($payload, true); // Decode JSON payload
    
    // Process received data (for demonstration, just returning the received payload
    // and include the timestamp)
    $response = array(
        'timestamp' => date("Y-m-d H:i:s"),
        'data' => $data
    );
    echo json_encode($response);

    // Write to a log so you can debug it. Use the command `tail -f debug.log` to watch the file in realtime.
    file_put_contents('debug.log', json_encode($response, JSON_PRETTY_PRINT)."\n", FILE_APPEND | LOCK_EX);
}

// Allow from any origin
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 0');    // Do not cache
}

// All responses are in json
header('Content-Type: application/json');

// Main logic to handle requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ( str_starts_with($_SERVER['REQUEST_URI'], '/start') ) {
        start();
        exit(0);
    } elseif ( str_starts_with($_SERVER['REQUEST_URI'], '/onboarding-url') ) {
        onboardingUrl();
        exit(0);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ( str_starts_with($_SERVER['REQUEST_URI'],'/webhook') ) {
        webhook();
        exit(0);
    }
} else if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        // List only valid methods
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    exit(0);
}

// No method and uri not found
http_response_code(404);
header("Content-Type: application/json");
echo "{\"error\":\"Cannot {$_SERVER['REQUEST_METHOD']} {$_SERVER['REQUEST_URI']}\"}";
