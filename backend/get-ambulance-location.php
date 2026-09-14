<?php

header("Content-Type: application/json");
require_once "config.php";

try {

    $case_id = trim($_GET["case_id"] ?? "");

    if ($case_id === "") {
        echo json_encode([
            "success" => false,
            "message" => "Case ID is required."
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT
            case_id,
            location,
            recorded_at
        FROM ambulance_locations
        WHERE case_id = ?
        ORDER BY recorded_at DESC
        LIMIT 1
    ");

    $stmt->execute([$case_id]);

    $location = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$location) {
        echo json_encode([
            "success" => false,
            "message" => "No ambulance location available yet."
        ]);
        exit;
    }

    /*
     * Location is stored as:
     * latitude,longitude
     */

    $parts = explode(",", $location["location"]);

    if (count($parts) !== 2) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid location data."
        ]);
        exit;
    }

    echo json_encode([
        "success" => true,
        "case_id" => $location["case_id"],
        "latitude" => (float) trim($parts[0]),
        "longitude" => (float) trim($parts[1]),
        "recorded_at" => $location["recorded_at"]
    ]);

} catch (Throwable $e) {

    echo json_encode([
        "success" => false,
        "message" => "Unable to retrieve ambulance location."
    ]);

    exit;
}
?>