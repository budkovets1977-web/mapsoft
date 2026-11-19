<?php
require_once(__DIR__.'/../../init.php'); class equeue_app_displaySegment { private $db; public function readDisplaySegment(){ $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<EOT
            SELECT
                med.medEqDisplayCode id,
                med.displayNumber "displayNumber",
                LPAD(med.displayNumber, 3, '0') "sortDisplayNumber",
                med.blinkCount "blinkCount",
                med.ipAddress "ipAddress",
                med.ipPort "ipPort",
                med.portNumber "portNumber",
                med.portSpeed "portSpeed",
                edo.medEqOwpCode "operatorId",
                meo.name "operatorName",
                med.note notes,
                mes.medEqServiceCode "serviceId",
                mes.name "serviceName",
                CASE WHEN med.displayKind = 2 THEN 1 ELSE 0 END "color",
                c1.color "letterColor",
                c2.color "digitsColor",
                c3.color "borderColor1",
                c4.color "borderColor2"
            FROM medEqDisplay med
            LEFT JOIN medEqDisplayOwp edo ON edo.medEqDisplayCode = med.medEqDisplayCode
            LEFT JOIN medEqOwp meo ON meo.medEqOwpCode = edo.medEqOwpCode
            LEFT JOIN medEqService mes ON mes.medEqServiceCode = meo.medEqServiceCode
            LEFT JOIN medEqDisplayColor c1 ON c1.medEqDisplayCode = med.medEqDisplayCode AND c1.type = 1
            LEFT JOIN medEqDisplayColor c2 ON c2.medEqDisplayCode = med.medEqDisplayCode AND c2.type = 2
            LEFT JOIN medEqDisplayColor c3 ON c3.medEqDisplayCode = med.medEqDisplayCode AND c3.type = 3
            LEFT JOIN medEqDisplayColor c4 ON c4.medEqDisplayCode = med.medEqDisplayCode AND c4.type = 4
            WHERE med.enterpriseCode = ?
                AND med.displayKind IN (1, 2)
            ORDER BY 3
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $mapEnterpriseCode ]); $ret = $stmt->fetchAll(); return $ret; } public function createDisplaySegment($records){ $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<EOT
            INSERT INTO medEqDisplay
                (displayNumber, enterpriseCode, displayKind,
                ipAddress, ipPort, portNumber,
                portSpeed, blinkCount, note)
            VALUES (?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?)
EOT;
$stmt = $db->prepare($sql); if (is_object($records)){ $records = [$records]; } $ret = []; $db->beginTransaction(); try{ foreach ($records as $row){ $displayKind = isUndefinedProperty($row, 'color') ? 2 : 1; $stmt->execute([ isEmptyProperty($row, 'displayNumber'), $mapEnterpriseCode, $displayKind, isEmptyProperty($row, 'ipAddress'), isEmptyProperty($row, 'ipPort'), isEmptyProperty($row, 'portNumber'), isEmptyProperty($row, 'portSpeed'), isEmptyProperty($row, 'blinkCount'), isEmptyProperty($row, 'notes') ]); $rec = new stdClass; $rec->id = (int)$db->lastInsertId('seq_medEqDisplay'); if ($displayKind == 2){ $this->updateColors($rec->id, $row); } $operatorId = isEmptyProperty($row, 'operatorId'); if ($operatorId){ $this->updateOperators($operatorId, $rec->id); } $ret[] = $rec; } $db->commit(); }catch (Throwable $e){ $db->rollback(); throw $e; } return $ret; } public function deleteDisplaySegment($records){ $db = $this->db ?: $this->db = connectDb(); if (is_object($records)){ $records = [$records]; } $db->beginTransaction(); try{ foreach ($records as $row){ $sql = "DELETE FROM medEqDisplayColor WHERE medEqDisplayCode = ?"; executeSql($db, $sql, [ $row->id ]); $sql = "DELETE FROM medEqDisplay WHERE medEqDisplayCode = ?"; executeSql($db, $sql, [ $row->id ]); } $db->commit(); }catch (Throwable $e){ $db->rollback(); throw $e; } return true; } public function updateDisplaySegment($records){ $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseCode = getSessionValue('mapEnterpriseCode'); $sql = <<<EOT
            UPDATE medEqDisplay
            SET
                displayNumber =     CASE WHEN ? = 1 THEN ? ELSE displayNumber END,
                enterpriseCode =    ?,
                displayKind =       CASE WHEN ? = 1 THEN ? ELSE displayKind END,
                ipAddress =         CASE WHEN ? = 1 THEN ? ELSE ipAddress END,
                ipPort =            CASE WHEN ? = 1 THEN ? ELSE ipPort END,
                portNumber =        CASE WHEN ? = 1 THEN ? ELSE portNumber END,
                portSpeed =         CASE WHEN ? = 1 THEN ? ELSE portSpeed END,
                blinkCount =        CASE WHEN ? = 1 THEN ? ELSE blinkCount END,
                note =              CASE WHEN ? = 1 THEN ? ELSE note END
            WHERE medEqDisplayCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)){ $records = [$records]; } $ret = []; $db->beginTransaction(); try{ foreach ($records as $row){ $displayKind = isUndefinedProperty($row, 'color') ? 2 : 1; $stmt->execute([ propertyExistsSQL($row, 'displayNumber'), isEmptyProperty($row, 'displayNumber'), $mapEnterpriseCode, propertyExistsSQL($row, 'color'), $displayKind, propertyExistsSQL($row, 'ipAddress'), isEmptyProperty($row, 'ipAddress'), propertyExistsSQL($row, 'ipPort'), isEmptyProperty($row, 'ipPort'), propertyExistsSQL($row, 'portNumber'), isEmptyProperty($row, 'portNumber'), propertyExistsSQL($row, 'portSpeed'), isEmptyProperty($row, 'portSpeed'), propertyExistsSQL($row, 'blinkCount'), isEmptyProperty($row, 'blinkCount'), propertyExistsSQL($row, 'notes'), isEmptyProperty($row, 'notes'), isEmptyProperty($row, 'id') ]); $rec = new stdClass; $rec->id = isEmptyProperty($row, 'id'); if ($displayKind == 2){ $this->updateColors($rec->id, $row); } $operatorId = isEmptyProperty($row, 'operatorId'); if ($operatorId){ $this->updateOperators($operatorId, $rec->id); } $ret[] = $rec; } $db->commit(); }catch (Throwable $e){ $db->rollback(); throw $e; } return $ret; } private function updateOperators($operatorsIds, $displayId){ $db = $this->db ?: $this->db = connectDb(); $sql = "DELETE FROM medEqDisplayOwp WHERE medEqDisplayCode = ?"; $stmt = $db->prepare($sql); $stmt->execute([ $displayId ]); $pos = 0; $operatorsIds = [$operatorsIds]; if ($operatorsIds){ foreach ($operatorsIds as $medEqOperatorId){ $sql = <<<EOT
                        INSERT INTO medEqDisplayOwp(medEqDisplayCode,
                                                medEqOwpCode,
                                                position)
                        VALUES (?, ?, ?)
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ $displayId, $medEqOperatorId, ++$pos ]); } } } private function updateColors($displayId, $record){ $db = $this->db ?: $this->db = connectDb(); $sql = "DELETE FROM medEqDisplayColor WHERE medEqDisplayCode = ?"; $stmt = $db->prepare($sql); $stmt->execute([ $displayId ]); $keys = array('letterColor', 'digitsColor', 'borderColor1', 'borderColor2'); $sql = <<<EOT
                INSERT INTO medEqDisplayColor(color, medEqDisplayCode, type)
                VALUES (?, ?, ?)
EOT;
for ($i = 0; $i < 4; $i++){ $type = $i + 1; executeSql($db, $sql, [ isUndefinedProperty($record, $keys[$i]), $displayId, $type ]); } } } handleJsonRpc('equeue_app_displaySegment');