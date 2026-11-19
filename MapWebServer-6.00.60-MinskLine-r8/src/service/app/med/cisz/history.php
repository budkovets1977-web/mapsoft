<?php
 require_once(__DIR__ . '/../../init.php'); require_once('enums.php'); class med_cisz_history { private ?PDO_ANY $db = null; public function readHistory(?object $params = null): object { $db = $this->db ?: $this->db = connectDb(); $sqlWhere = ['1 = 1']; if ($params?->startDate ?? false) { $startDate = date('Y-m-d', safe_strtotime($params->startDate)); $sqlWhere[] = "(ch.RequestTime IS NULL OR ch.RequestTime::date >= '$startDate')"; $sqlWhere[] = "(ch.ResponseTime IS NULL OR ch.ResponseTime::date >= '$startDate')"; } if ($params?->finishDate ?? false) { $finishDate = date('Y-m-d', safe_strtotime($params->finishDate)); $sqlWhere[] = "(ch.RequestTime IS NULL OR ch.RequestTime::date <= '$finishDate')"; $sqlWhere[] = "(ch.ResponseTime IS NULL OR ch.ResponseTime::date <= '$finishDate')"; } if ($params?->type ?? false) { $sqlWhere[] = "ch.Type = $params->type"; } if ($params?->actionType ?? false) { $sqlWhere[] = "ch.ActionType = $params->actionType"; } if ($params?->status ?? false) { $sqlWhere[] = "ch.Status = $params->status"; } if ($params?->doctorId ?? false) { $sqlWhere[] = "md.MedDoctorCode = $params->doctorId"; } if ($params?->patientId ?? false) { $sqlWhere[] = "ch.MedpatientCode = $params->patientId"; } $sqlWhere = 'WHERE ' . implode("\n\tAND ", $sqlWhere); $sqlSort = ['ch.RequestTime DESC', 'ch.ResponseTime DESC']; if ($params?->sort ?? false) { $sqlSort = []; $sorters = safe_json_decode($params->sort); foreach ($sorters as $sort) { if ($sort->property == '_type') { $sqlSort[] = "
                        CASE ch.Type
                            WHEN " . HistoryTypeEnum::PATIENT->value . " THEN '" . HistoryTypeEnum::PATIENT->label() . "'
                            WHEN " . HistoryTypeEnum::ENCOUNTER->value . " THEN '" . HistoryTypeEnum::ENCOUNTER->label() . "'
                        END $sort->direction"; } elseif ($sort->property == '_actionType') { $sqlSort[] = "
                        CASE ch.ActionType
                            WHEN " . HistoryActionTypeEnum::REGISTRATION->value . " THEN '" . HistoryActionTypeEnum::REGISTRATION->label() . "'
                            WHEN " . HistoryActionTypeEnum::CHECK_STATUS->value . " THEN '" . HistoryActionTypeEnum::CHECK_STATUS->label() . "'
                            WHEN " . HistoryActionTypeEnum::CANCEL->value . " THEN '" . HistoryActionTypeEnum::CANCEL->label() . "'
                        END $sort->direction"; } elseif ($sort->property == '_status') { $sqlSort[] = "
                        CASE ch.Status
                            WHEN " . StatusEnum::PENDING->value . " THEN '" . StatusEnum::PENDING->label() . "'
                            WHEN " . StatusEnum::IN_PROGRESS->value . " THEN '" . StatusEnum::IN_PROGRESS->label() . "'
                            WHEN " . StatusEnum::SUCCESSED->value . " THEN '" . StatusEnum::SUCCESSED->label() . "'
                            WHEN " . StatusEnum::FAILED->value . " THEN '" . StatusEnum::FAILED->label() . "'
                            WHEN " . StatusEnum::CANCELLED->value . " THEN '" . StatusEnum::CANCELLED->label() . "'
                        END $sort->direction"; } else { $sqlSort[] = '"' . $sort->property . '" ' . $sort->direction; } } } $sqlSort = 'ORDER BY ' . implode(', ', $sqlSort); $sqlLimit = ''; if (($limit = $params?->limit ?? false)) { $page = $params?->page ?? 1; $offset = max(($page - 1) * $limit, 0); $sqlLimit = "LIMIT $limit OFFSET $offset"; } $sql = <<<"SQL"
            SELECT
                ch.ciszHistoryCode AS "id",
                ch.Type AS "type",
                ch.UserCode AS "userId",
                md.MedDoctorCode AS "doctorId",
                COALESCE(
                    NULLIF(fn_cmFio(md.ManCode, 1), ''),
                    NULLIF(WU.Fio, ''),
                    NULLIF(U.Nick, '')
                ) AS "doctorName",
                ch.BundleId AS "bundleId",
                ch.RecordId AS "recordId",                
                ch.ActionType AS "actionType",
                ch.RequestTime AS "requestTime",
                ch.RequestJSON AS "requestJson",
                ch.ResponseTime AS "responseTime",
                ch.ResponseJSON AS "responseJson",                
                ch.MedpatientCode AS "patientId",
                fn_cmFio(mp.ManCode) AS "patientName",
                ch.Status AS "status"
            FROM
                ciszHistory ch
            LEFT JOIN MedDoctor md ON (CH.MedDoctorCode IS NOT NULL AND MD.MedDoctorCode = CH.MedDoctorCode) 
                                       OR (CH.MedDoctorCode IS NULL AND MD.UserCode = CH.UserCode)
            LEFT JOIN MedPatient mp ON mp.MedPatientCode = ch.MedpatientCode
            LEFT JOIN Users U ON CH.UserCode = U.UserCode
            LEFT JOIN WebUser WU ON U.UserCode = WU.UserCode
            $sqlWhere
            $sqlSort
        SQL;
$total = (int) selectSqlValue($db, "
            SELECT
                count(*)
            FROM (
                $sql
            ) AS subquery
        "); $sql .= "
            $sqlLimit
        "; $items = selectSql($db, $sql); return (object) [ 'total' => $total, 'items' => $items, ]; } } handleJsonRpc('med_cisz_history');