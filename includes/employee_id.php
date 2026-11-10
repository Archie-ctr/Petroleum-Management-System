<?php

function assignEmployeeIdIfMissing(mysqli $conn, int $userId): ?string
{
    $stmt = $conn->prepare(
        'SELECT u.employee_id, u.role, u.created_at, b.branch_name
         FROM users u
         LEFT JOIN branches b ON b.id = u.branch_id
         WHERE u.id = ?
         LIMIT 1'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        throw new RuntimeException('Could not assign an employee ID because the user was not found.');
    }

    if (!empty($user['employee_id'])) {
        return $user['employee_id'];
    }
    if ($user['role'] === 'admin') {
        return null;
    }
    if (empty($user['branch_name'])) {
        throw new RuntimeException('Assign a branch before generating an employee ID.');
    }

    $branchInitial = strtoupper(substr(trim((string)$user['branch_name']), 0, 1));
    if (!preg_match('/[A-Z0-9]/', $branchInitial)) {
        $branchInitial = 'B';
    }
    $registeredYear = date('Y', strtotime((string)$user['created_at']));
    $employeeId = sprintf('%s-%s-%06d', $branchInitial, $registeredYear, $userId);

    $update = $conn->prepare(
        "UPDATE users SET employee_id = ?
         WHERE id = ? AND (employee_id IS NULL OR employee_id = '')"
    );
    $update->bind_param('si', $employeeId, $userId);
    $update->execute();
    $assigned = $update->affected_rows === 1;
    $update->close();

    if ($assigned) {
        return $employeeId;
    }

    $check = $conn->prepare('SELECT employee_id FROM users WHERE id = ? LIMIT 1');
    $check->bind_param('i', $userId);
    $check->execute();
    $currentId = $check->get_result()->fetch_assoc()['employee_id'] ?? null;
    $check->close();
    if (is_string($currentId) && $currentId !== '') {
        return $currentId;
    }

    throw new RuntimeException('The employee ID could not be saved.');
}
