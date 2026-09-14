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
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success"=>false,"message"=>"Invalid request method."]);
    exit;
}

$input=json_decode(file_get_contents("php://input"),true);
if (!$input) {
    http_response_code(400);
    echo json_encode(["success"=>false,"message"=>"Invalid request data."]);
    exit;
}

$userId=(int)$_SESSION["patient_user_id"];
$fullName=trim($input["full_name"] ?? "");
$phone=trim($input["phone"] ?? "");
$email=trim($input["email"] ?? "");
$bloodGroup=trim($input["blood_group"] ?? "");
$allergies=trim($input["allergies"] ?? "");
$conditions=trim($input["medical_conditions"] ?? "");
$medications=trim($input["critical_medications"] ?? "");
$contactName=trim($input["emergency_contact_name"] ?? "");
$contactPhone=trim($input["emergency_contact_phone"] ?? "");

if ($fullName==="" || !preg_match('/^[0-9]{10}$/',$phone) || $contactName==="" || !preg_match('/^[0-9]{10}$/',$contactPhone)) {
    http_response_code(422);
    echo json_encode(["success"=>false,"message"=>"Please provide a valid name, 10-digit phone numbers and emergency contact name."]);
    exit;
}
if ($email!=="" && !filter_var($email,FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(["success"=>false,"message"=>"Please enter a valid email address."]);
    exit;
}

$check=$pdo->prepare("SELECT id FROM users WHERE phone=? AND id<>? LIMIT 1");
$check->execute([$phone,$userId]);
if ($check->fetch()) {
    http_response_code(409);
    echo json_encode(["success"=>false,"message"=>"That phone number is already registered."]);
    exit;
}
if ($email!=="") {
    $check=$pdo->prepare("SELECT id FROM users WHERE email=? AND id<>? LIMIT 1");
    $check->execute([$email,$userId]);
    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode(["success"=>false,"message"=>"That email address is already registered."]);
        exit;
    }
}

try {
    $pdo->beginTransaction();

    $u=$pdo->prepare("UPDATE users SET full_name=?, phone=?, email=? WHERE id=?");
    $u->execute([$fullName,$phone,$email!==""?$email:null,$userId]);

    $p=$pdo->prepare("
        UPDATE emergency_profiles
        SET blood_group=?, allergies=?, medical_conditions=?, critical_medications=?,
            emergency_contact_name=?, emergency_contact_phone=?, updated_at=NOW()
        WHERE user_id=?
    ");
    $p->execute([$bloodGroup,$allergies,$conditions,$medications,$contactName,$contactPhone,$userId]);

    $q=$pdo->prepare("SELECT emergency_id FROM emergency_profiles WHERE user_id=? LIMIT 1");
    $q->execute([$userId]);
    $profile=$q->fetch();

    $audit=$pdo->prepare("INSERT INTO audit_logs (emergency_id, action, accessor, ip_address) VALUES (?, ?, ?, ?)");
    $audit->execute([$profile["emergency_id"],"PROFILE_UPDATED","PATIENT",$_SERVER["REMOTE_ADDR"] ?? null]);

    $pdo->commit();

    echo json_encode(["success"=>true,"message"=>"Emergency medical profile updated successfully.","emergency_id"=>$profile["emergency_id"]]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["success"=>false,"message"=>"Unable to update your profile."]);
}
?>
