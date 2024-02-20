<?php
require __DIR__ . '/vendor/autoload.php';
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

// Load environment variables from .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$defaultHeader = [
    'Accept' => 'application/json',
    'Content-Type' => "application/json",
    'api-version' => '1.0',
    'x-api-key' => $_ENV['API_KEY']
];

// Call Incode's `omni/start` API to create an Incode session which will include a
// token in the JSON response.
function start() {
    global $defaultHeader;
    $body = [
        'configurationId' => $_ENV['FLOW_ID'],
        'countryCode' => 'ALL',
        'language' => 'en-US'
    ];

    $Client = new Client(['base_uri' => $_ENV['API_URL']]);
    $Request = new Request('POST', '/omni/start', $defaultHeader, json_encode($body));
    $Response = $Client->sendAsync($Request)->wait();
    
    $Data = json_decode($Response->getBody());
    $session = ['token'=> $Data->token, 'interviewId' => $Data->interviewId];
    echo json_encode($session);
}

// Calls incodes `omni/start` and then with the token calls `0/omni/onboarding-url`
// to retrieve the unique onboarding-url for the newly created session.
function onboardingUrl() {
    global $defaultHeader;
    $body = [
        'configurationId' => $_ENV['FLOW_ID'],
        'countryCode' => 'ALL',
        'language' => 'en-US',
        // 'redirectionUrl' => 'https://example.com?custom_parameter=some+value',
        // 'externalCustomerId' => 'the id of the customer in your system',
    ];

    $Client = new Client(['base_uri' => $_ENV['API_URL']]);
    $Request = new Request('POST', '/omni/start', $defaultHeader, json_encode($body));
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
    var_dump($data);
    // Process received data (for demonstration, just returning the received payload
    // and include the timestamp)
    $response = array(
        'timestamp' => date("Y-m-d H:i:s"),
        'success' => true,
        'data' => $data
    );
    echo json_encode($response);

    // Write to a log so you can debug it. Use the command `tail -f debug.log` to watch the file in realtime.
    file_put_contents('debug.log', json_encode($response, JSON_PRETTY_PRINT)."\n", FILE_APPEND | LOCK_EX);
}

// Webhook to receive onboarding status, configure it in
// incode dasboard > settings > webhook > onboarding status
// This endpoint will auto-approve(create an identity) for
// any sessions that PASS.
function approve() {
    // We receive raw json data
    $payload = file_get_contents('php://input');
    $data = json_decode($payload, true); // Decode JSON payload
    if($data['onboardingStatus']=="ONBOARDING_FINISHED"){
        $Client = new Client(['base_uri' => $_ENV['API_URL']]);
        // Admin Token + ApiKey are needed for approving and fetching scores
        $adminHeaders = [
            'Content-Type' => "application/json",
            'x-api-key' => $_ENV['API_KEY'],
            'X-Incode-Hardware-Id' => $_ENV['ADMIN_TOKEN'],
            'api-version' => '1.0'
        ];
        $scoreUrl='/omni/get/score?id='.urlEncode($data['interviewId']);
        $Request = new Request('GET', $scoreUrl, $adminHeaders);
        $Response = $Client->sendAsync($Request)->wait();
        $onboardingScore = json_decode($Response->getBody());

        if($onboardingScore->overall->status==='OK'){
            $approveURL='/omni/process/approve?interviewId='.urlEncode($data['interviewId']);
            $Request = new Request('POST', $approveURL, $adminHeaders);
            $Response = $Client->sendAsync($Request)->wait();
            $identityData = json_decode($Response->getBody());

            $response = array(
                'timestamp' => date("Y-m-d H:i:s"),
                'success' => true,
                'data' => $identityData
            );
            // This would return something like this:
            // {
            //   timestamp: '2024-01-04 00:38:28',
            //   success: true,
            //   data: {
            //     success: true,
            //     uuid: '6595c84ce69d469f69ad39fb',
            //     token: 'eyJhbGciOiJ4UzI1NiJ9.eyJleHRlcm5hbFVzZXJJZCI6IjY1OTVjODRjZTY5ZDk2OWY2OWF33kMjlmYiIsInJvbGUiOiJBQ0NFU5MiLCJrZXlSZWYiOiI2MmZlNjQ3ZTJjODJlOTVhZDNhZTRjMzkiLCJleHAiOjE3MTIxOTExMDksImlhdCI6MTcwNDMyODcwOX0.fbhlcTQrp-h-spgxKU2J7wpEBN4I4iOYG5CBwuQKPLQ72',
            //     totalScore: 'OK',
            //     existingCustomer: false
            //   }
            // }
            // UUID: You can save the generated uuid of your user to link your user with our systems.
            // Token: Is long lived and could be used to do calls in the name of the user if needed.
            // Existing Customer: Will return true in case the user was already in the database, in such case we are returning the UUID of the already existing user.  
            echo json_encode($response);
        } else {
            $response = array(
                'timestamp' => date("Y-m-d H:i:s"),
                'success' => false,
                'error' => "Session didn't PASS, identity was not created"
            );
            echo json_encode($response);
        }
    } else {
        // Process received data (for demonstration, just returning the received payload
        // and include the timestamp)
        $response = array(
            'timestamp' => date("Y-m-d H:i:s"),
            'success' => true,
            'data' => $data
        );
        echo json_encode($response);
    }
    // Write to a log so you can debug it. Use the command `tail -f debug.log` to watch the file in realtime.
    file_put_contents('debug.log', json_encode($response, JSON_PRETTY_PRINT)."\n", FILE_APPEND | LOCK_EX);
}

//  Receives the information about a faceMatch attempt and verifies if it was correct and has not been tampered.
function auth() {
    global $defaultHeader;
    // We receive raw json data
    $payload = file_get_contents('php://input');
    $data = json_decode($payload, true); // Decode JSON payload

    $body = [
        'transactionId' => $data["transactionId"],
        'token' => $data["token"],
        'interviewToken' => $data["interviewToken"],
    ];

    $Client = new Client(['base_uri' => $_ENV['API_URL']]);
    $Request = new Request('POST', '/omni/auth-attempt/verify', $defaultHeader, json_encode($body));
    $Response = $Client->sendAsync($Request)->wait();
    
    $Data = json_decode($Response->getBody());
    echo json_encode($Data);
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
    } elseif ( str_starts_with($_SERVER['REQUEST_URI'],'/approve') ) {
        approve();
        exit(0);
    } elseif ( str_starts_with($_SERVER['REQUEST_URI'],'/auth') ) {
        auth();
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
