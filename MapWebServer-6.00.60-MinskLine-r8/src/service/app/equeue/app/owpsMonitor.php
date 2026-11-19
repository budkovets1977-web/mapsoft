<?php
require_once(__DIR__.'/../../init.php'); class equeue_app_owpsMonitor { private $db; public function readOwpsMonitor($params){ $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseCode = getSessionValue('mapEnterpriseCode'); $serviceId = (int)$params->service; $state = (int)$params->state; $sqlTextState = ''; switch ($state) { case 0: $sqlTextState = ''; break; case 1: $sqlTextState = 'AND CASE WHEN (MEOP.MedEqOWPperiodCode IS NULL) THEN 1 ELSE 0 END = 1'; break; case 2: $sqlTextState = 'AND CASE WHEN (MEOP.MedEqOWPperiodCode IS NOT NULL) AND (MEQC.MedEqQueueCouponCode IS NULL) THEN 1 ELSE 0 END = 1'; break; case 3: $sqlTextState = 'AND CASE WHEN (MEOP.MedEqOWPperiodCode IS NOT NULL) AND (MEQC.MedEqQueueCouponCode IS NOT NULL) THEN 1 ELSE 0 END = 1'; break; } $sql = <<<EOT
SELECT
    MedEqOWPCode AS "id",
    MedEqServiceName AS "serviceName",
    MedEqOWPName AS "owpName",
    StateNotWorking AS "stateNotWorking",
    StateFree AS "stateFree",
    StateBusy AS "stateBusy",
    DATE_TRUNC('second', StateTime) AS "stateTime"
FROM (SELECT
        MEO.MedEqOWPCode AS MedEqOWPCode,
        CASE WHEN COALESCE(MES.Cipher, E'') = E'' THEN E'' ELSE MES.Cipher || E' - ' END || MES.Name AS MedEqServiceName,
        (CASE WHEN MEO.Cabinet IS NULL THEN E'' ELSE MEO.Cabinet || E', ' END) || MEO.Number || E' ' || MEO.Name AS MedEqOWPName,
        (SELECT MAX (FinishTime) FROM MedEqOWPperiod WHERE MedEqOWPCode = MEO.MedEqOWPCode) AS LastStateFreeDT,
        (SELECT MAX (FinishTime) FROM MedEqQueueCoupon WHERE MedEqOWPCode = MEO.MedEqOWPCode) AS LastStateBusyDT,
        (SELECT MAX (StartTime) FROM MedEqQueueCoupon WHERE MedEqOWPCode = MEO.MedEqOWPCode) AS LastStateBusyStartDT,
        CASE WHEN (MEOP.MedEqOWPperiodCode IS NULL) THEN 1 ELSE 0 END AS StateNotWorking,
        CASE WHEN (MEOP.MedEqOWPperiodCode IS NOT NULL) AND (MEQC.MedEqQueueCouponCode IS NULL) THEN 1 ELSE 0 END AS StateFree,
        CASE WHEN (MEOP.MedEqOWPperiodCode IS NOT NULL) AND (MEQC.MedEqQueueCouponCode IS NOT NULL) THEN 1 ELSE 0 END AS StateBusy,
        CASE WHEN
                CASE WHEN (MEOP.MedEqOWPperiodCode IS NULL) THEN 1 ELSE 0 END = 1 THEN
                    CASE WHEN DAY((CLOCK_TIMESTAMP()::TIMESTAMP)) = DAY((SELECT MAX(FinishTime) FROM MedEqOWPperiod WHERE MedEqOWPCode = MEO.MedEqOWPCode)::TIMESTAMP)
                        THEN (CLOCK_TIMESTAMP()::TIMESTAMP) - (SELECT MAX(FinishTime) FROM MedEqOWPperiod WHERE MedEqOWPCode = MEO.MedEqOWPCode) ELSE NULL END
            WHEN
                CASE WHEN (MEOP.MedEqOWPperiodCode IS NOT NULL) AND (MEQC.MedEqQueueCouponCode IS NULL) THEN 1 ELSE 0 END = 1
                    THEN CASE WHEN DAY((CLOCK_TIMESTAMP()::TIMESTAMP)) = DAY((SELECT MAX(FinishTime) FROM MedEqQueueCoupon WHERE MedEqOWPCode = MEO.MedEqOWPCode)::TIMESTAMP)
                        THEN (CLOCK_TIMESTAMP()::TIMESTAMP) - (SELECT MAX(FinishTime) FROM MedEqQueueCoupon WHERE MedEqOWPCode = MEO.MedEqOWPCode) ELSE NULL END
            WHEN
                CASE WHEN (MEOP.MedEqOWPperiodCode IS NOT NULL) AND (MEQC.MedEqQueueCouponCode IS NOT NULL) THEN 1 ELSE 0 END = 1
                    THEN CASE WHEN DAY((CLOCK_TIMESTAMP()::TIMESTAMP)) = DAY((SELECT MAX(StartTime) FROM MedEqQueueCoupon WHERE MedEqOWPCode = MEO.MedEqOWPCode)::TIMESTAMP)
                        THEN (CLOCK_TIMESTAMP()::TIMESTAMP) - (SELECT MAX(StartTime) FROM MedEqQueueCoupon WHERE MedEqOWPCode = MEO.MedEqOWPCode) ELSE NULL END
        END AS StateTime
      FROM MedEqOWP AS MEO
      LEFT JOIN MedEqService AS MES ON MES.MedEqServiceCode = MEO.MedEqServiceCode
      LEFT JOIN MedEqQueueCoupon AS MEQC ON MEQC.MedEqOWPCode = MEO.MedEqOWPCode
            AND MEQC.MedEqServiceCode = MES.MedEqServiceCode
            AND (CLOCK_TIMESTAMP()::TIMESTAMP) BETWEEN MEQC.StartTime AND (CASE WHEN MEQC.FinishTime IS NULL THEN 30000101 END)
            AND MEQC.StartTime >= (DATE((CLOCK_TIMESTAMP()::TIMESTAMP)))
      LEFT JOIN MedEqOWPperiod AS MEOP ON MEOP.MedEqOWPCode = MEO.MedEqOWPCode
            AND (CLOCK_TIMESTAMP()::TIMESTAMP) BETWEEN MEOP.StartTime AND MEOP.FinishTime
            AND MEOP.StartTime >= (DATE((CLOCK_TIMESTAMP()::TIMESTAMP)))
      WHERE MEO.active = 1
          AND (MEO.EnterpriseCode = $mapEnterpriseCode
                AND ((MEO.MedEqServiceCode = $serviceId)
                OR (0 = $serviceId)) $sqlTextState)
      ORDER BY 2, 3) AS RES
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchAll(); } } handleJsonRpc('equeue_app_owpsMonitor');