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
    $status = trim($data["status"] ?? "");

    if ($case_id === "" || $status === "") {
        echo json_encode([
            "success" => false,
            "message" => "Case ID and status are required."
        ]);
        exit;
    }

    /*
     * Allowed emergency workflow statuses
     */
    $allowedStatuses = [
        "IN_TRANSIT",
        "RECEIVED",
        "PREPARING",
        "READY",
        "COMPLETED"
    ];

    if (!in_array($status, $allowedStatuses, true)) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid emergency status."
        ]);
        exit;
    }

    /*
     * Find the emergency case
     */
    $check = $pdo->prepare("
        SELECT
            case_id,
            emergency_id,
            status
        FROM emergency_cases
        WHERE case_id = ?
        LIMIT 1
    ");

    $check->execute([$case_id]);

    $case = $check->fetch(PDO::FETCH_ASSOC);

    if (!$case) {
        echo json_encode([
            "success" => false,
            "message" => "Emergency case not found."
        ]);
        exit;
    }

    $oldStatus = $case["status"];

    /*
     * Prevent unnecessary status updates
     */
    if ($oldStatus === $status) {
        echo json_encode([
            "success" => false,
            "message" => "Case is already marked as " . $status . "."
        ]);
        exit;
    }

    /*
     * Define the valid emergency workflow
     *
     * IN_TRANSIT → RECEIVED
     * RECEIVED   → PREPARING
     * PREPARING  → READY
     * READY      → COMPLETED
     */
    $validTransitions = [
        "IN_TRANSIT" => ["RECEIVED"],
        "RECEIVED"   => ["PREPARING"],
        "PREPARING"  => ["READY"],
        "READY"      => ["COMPLETED"],
        "COMPLETED" => []
    ];

    /*
     * Check whether requested transition is valid
     */
    if (
        !isset($validTransitions[$oldStatus]) ||
        !in_array(
            $status,
            $validTransitions[$oldStatus],
            true
        )
    ) {
        echo json_encode([
            "success" => false,
            "message" =>
                "Invalid status transition: " .
                $oldStatus .
                " → " .
                $status
        ]);
        exit;
    }

    /*
     * Update emergency case
     */
    $stmt = $pdo->prepare("
        UPDATE emergency_cases
        SET
            status = ?,
            updated_at = NOW()
        WHERE case_id = ?
    ");

    $stmt->execute([
        $status,
        $case_id
    ]);

    /*
     * Record status change in audit log
     */
    $audit = $pdo->prepare("
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
            ?,
            'HOSPITAL',
            ?,
            NOW()
        )
    ");

    $audit->execute([
        $case["emergency_id"],
        "EMERGENCY_CASE_STATUS_" . $status,
        $_SERVER["REMOTE_ADDR"] ?? "UNKNOWN"
    ]);

    /*
     * Return success
     */
    echo json_encode([
        "success" => true,
        "message" => "Emergency case status updated.",
        "case_id" => $case_id,
        "old_status" => $oldStatus,
        "status" => $status
    ]);

} catch (Throwable $e) {

    echo json_encode([
        "success" => false,
        "message" => "Unable to update emergency case."
    ]);

    exit;
}
?>