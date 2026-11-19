<?php
 require_once(__DIR__ . '/../../init.php'); class med_reception_couponjobs { private $db; public function readCouponJobs($params = null) { $db = $this->db ?: $this->db = connectDb(); $flagCoupon = isEmptyProperty($params, 'flagCoupon', 0); $flagCancel = isEmptyProperty($params, 'flagCancel', 0); $flagSecondReception = isEmptyProperty($params, 'flagSecondReception', 0); $flagSelfRecording = isEmptyProperty($params, 'flagSelfRecording', 0); $sqlWhere = <<<"EOT"
WHERE ($flagCoupon = 0 OR MCW.FlagCoupon = $flagCoupon) AND
      ($flagCancel = 0 OR MCW.FlagCancel = $flagCancel)
  /* только повторная запись на прием */
      AND ($flagSecondReception = 0 OR MCW.FlagSecondReception = 1)
EOT;
if ($flagSelfRecording) { $sqlWhere .= " AND ((SELECT MAX(flagselfrecording) FROM MedCouponControl WHERE MedCouponWorkCode = MCW.MedCouponWorkCode) = $flagSelfRecording)"; } $sql = <<<"EOT"
SELECT
    MCW.MedCouponWorkCode "id",
    MCW.Name "name",
    MCW.MedJobVisitKindCode "visitKindId",
    MJVK.Name "visitKindName",
    MCW.FlagCoupon "flagCoupon",
    MCW.FlagCall "flagCall",
    MCW.FlagSecondReception "flagSecondReception",
    MCW.FlagCancel "flagCancel",
    MCW.IconIndex "iconIndex",
    MCW.CancelReason "cancelReason"
FROM MedCouponWork MCW
LEFT JOIN MedJobVisitKind MJVK ON MCW.MedJobVisitKindCode = MJVK.MedJobVisitKindCode
$sqlWhere
ORDER BY 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } public function updateCouponJobs($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
UPDATE MedCouponWork SET
       Name = case when ? = 1 then ? else Name end,
       MedJobVisitKindCode = case when ? = 1 then ? else MedJobVisitKindCode end,
       FlagCoupon = case when ? = 1 then ? else FlagCoupon end,
       FlagCall = case when ? = 1 then ? else FlagCall end,
       FlagSecondReception = case when ? = 1 then ? else FlagSecondReception end,
       FlagCancel = case when ? = 1 then ? else FlagCancel end,
       IconIndex = case when ? = 1 then ? else IconIndex end,
       CancelReason = CASE WHEN ? = 1 THEN ? ELSE CancelReason END
WHERE MedCouponWorkCode = ?
EOT;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ propertyExistsSQL($row, 'name'), isUndefinedProperty($row, 'name'), propertyExistsSQL($row, 'visitKindId'), isEmptyProperty($row, 'visitKindId'), propertyExistsSQL($row, 'flagCoupon'), isEmptyProperty($row, 'flagCoupon'), propertyExistsSQL($row, 'flagCall'), isEmptyProperty($row, 'flagCall'), propertyExistsSQL($row, 'flagSecondReception'), isUndefinedProperty($row, 'flagSecondReception'), propertyExistsSQL($row, 'flagCancel'), isEmptyProperty($row, 'flagCancel'), propertyExistsSQL($row, 'iconIndex'), isEmptyProperty($row, 'iconIndex'), propertyExistsSQL($row, 'cancelReason'), isEmptyProperty($row, 'cancelReason'), $row->id ]); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function deleteCouponJobs($records) { $db = $this->db ?: $this->db = connectDb(); $in = createSqlInCondition($records); $sql = <<<"EOT"
DELETE FROM MedCouponWork WHERE MedCouponWorkCode $in
EOT;
$stmt = $db->prepare($sql); $db->beginTransaction(); try { $stmt->execute(); $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } public function createCouponJobs($records) { $db = $this->db ?: $this->db = connectDb(); $sql = <<<SQL
            INSERT INTO MedCouponWork (
                Name, MedJobVisitKindCode, FlagCoupon,
                FlagCall, FlagSecondReception, FlagCancel,
                IconIndex, CancelReason
            )
            VALUES (
                ?, ?, ?,
                ?, ?, ?,
                ?, ?
            )
            SQL;
$stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $ret = []; $db->beginTransaction(); try { foreach ($records as $row) { $stmt->execute([ isUndefinedProperty($row, 'name'), isUndefinedProperty($row, 'visitKindId'), isUndefinedProperty($row, 'flagCoupon'), isUndefinedProperty($row, 'flagCall'), isUndefinedProperty($row, 'flagSecondReception'), isUndefinedProperty($row, 'flagCancel'), isUndefinedProperty($row, 'iconIndex'), isUndefinedProperty($row, 'cancelReason') ]); $ret[] = $rec = new stdClass; $rec->id = $db->lastInsertId('seq_MedCouponWork'); $row->id = $rec->id; } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $ret; } public function findIconsForLegend() { $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
SELECT
    MCW.IconIndex "iconIndex",
    MCW.Name "name"
FROM MedCouponWork MCW
WHERE MCW.IconIndex IN
    (SELECT MW.iconIndex
     FROM MedCouponWork MW
     WHERE MW.IconIndex IS NOT NULL
     GROUP BY MW.iconIndex
     HAVING COUNT(medCouponWorkCode) = 1)
ORDER BY 1
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } } handleJsonRpc('med_reception_couponjobs');