<?php
 require_once('utilsTrait.php'); trait med_cisz_documentReferenceTrait { use med_cisz_utilsTrait; public function createDocumentReferences($sourceCode = null, $type = null, $docId = null): void { if (isset($this->visitPatient) && !($visit = $this->visitPatient[array_key_first($this->visitPatient)] ?? false)) { return; } elseif (!$sourceCode && !$docId && !$this->entity == 'MedStatCard') { return; } if ($this->entity == 'MedStatCard') { $data = selectSql($this->db, <<<SQL
                SELECT
                    MEF.MedExternalFileCode AS "id",
                    MEF.CreateTime AS "createTime",
                    MEF.UserCode AS "creatorId",
                    MEF.version,
                    MEF.FilePath AS "filePath",
                    MEF.FileName AS "fileName",
                    MEF.FileTime AS "fileTime",
                    MEF.data,
                    CRV.code AS "type"
                FROM
                    MedExternalFile MEF
                LEFT JOIN CiszResourceValue CRV on CRV.CiszResourceValueCode = MEF.CiszResourceValueCodeKindOfAttach
                WHERE
                    MEF.Source = 41
                    AND MEF.SourceCode = ?
                SQL,
[$visit->visitPatientId], PDO::FETCH_OBJ ); } elseif ($docId) { $data = selectSql($this->db, <<<SQL
                SELECT
                    MEF.MedExternalFileCode AS "id",
                    MEF.CreateTime AS "createTime",
                    MEF.UserCode AS "creatorId",
                    MEF.version,
                    MEF.FilePath AS "filePath",
                    MEF.FileName AS "fileName",
                    MEF.FileTime AS "fileTime",
                    MEF.data,
                    CRV.code AS "type"
                FROM
                    MedExternalFile MEF
                LEFT JOIN CiszResourceValue CRV on CRV.CiszResourceValueCode = MEF.CiszResourceValueCodeKindOfAttach
                WHERE
                    MEF.Source = ?
                    AND MEF.MedExternalFileCode = ?
                SQL,
[ $type, $docId ], PDO::FETCH_OBJ ); } elseif ($type) { $data = selectSql($this->db, <<<SQL
                SELECT
                    MEF.MedExternalFileCode AS "id",
                    MEF.CreateTime AS "createTime",
                    MEF.UserCode AS "creatorId",
                    MEF.version,
                    MEF.FilePath AS "filePath",
                    MEF.FileName AS "fileName",
                    MEF.FileTime AS "fileTime",
                    MEF.data,
                    CRV.code AS "type"
                FROM
                    MedExternalFile MEF
                LEFT JOIN CiszResourceValue CRV on CRV.CiszResourceValueCode = MEF.CiszResourceValueCodeKindOfAttach
                WHERE
                    MEF.Source = ?
                    AND MEF.SourceCode = ?
                SQL,
[ $type, $sourceCode ], PDO::FETCH_OBJ ); } else { $data = selectSql($this->db, <<<SQL
                SELECT
                    MEF.MedExternalFileCode AS "id",
                    MEF.CreateTime AS "createTime",
                    MEF.UserCode AS "creatorId",
                    MEF.version,
                    MEF.FilePath AS "filePath",
                    MEF.FileName AS "fileName",
                    MEF.FileTime AS "fileTime",
                    MEF.data,
                    CRV.code AS "type" 
                FROM
                    MedExternalFile MEF
                LEFT JOIN CiszResourceValue CRV on CRV.CiszResourceValueCode = MEF.CiszResourceValueCodeKindOfAttach
                WHERE
                    MEF.Source = 10
                    AND MEF.SourceCode = ?
                SQL,
[$visit->visitPatientId], PDO::FETCH_OBJ ); } if (!$data) { return; } foreach ($data as $row) { if ($row->data && ($content = stream_get_contents($row->data)) ) { $id = generateUUID(null, null, true); $pathInfo = pathinfo($row->fileName); $ext = strtolower($pathInfo['extension']); if (!in_array($ext, ['pdf', 'jpg', 'jpeg'])) { continue; } if (isset($type) && $type === 112) { $row->type = 'med-doc-not-struct'; $this->placeOfCareDocuments[] = self::buildDocumentBody( $id, self::getKindOfAttachDocument($row), $this->patient->link, $content, $pathInfo['filename'], $this->organization->link ); } $this->documentReference[] = self::buildDocumentBody( $id, self::getKindOfAttachDocument($row), $this->patient->link, $content, $pathInfo['filename'], $this->organization->link ); } } } private static function getKindOfAttachDocument(object $data): string { if (isset($data->type)) { return $data->type; } return 'result-other'; } private static function buildDocumentBody(string|int $id, string $kind, object $patientLink, string $content, string $title = '', ?object $organization = null): object { $finfo = finfo_open(FILEINFO_MIME_TYPE); $mime_type = finfo_buffer($finfo, $content); return (object) [ 'fullUrl' => 'DocumentReference/' . $id, 'resource' => (object) [ 'resourceType' => 'DocumentReference', 'id' => (string) $id, 'meta' => (object) [ 'profile' => [med_cisz_constant::$structureDefinitions['AttachDocument']] ], 'status' => 'current', 'identifier' => [ (object) [ 'value' => (string) $id, 'assigner' => $organization ] ], 'type' => self::getCodingStructure( med_cisz_constant::$valueSet['KindOfAttachDocument'], $kind ), 'subject' => $patientLink, 'date' => date('c'), 'custodian' => $organization, 'author' => [ $organization ], 'content' => [ (object) [ 'attachment' => (object) [ 'contentType' => $mime_type, 'data' => base64_encode($content), 'title' => $title, ], ], ], ], ]; } }