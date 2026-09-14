<?php

header("Content-Type: application/json");

require_once "config.php";

try {

    $stmt = $pdo->query("
        SELECT
            ec.case_id,
            ec.emergency_id,
            ec.incident_type,
            ec.location,
            ec.eta_minutes,
            ec.notes,
            ec.status,
            ec.created_at,
            ec.updated_at,

            ep.blood_group,
            ep.allergies,
            ep.medical_conditions,
            ep.critical_medications,

            u.full_name

        FROM emergency_cases ec

        JOIN emergency_profiles ep
            ON ec.emergency_id = ep.emergency_id

        JOIN users u
            ON ep.user_id = u.id

        ORDER BY ec.created_at DESC
    ");

    $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "cases" => $cases
    ]);

} catch (Throwable $e) {

    echo json_encode([
        "success" => false,
        "message" => "Unable to load emergency cases.",
        "error" => $e->getMessage()
    ]);

}
?>