<?php
require __DIR__ . '/database.php';

$email = 'department@lgu.gov.ph';
$plain = 'department123';
$role = 'department_head';
$first = 'Department';
$last = 'Head';
$hash = password_hash($plain, PASSWORD_DEFAULT);

$stmt = $db->prepare('SELECT id FROM employees WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if ($row) {
    $id = (int)$row['id'];
    $up = $db->prepare('UPDATE employees SET first_name=?, last_name=?, role=?, password=? WHERE id=?');
    $up->bind_param('ssssi', $first, $last, $role, $hash, $id);
    $ok = $up->execute();
    $up->close();
    echo $ok ? "UPDATED:$id\n" : "UPDATE_FAILED\n";
} else {
    $ins = $db->prepare('INSERT INTO employees (first_name,last_name,email,password,role) VALUES (?,?,?,?,?)');
    $ins->bind_param('sssss', $first, $last, $email, $hash, $role);
    $ok = $ins->execute();
    $newId = (int)$db->insert_id;
    $ins->close();
    echo $ok ? "INSERTED:$newId\n" : "INSERT_FAILED\n";
}

$verify = $db->prepare('SELECT id,email,role FROM employees WHERE email=? LIMIT 1');
$verify->bind_param('s', $email);
$verify->execute();
$vres = $verify->get_result();
$vrow = $vres ? $vres->fetch_assoc() : null;
$verify->close();

if ($vrow) {
    echo 'EMAIL=' . $vrow['email'] . "\n";
    echo 'ROLE=' . $vrow['role'] . "\n";
} else {
    echo "VERIFY_FAILED\n";
}
?>
