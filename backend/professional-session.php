<?php

session_start();

header("Content-Type: application/json");


/*
|--------------------------------------------------------------------------
| Check professional session
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
        "message" => "Professional session is not active."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Allowed roles
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

    session_unset();
    session_destroy();

    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "Professional account is not authorized."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Return verified professional
|--------------------------------------------------------------------------
*/

echo json_encode([
    "success" => true,
    "professional" => [
        "id" => $_SESSION["professional_id"],
        "name" => $_SESSION["professional_name"] ?? "",
        "role" => $_SESSION["professional_role"]
    ]
]);

?>