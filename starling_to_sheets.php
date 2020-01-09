<?php

// Gets transactions from Starling Bank for last month, and add them to a new tab on a Google Sheet
// so, essentially creating Google Sheets statements
// tom@tomroyal.com / @tomroyal / github.com/tomroyal

// Note - getClient code is straight from the Google API reference
// You will need to create client_secret.json - see https://developers.google.com/sheets/api/quickstart/php

// required config vars:
$spreadsheetId = ''; // Google Sheet ID; get it from the url of the document
$starlingToken = ''; // Starling bank token; get it from https://developer.starlingbank.com/token/list
$starlingAccUID = ''; // UID of account
$starlingDefCat = ''; // the default category, also returnd from the accounts endpoint

if(file_exists('./tomconfig.php')){
  // my config file, containing my api key etc
  include './tomconfig.php';
}

// Google Sheets oauth 
include('./vendor/autoload.php');
function getClient()
{
    $client = new Google_Client();
    $client->setApplicationName('Google Sheets API PHP Quickstart');
    $client->setScopes(Google_Service_Sheets::SPREADSHEETS);
    $client->setAuthConfig('client_secret.json');
    $client->setAccessType('offline');

    // Load previously authorized credentials from a file.
    $credentialsPath = expandHomeDirectory('credentials.json');
    if (file_exists($credentialsPath)) {
        $accessToken = json_decode(file_get_contents($credentialsPath), true);
    } else {
        // Request authorization from the user.
        $authUrl = $client->createAuthUrl();
        printf("Open the following link in your browser:\n%s\n", $authUrl);
        print 'Enter verification code: ';
        $authCode = trim(fgets(STDIN));

        // Exchange authorization code for an access token.
        $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

        // Store the credentials to disk.
        if (!file_exists(dirname($credentialsPath))) {
            mkdir(dirname($credentialsPath), 0700, true);
        }
        file_put_contents($credentialsPath, json_encode($accessToken));
        printf("Credentials saved to %s\n", $credentialsPath);
    }
    $client->setAccessToken($accessToken);

    // Refresh the token if it's expired.
    if ($client->isAccessTokenExpired()) {
        $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        file_put_contents($credentialsPath, json_encode($client->getAccessToken()));
    }
    return $client;
}
function expandHomeDirectory($path)
{
    $homeDirectory = getenv('HOME');
    if (empty($homeDirectory)) {
        $homeDirectory = getenv('HOMEDRIVE') . getenv('HOMEPATH');
    }
    return str_replace('~', realpath($homeDirectory), $path);
}

// work out month start/end dates, name for sheet tab
/*
$dfr = new DateTime("first day of last month");
$dt = new DateTime("last day of last month");
$df = $dfr->format('Y-m-d').'T00:00:00.000Z';
$dt = $dt->format('Y-m-d').'T23:59:59.000Z'';
$tabname = $dfr->format('M Y'); 
*/

$df = '2019-12-01T00:00:00.000Z';
$dt = '2019-12-31T23:59:59.000Z';
$tabname = 'Dec 2019'; 

// do starling api call, now with v2
$curl_url = 'https://api.starlingbank.com/api/v2/feed/account/'.$starlingAccUID.'/category/'.$starlingDefCat.'/transactions-between?minTransactionTimestamp='.$df.'&maxTransactionTimestamp='.$dt;

$ch = curl_init($curl_url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Authorization: Bearer '.$starlingToken.''
  )
);
$stresult = curl_exec($ch);
$sbresult = json_decode($stresult);

// stop if no transactions
if (count($sbresult->feedItems) == 0){
  // no transactions to process here..
  echo('no results');
  die;
}

// connect to sheet and set tab
try {
  $client = getClient();
  $service = new Google_Service_Sheets($client);
  $addsheet = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest(array('requests' => array('addSheet' => array('properties' => array('title' => $tabname )))));
  $result = $service->spreadsheets->batchUpdate($spreadsheetId,$addsheet);
} 
catch (Exception $e){
  print_r($e);
  die;
};

// now add transactions - flip array first so it runs chronologically
$rowcounter = 1;
$transactions_reversed = array_reverse($sbresult->feedItems);
foreach ($transactions_reversed as $atransaction){
  $range = $tabname.'!A'.$rowcounter.':E'.$rowcounter;
  if ($atransaction->direction == 'OUT'){
    $direction_indicator = '-';
  }
  else {
    $direction_indicator = '';
  }
  $values = [
      [$atransaction->amount->currency,$direction_indicator.($atransaction->amount->minorUnits/100),$atransaction->transactionTime,$atransaction->source,$atransaction->counterPartyName]
  ];  
  $requestBody = new Google_Service_Sheets_ValueRange([
      'range' => $range,
      'majorDimension' => 'ROWS',
      'values' => $values,
  ]);  
  $response = $service->spreadsheets_values->update($spreadsheetId, $range, $requestBody, ['valueInputOption' => 'USER_ENTERED']);  
  $rowcounter++;
};

?>
