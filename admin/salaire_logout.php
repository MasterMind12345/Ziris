<?php
session_start();

unset($_SESSION['salaire_access_granted']);
unset($_SESSION['salaire_access_time']);

echo json_encode(['success' => true]);
?>