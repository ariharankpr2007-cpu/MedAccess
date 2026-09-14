<?php

header("Content-Type: application/json");

require_once "config.php";

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
| Temporary access requests
|--------------------------------------------------------------------------
*/

$accessQuery = $pdo->prepare(
    "SELECT
        id,
        accessor_type,
        access_method,
        reason,
        accessed_at,
        expires_at
     FROM emergency_access
     WHERE emergency_id = ?
     ORDER BY accessed_at DESC"
);

$accessQuery->execute([$emergencyId]);

$accessRecords = $accessQuery->fetchAll();


/*
|--------------------------------------------------------------------------
| Audit events
|--------------------------------------------------------------------------
*/

$auditQuery = $pdo->prepare(
    "SELECT
        id,
        action,
        accessor,
        ip_address,
        created_at
     FROM audit_logs
     WHERE emergency_id = ?
     ORDER BY created_at DESC"
);

$auditQuery->execute([$emergencyId]);

$auditRecords = $auditQuery->fetchAll();


/*
|--------------------------------------------------------------------------
| Build unified history
|--------------------------------------------------------------------------
*/

$history = [];


/*
| Temporary access records
*/

foreach ($accessRecords as $record) {

    $status =
        strtotime($record["expires_at"]) > time()
        ? "ACTIVE"
        : "EXPIRED";

    $history[] = [

        "type" => "TEMPORARY_ACCESS",

        "title" =>
            "Temporary emergency access",

        "accessor" =>
            $record["accessor_type"],

        "method" =>
            $record["access_method"],

        "reason" =>
            $record["reason"],

        "timestamp" =>
            $record["accessed_at"],

        "expires_at" =>
            $record["expires_at"],

        "status" =>
            $status

    ];
}


/*
| Audit records
*/

foreach ($auditRecords as $record) {

    $title = "Emergency activity";

    $method = "SYSTEM";

    $accessor = $record["accessor"];


    if ($record["action"] === "SMS_EMERGENCY_LOOKUP") {

        $title = "Emergency information accessed via SMS";
        $method = "SMS";

    }

    elseif ($record["action"] === "IVR_EMERGENCY_LOOKUP") {

        $title = "Emergency information accessed via IVR";
        $method = "IVR";

    }

    elseif ($record["action"] === "PROTECTED_RECORDS_VIEWED") {

        $title = "Protected medical records viewed";
        $method = "WEB";

    }

    elseif ($record["action"] === "EMERGENCY_ACCESS_REQUESTED") {

        $title = "Emergency access requested";
        $method = "WEB";

    }


    $history[] = [

        "type" => "AUDIT",

        "title" => $title,

        "accessor" => $accessor,

        "method" => $method,

        "reason" => "",

        "timestamp" => $record["created_at"],

        "expires_at" => null,

        "status" => "RECORDED"

    ];
}


/*
|--------------------------------------------------------------------------
| Sort newest first
|--------------------------------------------------------------------------
*/

usort(
    $history,
    function ($a, $b) {

        return strtotime($b["timestamp"])
            <=> strtotime($a["timestamp"]);

    }
);


echo json_encode([

    "success" => true,

    "history" => $history

]);

?>