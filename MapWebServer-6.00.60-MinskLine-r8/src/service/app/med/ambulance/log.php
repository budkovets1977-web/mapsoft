<?php
require_once(__DIR__ . '/../../init.php'); suspendJsonRpc(); require_once(__DIR__ . '/logStats.php'); resumeJsonRpc(); class med_ambulance_log { private $db; private $notificationSettings = null; const LOG_ADD_PERMISSION = 'med-ambulance-log-add'; const ERROR_NO_ADD_PERMISSION = 'У Вас нет прав на добавление вызовов. Обратитесь к Администратору.'; const LOG_EDIT_PERMISSION = 'med-ambulance-log-edit'; const ERROR_NO_EDIT_PERMISSION = 'У Вас нет прав на редактирование вызовов. Обратитесь к Администратору.'; const LOG_DELETE_TO_BIN_PERMISSION = 'med-ambulance-log-deletetobin'; const ERROR_NO_DELETE_TO_BIN_PERMISSION = 'У Вас нет прав на удаление вызова в корзину. Обратитесь к Администратору.'; const LOG_DELETE_FINAL_PERMISSION = 'med-ambulance-log-delete'; const ERROR_NO_DELETE_FINAL_PERMISSION = 'У Вас нет прав на окончательное удаление вызова. Обратитесь к Администратору.'; const LOG_CARD_NUMBER_EDIT_PERMISSION = 'med-ambulance-logmapcallbrigades-callnumber-edit'; const ERROR_NO_CARD_NUMBER_EDIT_PERMISSION = 'У Вас нет прав на редактирование номера вызова. Обратитесь к Администратору.'; public function readLog($params){ $db = $this->db ?: $this->db = connectDb(); $sqlParams = []; $active = isUndefinedProperty($params, 'active', 1); $activeSql = $active ? "AND L.active = 1" : "AND L.active IS NULL"; $id = isEmptyProperty($params, 'id'); $idSql = empty($id) ? '' : "and L.ambLogCode = {$id}"; $states = isEmptyProperty($params, 'state'); $dayNightCalls = isUndefinedProperty($params, 'dayNightCalls'); $stateFind=[]; if(!is_array($states)) $states = [$states]; $isProcessedSql = "(L.cardData::json->'mainCardBrigades' IS NOT NULL OR L.cardData::json->'secondCardBrigades' IS NOT NULL OR L.cardData::json->'thirdCardBrigades' IS NOT NULL)"; foreach ($states as $state){ switch ($state){ case 0: $stateFind = []; break 2; case 1: $stateFind[] = "(T.ambTeamCode IS NULL AND NOT $isProcessedSql)"; break; case 2: $stateFind[] = "(T.ambTeamCode IS NOT NULL AND L.transferTime IS NOT NULL AND L.checkOutTime IS NULL AND L.arrivalTime IS NULL AND L.endTime IS NULL AND L.returnTime IS NULL AND NOT $isProcessedSql)"; break; case 3: $stateFind[] = "T.ambTeamCode IS NOT NULL AND L.checkOutTime IS NOT NULL AND L.arrivalTime IS NULL AND L.endTime IS NULL AND L.returnTime IS NULL AND NOT $isProcessedSql"; break; case 4: $stateFind[] = "T.ambTeamCode IS NOT NULL AND L.arrivalTime IS NOT NULL AND L.endTime IS NULL AND L.returnTime IS NULL AND NOT $isProcessedSql"; break; case 5: $stateFind[] = "T.ambTeamCode IS NOT NULL AND L.endTime IS NOT NULL"; break; case 6: $stateFind[] = $isProcessedSql; break; } } if (!empty($stateFind)) { $stateFind =" AND (". implode (" OR ", $stateFind) . ")"; } else { $stateFind = ''; } $team = isEmptyProperty($params, 'team'); $teamSql = ''; if (!empty($team)) { if (is_array($team)) { $teamIn = createSqlInCondition($team); $teamSql = "AND T.ambTeamCode {$teamIn}"; } else { $team = (int) $team; $teamSql = "AND T.ambTeamCode = {$team}"; } } $shift = isEmptyProperty($params, 'shift'); $shiftSql = ''; if (empty($shift)) { $shiftSql = ''; } elseif (is_array($team)) { $shiftSql = "AND G.ambShiftCode in (select s1.ambshiftcode from ambshift s1 join ambshift s2 on s1.finishtime = s2.finishtime and s1.starttime = s2.starttime and s2.ambshiftcode ={$shift})"; } else { $shiftSql = "AND G.ambShiftCode = {$shift}"; } $cardNumber = isEmptyProperty($params, 'cardNumber'); $cardNumberSql = empty($cardNumber) ? '' : "AND L.cardNumber LIKE '%{$cardNumber}%'"; $priority = isEmptyProperty($params, 'priority'); $prioritySql = empty($priority) ? '' : "AND L.priority = {$priority}"; $search = isEmptyProperty($params, 'search', ''); $searchSql = ''; $isFio = isEmptyProperty($params, 'isFio'); if ($search) { $search = "%$search%"; if ($isFio) { $searchSql = "AND (L.fio LIKE :search OR (l.carddata::json->'mainCardBrigades'->'fioUpdate')::text LIKE :search)"; } else { $searchSql = "AND (L.fio LIKE :search OR L.address LIKE :search)"; } $sqlParams[':search'] = $search; } $dateTimeSql = ''; if (!$id) { $startDateTime = isUndefinedProperty($params, 'startDateTime'); $finishDateTime = isUndefinedProperty($params, 'finishDateTime', date("Y-m-d H:i:s")); $startDateTimeSql = ''; if ($dayNightCalls) { if ($startDateTime) { $startDateTimeSql = "
AND COALESCE(L.callSaveTime, L.callTime)::DATE >= :startDateTime::DATE
AND COALESCE(L.callSaveTime, L.callTime)::TIME >= :startTime::TIME"; $sqlParams[':startTime'] = dbTime($startDateTime); $sqlParams[':startDateTime'] = dbDateTime($startDateTime); } $dateTimeSql = "
AND (
    COALESCE(L.callSaveTime, L.callTime)::DATE <= :finishDateTime::DATE
    AND COALESCE(L.callSaveTime, L.callTime)::TIME <= :finishTime::TIME
    $startDateTimeSql
)"; $sqlParams[':finishTime'] = dbTime($finishDateTime); } else { if ($startDateTime) { $startDateTimeSql = "AND COALESCE(L.callSaveTime, L.callTime)::TIMESTAMP >= :startDateTime"; $sqlParams[':startDateTime'] = dbDateTime($startDateTime); } $dateTimeSql = "
AND (
COALESCE(L.callSaveTime, L.callTime)::TIMESTAMP <= :finishDateTime
$startDateTimeSql
)"; } $sqlParams[':finishDateTime'] = dbDateTime($finishDateTime); } $isIncompleted = isEmptyProperty($params, 'isIncompleted'); $isIncompletedSql = ''; if ($isIncompleted) { $isIncompletedSql = ' and L.endTime is null and L.ambGraphCode is not null'; } $offices = isEmptyProperty($params,'offices'); $officesSql = ''; if(!empty($offices)) { if (is_array($offices)) { $officesIn = createSqlInCondition($offices); $officesSql = "and L.enterprisestructurecodezone {$officesIn}"; } else { $offices = (int) $offices; $officesSql = "and L.enterprisestructurecodezone = {$offices}"; } } $advancedFilter = $this->createAdvancedFilterSql($params, $sqlParams); $limit = isEmptyProperty($params, 'limit', 0); $page = isEmptyProperty($params, 'page', 0) + 1; $limitSql = ''; if ($limit && $page){ $limitSql = "LIMIT " . ($page * $limit); } $sql = <<<EOT
            WITH mainSelect as (
                SELECT
                    L.ambLogCode            "id",
                    L.active                "active",
                    L.cardNumber            "cardNumber",
                    L.priority              "priority",
                    L.fio                   "fio",
                    L.address               "address",
                    L.ageDay                "ageDay",
                    l.ageMonth              "ageMonth",
                    L.ageYear               "ageYear",
                    L.inTown                "inTown",
                    L.ambgraphcode          "ambGraphId",
                    L.callTime              "callDate",
                    L.callTime              "callTime",
                    COALESCE(L.callSaveTime,L.callTime)
                                            "callSaveDate",
                    COALESCE(L.callSaveTime,L.callTime)
                                            "callSaveTime",
                    L.transferTime          "transferTime",
                    L.checkOutTime          "checkOutTime",
                    L.arrivalTime           "arrivalTime",
                    L.endTime               "endTime",
                    L.returnTime            "returnTime",
                    UD.value                "reasonDisplay",
                    T.ambTeamCode           "teamId",
                    T.teamNumber            "teamDisplay",
                    L.teamMembers           "teamMembers",
                    L.caller                "caller",
                    ESI.name                "officesName",
                    LF.isForwarding         "isForwarding",
                    L.appeal                "appeal",
                    L.callKind              "callKind",
                    L.location              "location",
                    L.dateofbirth           "dateOfBirth",
                    L.flat                  "kv",
                    L.callCancel            "callCancel", -- Отмена вызова
                    CASE WHEN G.ambShiftCode IS NOT NULL
                        THEN to_char(S.startTime, 'HH24:MI') || '-' ||  TO_CHAR(S.finishTime, 'HH24:MI')
                    ELSE NULL END           "shift"
                FROM AmbLog L
                LEFT JOIN UniDict UD ON UD.uniDictCode = L.uniDictCodeReason AND UD.dictNumber = 59
                LEFT JOIN AmbLogForwarding LF on LF.ambLogCode = L.ambLogCode
                LEFT JOIN AmbGraph G ON G.ambGraphCode = L.ambGraphCode
                LEFT JOIN AmbTeam T ON T.ambTeamCode = G.ambTeamCode
                LEFT JOIN AmbShift S ON S.ambShiftCode = G.ambShiftCode
                LEFT JOIN EnterpriseStructureInfo ESI ON ESI.EnterpriseStructureCode = L.enterprisestructurecodezone
                LEFT JOIN AmbLogDelivery ALD ON ALD.AmbLogCode = L.AmbLogCode
                LEFT JOIN AmbLogForwarding ALF ON ALF.AmbLogCode = L.AmbLogCode
                WHERE (fn_WebUserAccessOffice(L.enterprisestructurecodezone) = 1)
                {$idSql}
                {$activeSql}
                {$shiftSql}
                {$teamSql}
                {$cardNumberSql}
                {$isIncompletedSql}
                {$prioritySql}
                {$dateTimeSql}
                {$searchSql}
                {$stateFind}
                {$officesSql}
                {$advancedFilter}
                ORDER BY coalesce(L.callSaveTime,L.callTime) DESC, L.ambLogCode DESC
            ),
            countSelect AS (
                SELECT
                    COUNT(id) "amount"
                FROM mainSelect
            )
            SELECT
                MS.*,
                CS.amount
            FROM mainSelect MS
            LEFT JOIN countSelect CS on 1 = 1
            {$limitSql}
EOT;
return selectSql($db, $sql, $sqlParams); } private function createAdvancedFilterSql ($params, &$sqlParams) { $diagnosisId = isEmptyProperty($params, 'diagnosisId'); $diagnosisSql = ''; if (!empty($diagnosisId)) { $diagnosisSql = " AND EXISTS(
            SELECT
                ambLogDiagnosisCode
            FROM ambLogDiagnosis
            WHERE ambLogCode = L.ambLogCode AND medDiagnosisCode = :diagnosisId
            )"; $sqlParams[':diagnosisId'] = $diagnosisId; } $reasonId = isEmptyProperty($params, 'reasonId'); $reasonSql = ''; if (!empty($reasonId)) { if (is_array($reasonId)) { $reasonIdIn = createSqlInCondition($reasonId); $reasonSql = " AND L.UnidictCodeReason {$reasonIdIn}"; } else { $reasonSql = " AND L.UnidictCodeReason = :reasonId"; $sqlParams[':reasonId'] = (int) $reasonId; } } $isRepeated = isEmptyProperty($params, 'isRepeated'); $isRepeatedSql = ''; if (!empty($isRepeated)) { $isRepeatedSql = " AND L.repeatcall = :isRepeated"; $sqlParams[':isRepeated'] = $isRepeated; } $isPassing = isEmptyProperty($params,'isPassing'); $isPassingSql = ''; if (!empty($isPassing)) { $isPassingSql = " AND L.callKind = 3"; } $isForwarding = isEmptyProperty($params, 'isForwarding'); $isForwardingSql = ''; if (!empty($isForwarding)) { $isForwardingSql = " AND ALF.isForwarding = :isForwarding"; $sqlParams[':isForwarding'] = $isForwarding; } $isCancel = isEmptyProperty($params,'isCancel'); $isCancelSql = ''; if ($isCancel) { $isCancelSql = " AND l.callCancel = 1"; } $forwardingOrganizationId = isEmptyProperty($params, 'forwardingOrganizationId'); $forwardingOrganizationSql = ''; if (!empty($forwardingOrganizationId)) { $forwardingOrganizationSql = " AND ALF.enterpriseCode = :forwardingOrganizationId"; $sqlParams[':forwardingOrganizationId'] = $forwardingOrganizationId; } $isActiveSurveillance = isEmptyProperty($params, 'isActiveSurveillance'); $isActiveSurveillanceSql = ''; if (!empty($isActiveSurveillance)) { $isActiveSurveillanceSql = " AND L.activeObserve = :isActiveSurveillance"; $sqlParams[':isActiveSurveillance'] = $isActiveSurveillance; } $isInTown = isEmptyProperty($params, 'isInTown'); $isNotInTown = isEmptyProperty($params, 'isNotInTown'); switch (true) { case !empty($isInTown) && !empty($isNotInTown): $isInTownSql = ""; break; case !empty($isInTown): $isInTownSql = " AND L.inTown = 1"; break; case !empty($isNotInTown): $isInTownSql = " AND (L.inTown = 0 OR L.inTown IS NULL)"; break; default: $isInTownSql = ""; break; } $isNonresident = isEmptyProperty($params, 'isNonresident'); $isForeigner = isEmptyProperty($params, 'isForeigner'); switch (true) { case !empty($isNonresident) && !empty($isForeigner): $isResidentSql = " AND L.resident IS NOT NULL"; break; case !empty($isNonresident): $isResidentSql = " AND L.resident = 1"; break; case !empty($isForeigner): $isResidentSql = " AND L.resident = 2"; break; default: $isResidentSql = ""; break; } $ageFrom = isEmptyProperty($params, 'ageFrom'); $ageTo = isEmptyProperty($params, 'ageTo'); switch (true){ case !empty($ageFrom) && !empty($ageTo): if ($ageFrom == $ageTo){ $agesSql = " AND L.ageYear = :ageFrom"; } else { $agesSql = " AND (L.ageYear >= :ageFrom AND L.ageYear <= :ageTo)"; $sqlParams[':ageTo'] = $ageTo; } $sqlParams[':ageFrom'] = $ageFrom; break; case !empty($ageFrom): $agesSql = " AND L.ageYear >= :ageFrom"; $sqlParams[':ageFrom'] = $ageFrom; break; case !empty($ageTo): if ($ageTo == 1){ $agesSql = " AND ((L.ageYear <= :ageTo OR L.ageYear IS NULL) AND (L.ageMonth IS NOT NULL OR L.ageDay IS NOT NULL))"; } else { $agesSql = " AND L.ageYear <= :ageTo"; } $sqlParams[':ageTo'] = $ageTo; break; default: $agesSql = ""; break; } $sex = property_exists($params, 'sex') ? $params->sex : null; $sexSql = ''; if (!empty($sex)){ $sexSql = " AND L.sex = :sex"; $sqlParams[':sex'] = $sex; } else if ($sex === 0){ $sexSql = "AND L.sex IS NULL"; } $doctorId = isEmptyProperty($params, 'doctorId'); $doctorSql = ''; if (!empty($doctorId)){ if(is_array($doctorId)) { $doctorIdIn = createSqlInCondition($doctorId); $doctorSql = " AND EXISTS(
                SELECT
                    ambGraphPersonCode
                FROM AmbGraphPerson
                WHERE ambGraphCode = L.ambGraphCode AND medDoctorCode {$doctorIdIn}
                )"; } else { $doctorSql = " AND EXISTS(
                SELECT
                    ambGraphPersonCode
                FROM AmbGraphPerson
                WHERE ambGraphCode = L.ambGraphCode AND medDoctorCode = :doctorId
                )"; $sqlParams[':doctorId'] =(int) $doctorId; } } $resultTypes = isEmptyProperty($params, 'resultTypes'); $resultTypesSql = ''; if (!empty($resultTypes)){ $resultTypesSql = $this->createResultTypesSql($resultTypes, $params, $sqlParams); } $dispatcherUserId = isEmptyProperty($params, 'dispatcherUserId'); $dispatcherUserSql = ''; if (!empty($dispatcherUserId)){ $dispatcherUserSql = " AND L.userCodeDispatcher = :dispatcherUserId"; $sqlParams[':dispatcherUserId'] = $dispatcherUserId; } $isTimeoutExceeded = isEmptyProperty($params, 'isTimeoutExceeded'); $isTimeoutExceededSql = ''; if ($isTimeoutExceeded){ $isTimeoutExceededSql = $this->createIsTimeoutExceededSql($sqlParams); } $isTransmissionTimeExceeded = isEmptyProperty($params, 'isTransmissionTimeExceeded'); $isTransmissionTimeExceededSql = ''; if ($isTransmissionTimeExceeded){ $isTransmissionTimeExceededSql = $this->createIsTransmissionTimeExceededSql($sqlParams); } $isReassigned = isEmptyProperty($params,'reassigned'); $isReassignedSql = " AND L.callKind <> 11"; if (!empty($isReassigned)){ $isReassignedSql = " AND L.callKind = 11"; } $isLackDiagnosis = isEmptyProperty($params,'isLackDiagnosis'); $isLackDiagnosisSql = ''; if ($isLackDiagnosis) { $isLackDiagnosisSql = " AND NOT EXISTS(
            SELECT
                amblogdiagnosiscode
            FROM ambLogDiagnosis
            WHERE ambLogCode = L.ambLogCode AND diagnosistype = 1
            ) "; } $sql = "
        {$diagnosisSql}
        {$reasonSql}
        {$isRepeatedSql}
        {$isPassingSql}
        {$isForwardingSql}
        {$forwardingOrganizationSql}
        {$isActiveSurveillanceSql}
        {$isInTownSql}
        {$isResidentSql}
        {$agesSql}
        {$sexSql}
        {$doctorSql}
        {$dispatcherUserSql}
        {$resultTypesSql}
        {$isCancelSql}
        {$isTimeoutExceededSql}
        {$isTransmissionTimeExceededSql}
        {$isReassignedSql}
        {$isLackDiagnosisSql}
        "; return $sql; } public function getNotificationSettings () { if (!$this->notificationSettings) { $this->notificationSettings = readExtraSettings(null, 'ambNotificationSetup'); } return $this->notificationSettings; } private function createIsTimeoutExceededSql(&$sqlParams){ $settings = $this->getNotificationSettings(); $limitTimePoliceTown = isEmptyProperty($settings, 'limitTimePoliceTown', 0); $limitTimePoliceOther = isEmptyProperty($settings, 'limitTimePoliceOther', 0); $limitTimeTransportTown = isEmptyProperty($settings, 'limitTimeTransportTown', 0); $limitTimeTransportOther = isEmptyProperty($settings, 'limitTimeTransportOther', 0); $limitTimeRegularTown = isEmptyProperty($settings, 'limitTimeRegularTown', 0); $limitTimeRegularOther = isEmptyProperty($settings, 'limitTimeRegularOther', 0); $limitTimeExtraTown = isEmptyProperty($settings, 'limitTimeExtraTown', 0); $limitTimeExtraOther = isEmptyProperty($settings, 'limitTimeExtraOther', 0); return "
        and coalesce(L.callCancel, 0) = 0
        and coalesce(ALF.isForwarding, 0) = 0
        and
        (
            (
                /* неотложный */
                L.priority = 1 and
                (
                    (
                        (
                            coalesce(L.caller, '') LIKE 'Оперативно-дежурная служба'
                            and coalesce(UD.value, '') LIKE 'Констатация факта смерти'
                            and
                            (
                                (L.inTown = 1
                                    and
                                (coalesce(L.arrivalTime, 'now'::timestamp) - '$limitTimePoliceTown minutes'::interval) > coalesce(L.callSaveTime,L.callTime))
                                or
                                ((L.inTown IS NULL or L.inTown = 0)
                                    and
                                (coalesce(L.arrivalTime, 'now'::timestamp) - '$limitTimePoliceOther minutes'::interval) > coalesce(L.callSaveTime,L.callTime))
                            )
                        )
                        or
                        (
                            (coalesce(L.caller, '') LIKE 'Транспортировка' or coalesce(UD.value, '') LIKE 'Констатация факта смерти')
                            and
                            (
                                (L.inTown = 1
                                    and
                                (coalesce(L.arrivalTime, 'now'::timestamp) - '$limitTimeTransportTown minutes'::interval) > coalesce(L.callSaveTime,L.callTime))
                                or
                                ((L.inTown IS NULL or L.inTown = 0)
                                    and
                                (coalesce(L.arrivalTime, 'now'::timestamp) - '$limitTimeTransportOther minutes'::interval) > coalesce(L.callSaveTime,L.callTime))
                            )
                        )
                        or
                        (
                            coalesce(L.caller, '') NOT LIKE 'Транспортировка'
                            and coalesce(UD.value, '') NOT LIKE 'Констатация факта смерти'
                            and
                            (
                                (L.inTown = 1
                                    and
                                (coalesce(L.arrivalTime, 'now'::timestamp) - '$limitTimeRegularTown minutes'::interval) > coalesce(L.callSaveTime,L.callTime))
                                or
                                ((L.inTown IS NULL or L.inTown = 0)
                                    and
                                (coalesce(L.arrivalTime, 'now'::timestamp) - '$limitTimeRegularOther minutes'::interval) > coalesce(L.callSaveTime,L.callTime))
                            )
                        )
                    )
                )
            )
            or
            (
                /* экстренный */
                L.priority = 2
                and
                (
                    (L.inTown = 1
                        and
                    (coalesce(L.arrivalTime, 'now'::timestamp) - '$limitTimeExtraTown minutes'::interval) > coalesce(L.callSaveTime,L.callTime))
                    or
                    ((L.inTown IS NULL or L.inTown = 0)
                        and
                    (coalesce(L.arrivalTime, 'now'::timestamp) - '$limitTimeExtraOther minutes'::interval) > coalesce(L.callSaveTime,L.callTime))
                )
            )
        )
        "; } private function createIsTransmissionTimeExceededSql(&$sqlParams){ $settings = $this->getNotificationSettings(); $limitTransferTimeExtra = isEmptyProperty($settings, 'limitTransferTimeExtra', 0); $limitTransferTimeRegular = isEmptyProperty($settings, 'limitTransferTimeRegular', 0); $limitTransferTimeTransport = isEmptyProperty($settings, 'limitTransferTimeTransport', 0); $limitTransferTimePolice = isEmptyProperty($settings, 'limitTransferTimePolice', 0); return "
        and coalesce(L.callCancel, 0) = 0
        and coalesce(ALF.isForwarding, 0) = 0
        and
        (
            (
                /* неотложный */
                L.priority = 1 and
                (
                    (
                        (
                            coalesce(L.caller, '') LIKE 'Оперативно-дежурная служба'
                            and coalesce(UD.value, '') LIKE 'Констатация факта смерти'
                            and
                            (coalesce(L.transferTime, 'now'::timestamp) - '$limitTransferTimePolice minutes'::interval)
                                > coalesce(L.callSaveTime,L.callTime)
                        )
                        or
                        (
                            (coalesce(L.caller, '') LIKE 'Транспортировка' or coalesce(UD.value, '') LIKE 'Констатация факта смерти')
                            and
                            (coalesce(L.transferTime, 'now'::timestamp) - '$limitTransferTimeTransport minutes'::interval)
                                > coalesce(L.callSaveTime,L.callTime)
                        )
                        or
                        (
                            coalesce(L.caller, '') NOT LIKE 'Транспортировка'
                            and coalesce(UD.value, '') NOT LIKE 'Констатация факта смерти'
                            and
                            (coalesce(L.transferTime, 'now'::timestamp) - '$limitTransferTimeRegular minutes'::interval)
                                > coalesce(L.callSaveTime,L.callTime)
                        )
                    )
                )
            )
            or
            (
                /* экстренный */
                L.priority = 2
                and
                (coalesce(L.transferTime, 'now'::timestamp) - '$limitTransferTimeExtra minutes'::interval)
                    > coalesce(L.callSaveTime,L.callTime)
            )
        )
        "; } private function createResultTypesSql($types, $params, &$sqlParams) { $config = [ 'unsuccessful' => '(coalesce(L.departureResultFlags, 0) & 1) = 1', 'left' => '(coalesce(L.departureResultFlags, 0) & 2) = 2', 'transferred' => '(coalesce(L.departureResultFlags, 0) & 4) = 4', 'delivered' => '(coalesce(L.departureResultFlags, 0) & 8) = 8', 'died_with' => 'L.death = 1', 'died_without' => 'L.death = 2', 'refusal_med' => '(coalesce(L.departureResultFlags, 0) & 16) = 16', 'refusal_transportation' => '(coalesce(L.departureResultFlags, 0) & 32) = 32', 'refusal_hospitalization' => '(coalesce(L.departureResultFlags, 0) & 64) = 64', 'childbirth_with' => 'L.birth = 1', 'childbirth_without' => 'L.birth = 2' ]; $resultSql = ''; $additionalSql = ''; foreach ($types as $type){ if ($type === 'delivered') { $deliveredOrganizationId = isEmptyProperty($params, 'deliveredOrganizationId'); if (!empty($deliveredOrganizationId)) { $additionalSql = "AND ALD.EnterpriseCode = :deliveredOrganizationId"; $sqlParams[':deliveredOrganizationId'] = $deliveredOrganizationId; } } if (empty($resultSql)) { $resultSql = $config[$type]; } else { $resultSql .= " OR ($config[$type] $additionalSql)"; } } return " AND ($resultSql)"; } public function checkLogAccess($type) { switch ($type) { case 'add': $permission = self::LOG_ADD_PERMISSION; $errorText = self::ERROR_NO_ADD_PERMISSION; break; case 'edit': $permission = self::LOG_EDIT_PERMISSION; $errorText = self::ERROR_NO_EDIT_PERMISSION; break; case 'deleteToBin': $permission = self::LOG_DELETE_TO_BIN_PERMISSION; $errorText = self::ERROR_NO_DELETE_TO_BIN_PERMISSION; break; case 'deleteFinal': $permission = self::LOG_DELETE_FINAL_PERMISSION; $errorText = self::ERROR_NO_DELETE_FINAL_PERMISSION; break; case 'cardNumber': $permission = self::LOG_CARD_NUMBER_EDIT_PERMISSION; $errorText = self::ERROR_NO_CARD_NUMBER_EDIT_PERMISSION; break; default: return; } if (!testUserAccess($permission)) { throw new Exception($errorText, ERROR_NOT_ALLOWED); } } public function updateLog($records) { $db = $this->db ?: $this->db = connectDb(); $this->checkLogAccess('edit'); $db->beginTransaction(); try { $graphId = isEmptyProperty($records, 'ambGraphId'); $sql = <<<EOT
    update AmbLog set
        transferTime = case when ? = 1 then ?::time else transferTime end,
        checkOutTime = case when ? = 1 then ?::time else checkOutTime end,
        arrivalTime = case when ? = 1 then ?::time else arrivalTime end,
        endTime = case when ? = 1 then ?::time else endTime end,
        returnTime = case when ? = 1 then ?::time else returnTime end,
        ambGraphCode = case when ? = 1 then ? else ambGraphCode end,
        teamMembers = case when ? = 1 then ? else teamMembers end,
        inTown = case when ? = 1 then ? else inTown end,
        active = case when ? = 1 then ? else active end,
        callKind = case when ? = 1 then ? else callKind end
    where ambLogCode = ?
    returning AmbLogCode as id
EOT;
$result = selectSqlValue($db, $sql, [ propertyExistsSQL($records, 'transferTime'), isEmptyProperty($records, 'transferTime'), propertyExistsSQL($records, 'checkOutTime'), isEmptyProperty($records, 'checkOutTime'), propertyExistsSQL($records, 'arrivalTime'), isEmptyProperty($records, 'arrivalTime'), propertyExistsSQL($records, 'endTime'), isEmptyProperty($records, 'endTime'), propertyExistsSQL($records, 'returnTime'), isEmptyProperty($records, 'returnTime'), propertyExistsSQL($records, 'ambGraphId'), isEmptyProperty($records, 'ambGraphId'), propertyExistsSQL($records, 'teamMembers'), isEmptyProperty($records, 'teamMembers'), propertyExistsSQL($records, 'inTown'), isEmptyProperty($records, 'inTown'), propertyExistsSQL($records,'active'), isEmptyProperty($records,'active'), propertyExistsSQL($records,'callKind'), isEmptyProperty($records,'callKind'), isUndefinedProperty($records, 'id') ]); $ambLogId = $result; suspendJsonRpc(); require_once (__DIR__ . '/logEx.php'); resumeJsonRpc(); $logEx = new med_ambulance_logEx(); $logEx->createUpdateNotice($ambLogId, false); if ($graphId){ $sqlNotification = <<<"EOT"
    SELECT
        MD.UserCode as "userCode"
    FROM
        AmbGraphPerson A
            JOIN medDoctor MD on A.MedDoctorCode = MD.MedDoctorCode
    WHERE ambGraphCode = ?
    AND MD.usercode IS NOT NULL
EOT;
$doctors = selectSql($this->db, $sqlNotification, [$graphId], PDO::FETCH_NAMED); foreach ($doctors as $row) { $logEx->createMobileNotice($row, $ambLogId); } } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return $result; } public function sendTelegramMessage($params) { $db = $this->db ?: $this->db = connectDb(); $graphId = isUndefinedProperty($params, 'graphId'); $sqlTelegram = <<<EOT
            SELECT
                telegramCode
            FROM ambMobileDevice
            WHERE ambTeamCode = (SELECT ambTeamCode FROM ambGraph WHERE ambGraphCode = ?)
                AND ambTeamCode IS NOT NULL
            LIMIT 1
EOT;
$telegramId = selectSqlValue($db, $sqlTelegram, [$graphId], PDO::FETCH_NAMED); if ($telegramId) { $reassigned = isUndefinedProperty($params,'reassigned'); $requestParams = new stdClass(); $requestParams->id = isUndefinedProperty($params, 'id'); $requestParams->reassigned = $reassigned; $record = $this->readLog($requestParams)[0]; $record = (object)$record; $address = isEmptyProperty($record, 'address',' '); $birth = isEmptyProperty($record, 'dateOfBirth', ' '); $reason = isEmptyProperty($record,'reasonDisplay', ' '); $cardNumber = isEmptyProperty($record, 'cardNumber', ' '); $callTime = $this->createDateTimeField(isEmptyProperty($record, 'callSaveDate'), isEmptyProperty($record, 'callSaveTime')); $priority = isEmptyProperty($record, 'priority') === 1 ? 'Неотложный' : 'Экстренный'; $kv = isEmptyProperty($record,'kv') ? 'кв. ' . isEmptyProperty($record, 'kv') : ' '; $sex = isEmptyProperty($record, 'sex') === 0 ? 'М.' : 'Ж.'; $location = isEmptyProperty($record, 'location'); $locationStr = ' '; switch ($location) { case 1: $locationStr = 'улица'; break; case 2: $locationStr = 'рабочее место'; break; case 3: $locationStr = 'квартира'; break; case 4: $locationStr = 'общественное место'; break; case 5: $locationStr = 'организация здравоохранения'; break; } $path = "https://api.telegram.org/bot5319443750:AAFjdj9PoJdBvSjtZ5YvPSQTtIPQww04hC0/sendmessage"; $text = ($reassigned ? '<b>ВЫЗОВ ОТМЕНЕН</b>'. "\n" : '') . $priority . "\n". 'Адрес: ' . $address . ' ' . $kv . "\n". 'Дата рождения: ' . $birth . "\n". 'Пол: ' . $sex . "\n". 'Повод: ' . $reason . "\n". 'Место нахождения: ' . $locationStr . "\n". 'Карта №: ' . $cardNumber . ', дата и время вызова: ' . $callTime . "\n" ; $data = array('chat_id' => $telegramId, 'text' => $text, 'parse_mode' => 'HTML'); $options = array( 'http' => array( 'header' => "Content-type: application/x-www-form-urlencoded\r\n", 'method' => 'POST', 'content' => http_build_query($data) ), 'ssl' => array( 'verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true ) ); $context = stream_context_create($options); try { $message = file_get_contents($path, false, $context); app_log($message); } catch (Throwable $e) { app_log('Сообщение для ' . $telegramId . ' не отправлено '. json_encode($e,JSON_UNESCAPED_UNICODE)); } } else { app_log('Не найден telegramId для бригады из графика № ' . $graphId); } } private function createDateTimeField($date, $time) { if (empty($date) && empty($time)) { return null; } $_date = new DateTime(empty($date) ? '19000101' : $date); $_time = new DateTime(empty($time) ? '00:00:00' : $time); return date($_date->format('Y-m-d') . ' ' . $_time->format('H:i:s')); } public function deleteLog($records) { $db = $this->db ?: $this->db = connectDb(); $userId = getSessionValue('mapUserCode'); $this->checkLogAccess('deleteToBin'); $sql = <<<EOT
            UPDATE ambLog SET
                active = NULL,
                returnTime = NULL,
                endTime = 'NOW'::DATE,
                userCode = {$userId}
            WHERE ambLogCode = ?
EOT;
$deleteFinally = isEmptyProperty($records, 'deleteFinally'); if ($deleteFinally){ $this->checkLogAccess('deleteFinal'); $sql = <<<EOT
                DELETE FROM ambLog
                WHERE ambLogCode = ?
EOT;
} $stmt = $db->prepare($sql); if (is_object($records)) { $records = [$records]; } $db->beginTransaction(); try { foreach ($records as $row) { $id = isEmptyProperty($row, 'id'); $stmt->execute([$id]); if (!$deleteFinally) { med_ambulance_logStats::updateMedEmergency($this->db, $id); } suspendJsonRpc(); require_once (__DIR__ . '/logEx.php'); resumeJsonRpc(); $logEx = new med_ambulance_logEx(); $logEx->createUpdateNotice($id, false); } $db->commit(); } catch (Throwable $e) { $db->rollback(); throw $e; } return true; } } handleJsonRpc('med_ambulance_log');