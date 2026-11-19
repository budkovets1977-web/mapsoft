<?php
require_once(__DIR__ . '/../../../../init.php'); class med_cisz_contract_rejectionBy_loader { use med_cisz_utilsTrait; public const int CISZ_DOC_EXTRA_TYPE = 4; private PDO_ANY $db; private med_cisz_context $context; function __construct($context) { $this->db = connectDb(); $this->context = $context; } public function load(?object $params = null): ?object { $db = $this->db; $sql = <<<"SQL"
SELECT
    ML.refused "refused",
    ML.uniDictCodeReasonRefuse "uniDictCodeReasonRefuse",
    UD.cipher "reasonRefuseCode",
    UD.value "reasonRefuseDisplay",
    ML.leaveTime "leaveTime",
    ML.rejectBy "rejectBy",
    ML.ciszResourceValueCodeRejectionReason "ciszResourceValueCodeRejectionReason",
    CDE.json "json"
FROM MedLog ML
LEFT JOIN CiszDocExtra CDE ON CDE.MedLogCode = ML.MedLogCode AND CDE.DocumentType = :docType
LEFT JOIN UniDict UD ON UD.UniDictCode = ML.UniDictCodeReasonRefuse
WHERE ML.MedLogCode = :medLogCode
SQL;
$data = selectSql($db, $sql, [ ':medLogCode' => $params->primaryKey, ':docType' => self::CISZ_DOC_EXTRA_TYPE ], PDO::FETCH_OBJ); if (!count($data)) { throw new Error('Выбранная запись отсутствует в базе данных'); } $row = $data[0]; if(!$row->ciszResourceValueCodeRejectionReason) { throw new Error('Не указана причина отказа.'); } $row->ciszCodeReason = self::getCiszCodeByCiszResourceValueCode($row->ciszResourceValueCodeRejectionReason); $row->jsonData = safe_json_decode($row->json); return (object)$row; } }