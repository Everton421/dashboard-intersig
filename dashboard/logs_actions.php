<?php
session_start();
require_once '../config/config.php';
require_once '../config/session.php'; 

header('Content-Type: application/json');

if (!isset($_SESSION["user_name"])) {
    echo json_encode(["success" => false, "message" => "Acesso negado."]);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'delete_all') {
    $sql = "DELETE FROM logs";
    if ($dash->query($sql)) {
        echo json_encode(["success" => true, "message" => "Todos os logs foram excluídos com sucesso!"]);
    } else {
        echo json_encode(["success" => false, "message" => "Erro ao excluir logs: " . $dash->error]);
    }
    exit;
}

if ($action === 'delete_one' && isset($_POST['id'])) {
    $id = (int) $_POST['id'];
    $stmt = $dash->prepare("DELETE FROM logs WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Log excluído com sucesso!"]);
    } else {
        echo json_encode(["success" => false, "message" => "Erro ao excluir log: " . $stmt->error]);
    }
    $stmt->close();
    exit;
}

echo json_encode(["success" => false, "message" => "Ação inválida."]);