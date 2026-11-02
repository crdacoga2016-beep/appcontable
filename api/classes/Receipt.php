<?php
require_once 'config.php';

class Receipt {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function addClient($userId, $name, $phone) {
        $stmt = $this->db->prepare("INSERT INTO clients (user_id, name, phone) VALUES (?, ?, ?)");
        return $stmt->execute([$userId, $name, $phone]) ? $this->db->lastInsertId() : false;
    }

    public function listClients($userId) {
        $stmt = $this->db->prepare(
            "SELECT c.id, c.name, c.phone, c.created_at,
                    COALESCE(SUM(r.amount), 0) AS total,
                    COUNT(r.id) AS receipts_count
             FROM clients c
             LEFT JOIN receipts r ON r.client_id = c.id AND r.user_id = c.user_id
             WHERE c.user_id = ?
             GROUP BY c.id, c.name, c.phone, c.created_at
             ORDER BY c.name ASC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function addReceipt($userId, $clientId, $amount, $date, $method, $note, $filePath) {
        $stmt = $this->db->prepare("INSERT INTO receipts (user_id, client_id, amount, receipt_date, method, note, file_path, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        return $stmt->execute([$userId, $clientId, $amount, $date, $method, $note, $filePath]) ? $this->db->lastInsertId() : false;
    }

    public function updateReceipt($id, $userId, $clientId, $amount, $date, $method, $note, $newFile = null) {
        $stmt = $this->db->prepare("SELECT file_path FROM receipts WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();
        if (!$row) return false;

        $sql = "UPDATE receipts SET client_id = ?, amount = ?, receipt_date = ?, method = ?, note = ?";
        $params = [$clientId, $amount, $date, $method, $note];
        if ($newFile !== null) {
            $sql .= ", file_path = ?";
            $params[] = $newFile;
        }
        $sql .= " WHERE id = ? AND user_id = ?";
        $params[] = $id;
        $params[] = $userId;

        $ok = $this->db->prepare($sql)->execute($params);
        if ($ok && $newFile !== null && !empty($row['file_path']) && file_exists($row['file_path'])) {
            @unlink($row['file_path']);
        }
        return $ok;
    }

    public function listReceipts($userId, $clientId = null, $start = null, $end = null) {
        $sql = "SELECT r.*, c.name AS client_name FROM receipts r LEFT JOIN clients c ON r.client_id = c.id WHERE r.user_id = ?";
        $params = [$userId];
        if ($clientId) { $sql .= " AND r.client_id = ?"; $params[] = $clientId; }
        if ($start) { $sql .= " AND r.receipt_date >= ?"; $params[] = $start; }
        if ($end) { $sql .= " AND r.receipt_date <= ?"; $params[] = $end; }
        $sql .= " ORDER BY r.receipt_date DESC, r.id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function totalByClient($userId, $clientId, $start = null, $end = null) {
        $sql = "SELECT COALESCE(SUM(amount),0) AS total, COUNT(*) AS cant FROM receipts WHERE user_id = ?";
        $params = [$userId];
        if ($clientId) { $sql .= " AND client_id = ?"; $params[] = $clientId; }
        if ($start) { $sql .= " AND receipt_date >= ?"; $params[] = $start; }
        if ($end) { $sql .= " AND receipt_date <= ?"; $params[] = $end; }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    public function getReceipt($id, $userId) {
        $stmt = $this->db->prepare("SELECT r.*, c.name AS client_name FROM receipts r LEFT JOIN clients c ON r.client_id = c.id WHERE r.id = ? AND r.user_id = ?");
        $stmt->execute([$id, $userId]);
        return $stmt->fetch();
    }

    public function deleteReceipt($id, $userId) {
        $stmt = $this->db->prepare("SELECT file_path FROM receipts WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();
        if (!$row) return false;
        if (!empty($row['file_path']) && file_exists($row['file_path'])) @unlink($row['file_path']);
        $stmt = $this->db->prepare("DELETE FROM receipts WHERE id = ? AND user_id = ?");
        return $stmt->execute([$id, $userId]);
    }
}