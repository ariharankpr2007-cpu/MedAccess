<?php

header("Content-Type: application/json");

require_once "config.php";


/*
|--------------------------------------------------------------------------
| Only accept POST requests
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
| Read JSON request
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
| Collect form data
|--------------------------------------------------------------------------
*/

$fullName = trim($input["full_name"] ?? "");
$phone = trim($input["phone"] ?? "");
$email = trim($input["email"] ?? "");
$password = $input["password"] ?? "";

$bloodGroup = trim($input["blood_group"] ?? "");
$allergies = trim($input["allergies"] ?? "");
$conditions = trim($input["medical_conditions"] ?? "");
$medications = trim($input["critical_medications"] ?? "");

$contactName = trim(
    $input["emergency_contact_name"] ?? ""
);

$contactPhone = trim(
    $input["emergency_contact_phone"] ?? ""
);


/*
|--------------------------------------------------------------------------
| Basic validation
|--------------------------------------------------------------------------
*/

if (
    $fullName === "" ||
    $phone === "" ||
    $password === ""
) {

    http_response_code(422);

    echo json_encode([
        "success" => false,
        "message" => "Name, phone and password are required."
    ]);

    exit;
}


if (strlen($password) < 6) {

    http_response_code(422);

    echo json_encode([
        "success" => false,
        "message" => "Password must contain at least 6 characters."
    ]);

    exit;
}


if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {

    http_response_code(422);

    echo json_encode([
        "success" => false,
        "message" => "Please enter a valid email address."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Check whether phone already exists
|--------------------------------------------------------------------------
*/

$checkPhone = $pdo->prepare(
    "SELECT id FROM users WHERE phone = ? LIMIT 1"
);

$checkPhone->execute([$phone]);


if ($checkPhone->fetch()) {

    http_response_code(409);

    echo json_encode([
        "success" => false,
        "message" => "This phone number is already registered."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Check email if provided
|--------------------------------------------------------------------------
*/

if ($email !== "") {

    $checkEmail = $pdo->prepare(
        "SELECT id FROM users WHERE email = ? LIMIT 1"
    );

    $checkEmail->execute([$email]);


    if ($checkEmail->fetch()) {

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "This email address is already registered."
        ]);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Generate secure password hash
|--------------------------------------------------------------------------
*/

$passwordHash = password_hash(
    $password,
    PASSWORD_DEFAULT
);


/*
|--------------------------------------------------------------------------
| Generate Emergency ID
|--------------------------------------------------------------------------
*/

function generateEmergencyId($pdo)
{
    do {

        $emergencyId =
            "MED-" .
            random_int(1000, 9999) .
            "-" .
            random_int(10, 99);

        $check = $pdo->prepare(
            "SELECT id
             FROM emergency_profiles
             WHERE emergency_id = ?
             LIMIT 1"
        );

        $check->execute([$emergencyId]);

    } while ($check->fetch());


    return $emergencyId;
}


$emergencyId = generateEmergencyId($pdo);


/*
|--------------------------------------------------------------------------
| Insert user + emergency profile
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    /*
    | Create user
    */

    $userQuery = $pdo->prepare(
        "INSERT INTO users
        (
            full_name,
            phone,
            email,
            password_hash
        )
        VALUES (?, ?, ?, ?)"
    );


    $userQuery->execute([
        $fullName,
        $phone,
        $email !== "" ? $email : null,
        $passwordHash
    ]);


    $userId = $pdo->lastInsertId();


    /*
    | Create emergency profile
    */

    $profileQuery = $pdo->prepare(
        "INSERT INTO emergency_profiles
        (
            user_id,
            emergency_id,
            blood_group,
            allergies,
            medical_conditions,
            critical_medications,
            emergency_contact_name,
            emergency_contact_phone
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );


    $profileQuery->execute([
        $userId,
        $emergencyId,
        $bloodGroup,
        $allergies,
        $conditions,
        $medications,
        $contactName,
        $contactPhone
    ]);


    /*
    | Record creation in audit log
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
        "PROFILE_CREATED",
        "PATIENT",
        $_SERVER["REMOTE_ADDR"] ?? null
    ]);


    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Start patient session after registration
    |--------------------------------------------------------------------------
    */
    session_set_cookie_params([
        "httponly" => true,
        "samesite" => "Lax",
        "secure" => (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
    ]);
    session_start();
    session_regenerate_id(true);
    $_SESSION["patient_user_id"] = (int)$userId;
    $_SESSION["patient_login_at"] = time();

    /*
    |--------------------------------------------------------------------------
    | Success response
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        "success" => true,
        "message" => "Emergency profile created successfully.",
        "emergency_id" => $emergencyId,
        "user_id" => $userId
    ]);

} catch (Exception $e) {

    if ($pdo->inTransaction()) {

        $pdo->rollBack();

    }


    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to create emergency profile."
    ]);

}