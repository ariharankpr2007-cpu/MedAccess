<?php
header("Content-Type: application/json");
require_once "config.php";

session_set_cookie_params([
    "httponly" => true,
    "samesite" => "Lax",
    "secure" => (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
]);
session_start();

$_SESSION=[];
if (ini_get("session.use_cookies")) {
    $params=session_get_cookie_params();
    setcookie(session_name(),"",time()-42000,$params["path"],$params["domain"],$params["secure"],$params["httponly"]);
}
session_destroy();

echo json_encode(["success"=>true,"message"=>"Patient logged out."]);
?>
