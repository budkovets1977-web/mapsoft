<?php
 require_once(__DIR__ . '/../../init.php'); class med_ambulance_logStats { static public function updateMedEmergency($db, $ambLogCode, $inTransaction = true) { $rows = selectSql($db, <<<"EOT"
SELECT
    active,
    calltime::date AS calldate,
    '1900-01-01'::date + calltime::time AS calltime,
    cardnumber AS callnumber,
    medpatientcode,
    fio AS patientfio,
    address AS patientaddress,
    NULLIF(CASE WHEN ageyear IS NOT NULL THEN ageyear::text || ' л' ELSE '' END
            || CASE WHEN dateofbirth IS NOT NULL THEN ' (' || to_char(dateofbirth, 'DD.MM.YYYY') || ')' ELSE '' END,
        '') AS patientage,
    activeobserve AS asset,
    (SELECT G.visitzone FROM medpatientgroup G JOIN medpatient P ON P.medpatientgroupcode = G.medpatientgroupcode
            AND P.medpatientcode = L.medpatientcode) AS visitzone,
    (SELECT unidictcode FROM unidict WHERE dictnumber = 22
            AND cipher = CASE WHEN COALESCE(L.priority, 0) = 2 THEN 1 ELSE 2 END) AS priority,
    D.meddiagnosiscode,
    D.chronic,
    (SELECT medemergencycode FROM medemergency WHERE amblogcode = L.amblogcode LIMIT 1) medemergencycode,
    -- ищем существующий вызов повремени и номеру
    (SELECT medemergencycode FROM medemergency
        WHERE calldate = L.calltime::date
            AND to_char(calltime, 'HH24:MI')::time = to_char(L.calltime, 'HH24:MI')::time
            AND callnumber = L.cardnumber
        LIMIT 1) AS findemergencycode
FROM amblog L
    LEFT JOIN LATERAL (
      SELECT meddiagnosiscode, CASE WHEN EXISTS(SELECT 1 FROM medvisitpatient
            WHERE medpatientcode = L.medpatientcode AND meddiagnosiscode = D2.meddiagnosiscode)
          THEN 1 END AS chronic
      FROM amblogdiagnosis D2
      WHERE D2.amblogcode = L.amblogcode AND diagnosistype = 1
      ORDER BY amblogdiagnosiscode LIMIT 1) D ON true
WHERE amblogcode = ? AND appeal <> 2 -- кроме консультаций
  
EOT,
[$ambLogCode], PDO::FETCH_OBJ); if (empty($rows)) return; $r = $rows[0]; if (empty($r->meddiagnosiscode) || empty($r->active)) { if (!empty($r->medemergencycode)) { executeSql($db, "DELETE FROM medemergency WHERE medemergencycode = ?", [$r->medemergencycode]); } return; } $fields = 'amblogcode,calldate,calltime,callnumber,medpatientcode,patientfio,patientaddress,patientage,
asset,visitzone,priority,meddiagnosiscode,chronic,linkstatus,linkcode'; $r->amblogcode = $ambLogCode; $r->medemergencycode = $r->medemergencycode ?? $r->findemergencycode; $r->linkstatus = 1; $r->linkcode = $ambLogCode; if (empty($r->medemergencycode)) { insertSql($db, 'medemergency', $r, $fields, null, null, 'medemergencycode', $inTransaction); } else { updateSql($db, 'medemergency', 'medemergencycode', $r, $fields, null, 'medemergencycode', $inTransaction); } } }