<?php

header("Content-Type: application/json");

require_once "config.php";


/*
|--------------------------------------------------------------------------
| Get Emergency ID
|--------------------------------------------------------------------------
*/

$emergencyId = trim($_GET["emergency_id"] ?? "");


if ($emergencyId === "") {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Emergency ID is required."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Find patient profile
|--------------------------------------------------------------------------
*/

$query = $pdo->prepare(
    "SELECT
        u.id AS user_id,
        u.full_name,
        u.phone,
        u.email,

        ep.emergency_id,
        ep.blood_group,
        ep.allergies,
        ep.medical_conditions,
        ep.critical_medications,

        ep.emergency_contact_name,
        ep.emergency_contact_phone,

        ep.profile_status,
        ep.created_at,
        ep.updated_at

    FROM emergency_profiles ep

    INNER JOIN users u
        ON ep.user_id = u.id

    WHERE ep.emergency_id = ?

    LIMIT 1"
);


$query->execute([$emergencyId]);

$profile = $query->fetch();


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
| Success
|--------------------------------------------------------------------------
*/

echo json_encode([
    "success" => true,
    "profile" => $profile
]);

?>