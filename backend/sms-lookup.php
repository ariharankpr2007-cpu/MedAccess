<?php

header("Content-Type: application/json");

require_once "config.php";


/*
|--------------------------------------------------------------------------
| Accept POST only
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Read request
|--------------------------------------------------------------------------
*/

$input = json_decode(
    file_get_contents("php://input"),
    true
);


$emergencyId =
    trim($input["emergency_id"] ?? "");

$senderPhone =
    trim($input["sender_phone"] ?? "");


if ($emergencyId === "") {

    http_response_code(422);

    echo json_encode([
        "success" => false,
        "message" => "Emergency ID is required."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Find emergency profile
|--------------------------------------------------------------------------
*/

$query = $pdo->prepare(
    "SELECT
        u.full_name,

        ep.emergency_id,
        ep.blood_group,
        ep.allergies,
        ep.medical_conditions,

        ep.emergency_contact_name,
        ep.emergency_contact_phone

     FROM emergency_profiles ep

     INNER JOIN users u
        ON ep.user_id = u.id

     WHERE ep.emergency_id = ?
     AND ep.profile_status = 'active'

     LIMIT 1"
);


$query->execute([
    $emergencyId
]);


$profile =
    $query->fetch();


/*
|--------------------------------------------------------------------------
| Profile not found
|--------------------------------------------------------------------------
*/

if (!$profile) {

    http_response_code(404);

    echo json_encode([
        "success" => false,
        "message" => "Emergency profile not found."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Create SMS-safe response
|--------------------------------------------------------------------------
*/

$message =
    "MEDACCESS EMERGENCY\n" .
    "Patient: " .
    $profile["full_name"] .
    "\n" .
    "Emergency ID: " .
    $profile["emergency_id"] .
    "\n" .
    "Blood: " .
    ($profile["blood_group"] ?: "Unknown") .
    "\n" .
    "Critical Allergy: " .
    ($profile["allergies"] ?: "None reported") .
    "\n" .
    "Condition: " .
    ($profile["medical_conditions"] ?: "None reported") .
    "\n" .
    "Emergency Contact: " .
    ($profile["emergency_contact_name"] ?: "Not provided") .
    " - " .
    ($profile["emergency_contact_phone"] ?: "Not provided");


/*
|--------------------------------------------------------------------------
| Log SMS lookup
|--------------------------------------------------------------------------
*/

$auditQuery = $pdo->prepare(
    "INSERT INTO audit_logs
    (
        emergency_id,
        action,
        accessor,
        ip_address
    )
    VALUES (?, ?, ?, ?)"
);


$auditQuery->execute([
    $emergencyId,
    "SMS_EMERGENCY_LOOKUP",
    $senderPhone !== ""
        ? "SMS:" . $senderPhone
        : "SMS_USER",
    $_SERVER["REMOTE_ADDR"] ?? null
]);


/*
|--------------------------------------------------------------------------
| Return simulated SMS
|--------------------------------------------------------------------------
*/

echo json_encode([
    "success" => true,
    "channel" => "SMS",
    "emergency_id" => $emergencyId,
    "message" => $message
]);

?>