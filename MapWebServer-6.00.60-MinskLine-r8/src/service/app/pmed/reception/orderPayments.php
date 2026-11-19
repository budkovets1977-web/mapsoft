<?php
require_once(__DIR__ . '/../../init.php'); class pmed_reception_orderPayments { private $db; public function readOrderPayments($params){ $db = $this->db ?: $this->db = connectDb(); $orderId = isEmptyProperty($params, 'orderId'); $sql = <<<EOT
            SELECT
                pmsOrderPaymentCode "id",
                note,
                paymentDate "date",
                paymentSum "sum",
                paymentType "type",
                pmsOrderCode "orderId",
                0 "total"
            FROM pmsOrderPayment
            WHERE pmsOrderCode = :orderId

            UNION ALL

            SELECT
                NULL "id",
                NULL "note",
                NULL "date",
                (SELECT SUM(paymentSum) FROM pmsOrderPayment WHERE pmsOrderCode = :orderId) "sum",
                -1 "type",
                :orderId "orderId",
                1 "total"
            WHERE ((SELECT COUNT(*) FROM pmsOrderPayment WHERE pmsOrderCode = :orderId) != 0)

            ORDER BY "total", "id"
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ "orderId" => $orderId ]); $ret = $stmt->fetchAll(); return $ret; } public function createOrderPayments($records){ $db = $this->db ?: $this->db = connectDb(); if (is_object($records)) { $records = [$records]; } $sql = <<<EOT
            INSERT INTO pmsOrderPayment
                        (note, paymentDate, paymentSum,
                        paymentType, pmsOrderCode)
            VALUES (?, ?, ?,
                    ?, ?)
EOT;
$stmt = $db->prepare($sql); foreach ($records as $row){ $stmt->execute([ isUndefinedProperty($row, 'note'), isUndefinedProperty($row, 'date'), isUndefinedProperty($row, 'sum'), isUndefinedProperty($row, 'type'), isUndefinedProperty($row, 'orderId') ]); } return true; } public function deleteOrderPayments($records){ $db = $this->db ?: $this->db = connectDb(); if (is_object($records)){ $records = [$records]; } $sql = <<<EOT
            DELETE FROM pmsOrderPayment
            WHERE pmsOrderPaymentCode = ?
EOT;
$stmt = $db->prepare($sql); foreach ($records as $row){ $stmt->execute([ $row->id ]); } return true; } public function getHintSum($params){ $db = $this->db ?: $this->db = connectDb(); $orderId = isEmptyProperty($params, 'orderId'); $ret = null; $sql = <<<EOT
            SELECT CASE WHEN (COALESCE(resultSum, 0)-COALESCE(paymentSum, 0)) < 0 THEN 0
                ELSE (COALESCE(resultSum, 0)-COALESCE(paymentSum, 0)) END "sum"
            FROM pmsOrder
            WHERE pmsOrderCode = :orderId
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ "orderId" => $orderId ]); $ret = $stmt->fetchAll(); return $ret; } } handleJsonRpc('pmed_reception_orderPayments');