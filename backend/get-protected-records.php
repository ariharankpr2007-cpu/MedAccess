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
| Verify professional role
|--------------------------------------------------------------------------
*/

$allowedRoles = [
    "PARAMEDIC",
    "DOCTOR",
    "HOSPITAL_STAFF"
];

if (
    !in_array(
        $_SESSION["professional_role"],
        $allowedRoles,
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


$emergencyId =
    trim($_GET["emergency_id"] ?? "");

$accessId =
    trim($_GET["access_id"] ?? "");


if (
    $emergencyId === "" ||
    $accessId === ""
) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Emergency ID and access ID are required."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Check active temporary access
|--------------------------------------------------------------------------
*/

$accessQuery = $pdo->prepare(
    "SELECT
        id,
        accessor_type,
        access_method,
        expires_at

     FROM emergency_access

     WHERE id = ?
     AND emergency_id = ?
     AND accessor_type = ?
     AND access_method = 'PROFESSIONAL_PORTAL'
     AND expires_at > NOW()

     LIMIT 1"
);


$accessQuery->execute([
    $accessId,
    $emergencyId,
    $_SESSION["professional_role"]
]);


$access = $accessQuery->fetch();


if (!$access) {

    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "Emergency access is invalid or has expired."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Get protected medical records
|--------------------------------------------------------------------------
*/

$profileQuery = $pdo->prepare(
    "SELECT
        u.full_name,
        ep.emergency_id

     FROM emergency_profiles ep

     INNER JOIN users u
        ON ep.user_id = u.id

     WHERE ep.emergency_id = ?

     LIMIT 1"
);


$profileQuery->execute([
    $emergencyId
]);


$profile = $profileQuery->fetch();


if (!$profile) {

    http_response_code(404);

    echo json_encode([
        "success" => false,
        "message" => "Patient profile not found."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Medical records
|--------------------------------------------------------------------------
*/

$recordsQuery = $pdo->prepare(
    "SELECT
        diagnosis,
        medication,
        hospital,
        doctor,
        record_date,
        notes

     FROM medical_records

     WHERE user_id = (
         SELECT user_id
         FROM emergency_profiles
         WHERE emergency_id = ?
         LIMIT 1
     )

     ORDER BY record_date DESC"
);


$recordsQuery->execute([
    $emergencyId
]);


$records =
    $recordsQuery->fetchAll();


/*
|--------------------------------------------------------------------------
| Audit log
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
    "PROTECTED_RECORDS_VIEWED",
    $_SESSION["professional_id"],
    $_SERVER["REMOTE_ADDR"] ?? null
]);


echo json_encode([
    "success" => true,

    "patient" => [
        "name" =>
            $profile["full_name"],

        "emergency_id" =>
            $profile["emergency_id"]
    ],

    "records" =>
        $records
]);

?>