<?php

header("Content-Type: application/json");
require_once "config.php";

try {

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode([
            "success" => false,
            "message" => "Only POST requests are allowed."
        ]);
        exit;
    }

    $data = json_decode(
        file_get_contents("php://input"),
        true
    );

    $case_id = trim($data["case_id"] ?? "");
    $location = trim($data["location"] ?? "");

    if ($case_id === "" || $location === "") {
        echo json_encode([
            "success" => false,
            "message" => "Case ID and location are required."
        ]);
        exit;
    }

    /* Check that the emergency case exists */

    $check = $pdo->prepare("
        SELECT case_id
        FROM emergency_cases
        WHERE case_id = ?
        LIMIT 1
    ");

    $check->execute([$case_id]);

    if (!$check->fetch()) {
        echo json_encode([
            "success" => false,
            "message" => "Emergency case not found."
        ]);
        exit;
    }

    /* Save the latest ambulance location */

    $stmt = $pdo->prepare("
        INSERT INTO ambulance_locations
        (case_id, location, recorded_at)
        VALUES (?, ?, NOW())
    ");

    $stmt->execute([
        $case_id,
        $location
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Ambulance location updated.",
        "location" => $location
    ]);

} catch (Throwable $e) {

    echo json_encode([
        "success" => false,
        "message" => "Unable to update ambulance location."
    ]);

    exit;
}

?>