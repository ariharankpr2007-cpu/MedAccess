<?php

session_start();

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
| Get login information
|--------------------------------------------------------------------------
*/

$professionalId =
    trim($input["professional_id"] ?? "");

$password =
    $input["password"] ?? "";


if (
    $professionalId === "" ||
    $password === ""
) {

    http_response_code(422);

    echo json_encode([
        "success" => false,
        "message" => "Professional ID and password are required."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Find professional account
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        professional_id,
        full_name,
        role,
        password_hash,
        account_status
    FROM professionals
    WHERE professional_id = ?
    LIMIT 1
");

$stmt->execute([
    $professionalId
]);

$professional =
    $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Verify account
|--------------------------------------------------------------------------
*/

if (
    !$professional ||
    $professional["account_status"] !== "ACTIVE" ||
    !password_verify(
        $password,
        $professional["password_hash"]
    )
) {

    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Invalid professional ID or password."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Create server-side session
|--------------------------------------------------------------------------
*/

session_regenerate_id(true);

$_SESSION["professional_authenticated"] = true;

$_SESSION["professional_db_id"] =
    $professional["id"];

$_SESSION["professional_id"] =
    $professional["professional_id"];

$_SESSION["professional_role"] =
    $professional["role"];

$_SESSION["professional_name"] =
    $professional["full_name"];


/*
|--------------------------------------------------------------------------
| Success
|--------------------------------------------------------------------------
*/

echo json_encode([
    "success" => true,
    "message" => "Professional authentication successful.",
    "professional" => [
        "id" => $professional["professional_id"],
        "name" => $professional["full_name"],
        "role" => $professional["role"]
    ]
]);

?>