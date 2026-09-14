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

    $data = json_decode(file_get_contents("php://input"), true);

    $emergency_id = trim($data["emergency_id"] ?? "");
    $access_id = trim($data["access_id"] ?? "");
    $incident_type = trim($data["incident_type"] ?? "");
    $location = trim($data["location"] ?? "");
    $eta_minutes = intval($data["eta_minutes"] ?? 0);
    $notes = trim($data["notes"] ?? "");

    if ($emergency_id === "") {
        echo json_encode([
            "success" => false,
            "message" => "Emergency ID is required."
        ]);
        exit;
    }

    /*
     * Verify that the emergency profile exists.
     */
    $stmt = $pdo->prepare("
    SELECT 
        ep.emergency_id,
        ep.user_id,
        u.full_name
    FROM emergency_profiles ep
    JOIN users u ON ep.user_id = u.id
    WHERE ep.emergency_id = ?
    LIMIT 1
");

    $stmt->execute([$emergency_id]);

    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        echo json_encode([
            "success" => false,
            "message" => "Emergency profile not found."
        ]);
        exit;
    }

    /*
     * If an access ID was supplied, verify that it exists.
     */
    if ($access_id !== "") {

        $accessStmt = $pdo->prepare("
            SELECT id
            FROM emergency_access
            WHERE id = ?
              AND emergency_id = ?
              AND expires_at > NOW()
            LIMIT 1
        ");

        $accessStmt->execute([
            $access_id,
            $emergency_id
        ]);

        if (!$accessStmt->fetch()) {

            echo json_encode([
                "success" => false,
                "message" => "Temporary emergency access is invalid or expired."
            ]);

            exit;
        }
    }

    /*
     * Create the emergency case.
     */
    $case_id = "CASE-" . date("YmdHis") . "-" . random_int(100, 999);

    $stmt = $pdo->prepare("
        INSERT INTO emergency_cases
        (
            case_id,
            emergency_id,
            access_id,
            incident_type,
            location,
            eta_minutes,
            notes,
            status,
            created_at,
            updated_at
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            'IN_TRANSIT',
            NOW(),
            NOW()
        )
    ");

    $stmt->execute([
        $case_id,
        $emergency_id,
        $access_id !== "" ? $access_id : null,
        $incident_type,
        $location,
        $eta_minutes,
        $notes
    ]);

    /*
     * Add an audit entry.
     */
    $auditStmt = $pdo->prepare("
        INSERT INTO audit_logs
        (
            emergency_id,
            action,
            accessor,
            ip_address,
            created_at
        )
        VALUES
        (
            ?,
            'EMERGENCY_CASE_CREATED',
            'PARAMEDIC',
            ?,
            NOW()
        )
    ");

    $auditStmt->execute([
        $emergency_id,
        $_SERVER["REMOTE_ADDR"] ?? "UNKNOWN"
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Emergency case created successfully.",
        "case" => [
            "case_id" => $case_id,
            "emergency_id" => $emergency_id,
            "patient_name" => $patient["full_name"],
            "incident_type" => $incident_type,
            "location" => $location,
            "eta_minutes" => $eta_minutes,
            "status" => "IN_TRANSIT"
        ]
    ]);

} catch (Throwable $e) {

    echo json_encode([
        "success" => false,
        "message" => "Server error: " . $e->getMessage()
    ]);

    exit;
}
?>