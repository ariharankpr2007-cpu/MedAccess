<?php

header("Content-Type: application/json");

require_once "config.php";


/*
|--------------------------------------------------------------------------
| Only POST requests
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
    strtoupper(
        trim(
            $input["emergency_id"] ?? ""
        )
    );

$callerPhone =
    trim(
        $input["caller_phone"] ?? ""
    );


/*
|--------------------------------------------------------------------------
| Validate
|--------------------------------------------------------------------------
*/

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
| Find profile
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
| Not found
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
| Log IVR access
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
    "IVR_EMERGENCY_LOOKUP",
    $callerPhone !== ""
        ? "IVR:" . $callerPhone
        : "IVR_USER",
    $_SERVER["REMOTE_ADDR"] ?? null
]);


/*
|--------------------------------------------------------------------------
| Return voice-menu data
|--------------------------------------------------------------------------
*/

echo json_encode([

    "success" => true,

    "patient_name" =>
        $profile["full_name"],

    "emergency_id" =>
        $profile["emergency_id"],

    "menu" => [

        "1" => [
            "title" => "Blood Group",
            "value" =>
                $profile["blood_group"]
                ?: "Unknown"
        ],

        "2" => [
            "title" => "Critical Allergy",
            "value" =>
                $profile["allergies"]
                ?: "None reported"
        ],

        "3" => [
            "title" => "Medical Conditions",
            "value" =>
                $profile["medical_conditions"]
                ?: "None reported"
        ],

        "4" => [
            "title" => "Emergency Contact",
            "value" =>
                $profile["emergency_contact_name"]
                . " - " .
                $profile["emergency_contact_phone"]
        ]

    ]

]);

?>