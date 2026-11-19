<?php
 require_once(__DIR__.'/../../init.php'); class crm_passworddb_requests { const NOTICE_REQUEST_TYPE = 'crm-passworddb-new-request'; const NOTICE_APPROVE_TYPE = 'crm-passworddb-new-approval'; private $db; public function readRequests($params = null) { $db = $this->db ?: $this->db = connectDb(); $userId = getSessionValue('user'); $sqlParams = []; $isApprovals = isEmptyProperty($params, 'isApprovals', false); $isLog = isEmptyProperty($params, 'isLog', false); $requestId = isEmptyProperty($params, 'requestId', 0); $orderBy = 'order by
    case when status = 1 then 0 else 1 end,
    case when status = 1 then request_time end asc,
    case when status <> 1 then request_time end desc'; $where = 'where PDR.role_author_id = ' . $userId; if ($isApprovals) { $where = 'where PDR.status = 1 or PDR.role_actor_id = ' . $userId; } else if ($isLog) { $where = ''; } else if ($requestId) { $where = 'where PDR.id = ?'; array_push($sqlParams, $requestId); } $sql = <<<"EOT"
select
    PDR.id "id",
    PDR.role_author_id "roleId",
    PDR.db_id "dbId",
    PDR.status "status",
    PDR.request_time "requestTime",
    PDR.request_note "authorNote",
    PDR.rejection_note "actorNote",
    PDR.change_time "changeTime",
    PDR.role_actor_id "actorId",
    RU2.full_name "actorFullName",
    CLD.id "dbId",
    CLD.name "dbName",
    CLD.client_id "clientId",
    CL.name "clientName",
    RU.full_name "authorFullName"
from password_db_request PDR
left join rbac_user RU on PDR.role_author_id = RU.role_id
left join rbac_user RU2 on PDR.role_actor_id = RU2.role_id
join client_list_db CLD on PDR.db_id = CLD.id
join client_list CL on CLD.client_id = CL.id
$where
$orderBy
EOT;
$ret = selectSql($db, $sql, $sqlParams); return $ret; } public function updateRequests ($record) { $db = $this->db ?: $this->db = connectDb(); $userId = getSessionValue('user'); $isApprove = (isUndefinedProperty($record, 'status') && $record->status !== 1); if ($isApprove) { $record->actorId = $userId; } $result = updateSql($db, 'password_db_request', 'id', $record, "db_id, status, request_note, rejection_note, role_actor_id", "dbId, status, authorNote, actorNote, actorId" ); $this->createNotice($record->id, $isApprove); return $result; } public function deleteRequests ($record) { $db = $this->db ?: $this->db = connectDb(); $this->deleteNotice($record->id); return deleteSql($db, 'password_db_request', 'id', $record); } public function createRequests ($record) { $userId = getSessionValue('user'); $db = $this->db ?: $this->db = connectDb(); $sql = <<<"EOT"
select exists(
    select
        PDR.id "id"
    from password_db_request PDR
    where role_author_id = ? and db_id = ? and status = 1
)
EOT;
$exists = selectSqlValue($db, $sql, [ $userId, isUndefinedProperty($record, 'dbId') ]); if ($exists) { throw new Exception('На данный момент уже имеется заявка на пароль к выбранной базе данных'); } $createdEl = insertSql($db, 'password_db_request', $record, "role_author_id, db_id, status, request_note", "dbId, authorNote", "$userId, ?, 1, ?", 'id', true, 'password_db_request_id_seq' )[0]; $this->createNotice($createdEl->id); return $createdEl; } public function createNotice($sourceId, $isApprove = false) { $notice = new stdClass(); $userId = getSessionValue('user'); $notice->type = ($isApprove ? self::NOTICE_APPROVE_TYPE : self::NOTICE_REQUEST_TYPE); $notice->source = (string)$sourceId; $notice->validTime = (new DateTime())->add(new DateInterval('P2D')); $notice->userCodeAuthor = $userId; $params = new stdClass(); $params->requestId = $sourceId; $element = $this->readRequests($params)[0]; if ($isApprove) { $notice->singleton = 1; $notice->userCode = $element['roleId']; } $notice->data = $element; common_notices::createNotice($notice); } public function deleteNotice($sourceId) { common_notices::deleteNotice((object)[ 'type' => self::NOTICE_REQUEST_TYPE, 'source' => $sourceId ]); } public function getPassword ($params) { $db = $this->db ?: $this->db = connectDb(); $id = isEmptyProperty($params, 'id'); $sql = <<<"EOT"
    select
        PDR.pwd "password"
    from password_db_request PDR
    where id = ?
EOT;
$password = selectSqlValue($db, $sql, [ $id ]); return $password ? $password : 'Здесь будет пароль'; } } handleJsonRpc('crm_passworddb_requests');