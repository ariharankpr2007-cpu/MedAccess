<?php

header("Content-Type: application/json");

require_once "config.php";
require_once "exotel-config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "POST request required."
    ]);

    exit;
}

$input = json_decode(
    file_get_contents("php://input"),
    true
);

$to = trim($input["to"] ?? "");
$message = trim($input["message"] ?? "");

if ($to === "" || $message === "") {
    http_response_code(422);

    echo json_encode([
        "success" => false,
        "message" => "Phone number and message are required."
    ]);

    exit;
}

/*
 * Convert Indian numbers such as:
 * 9876543210
 * +919876543210
 * 919876543210
 *
 * into:
 * +919876543210
 */

$to = preg_replace("/\s+/", "", $to);

if (preg_match("/^[6-9][0-9]{9}$/", $to)) {
    $to = "+91" . $to;
}
elseif (preg_match("/^91[6-9][0-9]{9}$/", $to)) {
    $to = "+" . $to;
}

if (!preg_match("/^\+?[0-9]{10,15}$/", $to)) {
    http_response_code(422);

    echo json_encode([
        "success" => false,
        "message" => "Invalid phone number."
    ]);

    exit;
}

/*
 * IMPORTANT:
 * Put your ExoPhone here.
 *
 * Example:
 * +9180XXXXXXXX
 */

$virtualNumber = "08045691312";

if ($virtualNumber === "YOUR_EXOPHONE_HERE") {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "ExoPhone has not been configured."
    ]);

    exit;
}

/*
 * Exotel SMS API
 */

$url =
    $EXOTEL_BASE_URL .
    "/v1/Accounts/" .
    rawurlencode($EXOTEL_SID) .
    "/Sms/send.json";

$postData = http_build_query([
    "From" => $virtualNumber,
    "To" => $to,
    "Body" => $message
]);

$ch = curl_init($url);

curl_setopt_array($ch, [

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_POST => true,

    CURLOPT_POSTFIELDS => $postData,

    CURLOPT_USERPWD =>
        $EXOTEL_API_KEY .
        ":" .
        $EXOTEL_API_TOKEN,

    CURLOPT_HTTPHEADER => [
        "Content-Type: application/x-www-form-urlencoded"
    ],

    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);

$httpCode = curl_getinfo(
    $ch,
    CURLINFO_HTTP_CODE
);

$curlError = curl_error($ch);

curl_close($ch);

if ($curlError !== "") {

    http_response_code(502);

    echo json_encode([
        "success" => false,
        "message" => "Unable to connect to Exotel.",
        "error" => $curlError
    ]);

    exit;
}

echo json_encode([
    "success" => ($httpCode >= 200 && $httpCode < 300),
    "http_code" => $httpCode,
    "exotel_response" => $response
]);

?>