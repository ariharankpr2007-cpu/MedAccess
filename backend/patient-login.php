<?php
header("Content-Type: application/json");
require_once "config.php";

session_set_cookie_params([
    "httponly" => true,
    "samesite" => "Lax",
    "secure" => (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
]);
session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success"=>false,"message"=>"Invalid request method."]);
    exit;
}

$input=json_decode(file_get_contents("php://input"),true);
$phone=trim($input["phone"] ?? "");
$password=$input["password"] ?? "";

if (!preg_match('/^[0-9]{10}$/',$phone) || $password==="") {
    http_response_code(422);
    echo json_encode(["success"=>false,"message"=>"Enter your registered 10-digit mobile number and password."]);
    exit;
}

$stmt=$pdo->prepare("SELECT id, password_hash FROM users WHERE phone=? LIMIT 1");
$stmt->execute([$phone]);
$user=$stmt->fetch();

if (!$user || !password_verify($password,$user["password_hash"])) {
    http_response_code(401);
    echo json_encode(["success"=>false,"message"=>"Invalid mobile number or password."]);
    exit;
}

$profile=$pdo->prepare("SELECT emergency_id FROM emergency_profiles WHERE user_id=? LIMIT 1");
$profile->execute([$user["id"]]);
$ep=$profile->fetch();

if (!$ep) {
    http_response_code(404);
    echo json_encode(["success"=>false,"message"=>"Emergency profile not found."]);
    exit;
}

session_regenerate_id(true);
$_SESSION["patient_user_id"]=(int)$user["id"];
$_SESSION["patient_login_at"]=time();

echo json_encode([
    "success"=>true,
    "message"=>"Patient login successful.",
    "user_id"=>(int)$user["id"],
    "emergency_id"=>$ep["emergency_id"]
]);
?>
