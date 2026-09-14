<?php
header("Content-Type: application/json");
require_once "config.php";

session_set_cookie_params([
    "httponly" => true,
    "samesite" => "Lax",
    "secure" => (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
]);
session_start();

if (empty($_SESSION["patient_user_id"])) {
    http_response_code(401);
    echo json_encode(["success"=>false,"message"=>"Patient session is not active."]);
    exit;
}

$stmt=$pdo->prepare("
    SELECT u.id AS user_id, u.full_name, u.phone, u.email,
           ep.emergency_id, ep.blood_group, ep.allergies,
           ep.medical_conditions, ep.critical_medications,
           ep.emergency_contact_name, ep.emergency_contact_phone,
           ep.profile_status, ep.updated_at
    FROM users u
    INNER JOIN emergency_profiles ep ON ep.user_id=u.id
    WHERE u.id=? LIMIT 1
");
$stmt->execute([$_SESSION["patient_user_id"]]);
$data=$stmt->fetch();

if (!$data) {
    session_unset();
    session_destroy();
    http_response_code(401);
    echo json_encode(["success"=>false,"message"=>"Patient profile was not found."]);
    exit;
}

echo json_encode([
    "success"=>true,
    "patient"=>[
        "user_id"=>$data["user_id"],
        "full_name"=>$data["full_name"],
        "phone"=>$data["phone"],
        "email"=>$data["email"]
    ],
    "profile"=>$data
]);
?>
