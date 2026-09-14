<?php

session_start();

header("Content-Type: application/json");

require_once "config.php";

/*
|--------------------------------------------------------------------------
| Verify authenticated professional session
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION["professional_authenticated"]) ||
    $_SESSION["professional_authenticated"] !== true ||
    empty($_SESSION["professional_db_id"]) ||
    empty($_SESSION["professional_id"]) ||
    empty($_SESSION["professional_role"])
) {

    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" =>
            "Professional authentication is required."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Get verified professional identity
|--------------------------------------------------------------------------
*/

$verifiedProfessionalId =
    $_SESSION["professional_id"];

$verifiedProfessionalRole =
    $_SESSION["professional_role"];

$verifiedProfessionalName =
    $_SESSION["professional_name"] ?? "";


/*
|--------------------------------------------------------------------------
| Allowed professional roles
|--------------------------------------------------------------------------
*/

$allowedProfessionalTypes = [
    "PARAMEDIC",
    "DOCTOR",
    "HOSPITAL_STAFF"
];


if (
    !in_array(
        $verifiedProfessionalRole,
        $allowedProfessionalTypes,
        true
    )
) {

    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" =>
            "This professional account is not authorized."
    ]);

    exit;
}


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
| Read JSON
|--------------------------------------------------------------------------
*/

$input = json_decode(
    file_get_contents("php://input"),
    true
);


if (!$input) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid request data."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Get request information
|--------------------------------------------------------------------------
*/

$emergencyId =
    trim($input["emergency_id"] ?? "");

$reason =
    trim($input["reason"] ?? "");

$details =
    trim($input["details"] ?? "");

$accessorType =
    $verifiedProfessionalRole;

$accessMethod =
    "PROFESSIONAL_PORTAL";

    /*
|--------------------------------------------------------------------------
| PROFESSIONAL ACCESS SECURITY
|--------------------------------------------------------------------------
|
| Protected medical records can only be accessed
| through the authorized professional portal.
|
*/

$allowedProfessionalTypes = [
    "PARAMEDIC",
    "DOCTOR",
    "HOSPITAL_STAFF"
];

if (
    $accessMethod !== "PROFESSIONAL_PORTAL" ||
    !in_array(
        $accessorType,
        $allowedProfessionalTypes,
        true
    )
) {

    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" =>
            "Authorized professional access is required."
    ]);

    exit;
}


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


if ($reason === "") {

    http_response_code(422);

    echo json_encode([
        "success" => false,
        "message" => "Reason for access is required."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Verify emergency profile exists
|--------------------------------------------------------------------------
*/

$profileQuery = $pdo->prepare(
    "SELECT id
     FROM emergency_profiles
     WHERE emergency_id = ?
     AND profile_status = 'active'
     LIMIT 1"
);

$profileQuery->execute([
    $emergencyId
]);


if (!$profileQuery->fetch()) {

    http_response_code(404);

    echo json_encode([
        "success" => false,
        "message" => "Emergency profile not found."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Temporary access
|--------------------------------------------------------------------------
|
| Demo duration: 10 minutes
|
*/

$expiresAt =
    date(
        "Y-m-d H:i:s",
        time() + (10 * 60)
    );


try {

    $pdo->beginTransaction();


    /*
    | Create emergency access record
    */

    $accessQuery = $pdo->prepare(
        "INSERT INTO emergency_access
        (
            emergency_id,
            accessor_type,
            access_method,
            reason,
            details,
            expires_at
        )
        VALUES (?, ?, ?, ?, ?, ?)"
    );


    $accessQuery->execute([
        $emergencyId,
        $accessorType,
        $accessMethod,
        $reason,
        $details,
        $expiresAt
    ]);


    $accessId =
        $pdo->lastInsertId();


    /*
    | Add audit log
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
        "EMERGENCY_ACCESS_REQUESTED",
        $accessorType,
        $_SERVER["REMOTE_ADDR"] ?? null
    ]);


    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        "success" => true,
        "message" => "Emergency access recorded.",
        "access_id" => $accessId,
        "expires_at" => $expiresAt,
        "duration_minutes" => 10
    ]);


} catch (Exception $e) {

    if ($pdo->inTransaction()) {

        $pdo->rollBack();

    }


    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to record emergency access."
    ]);

}

?>