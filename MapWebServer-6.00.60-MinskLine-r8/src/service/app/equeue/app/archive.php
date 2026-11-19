<?php
require_once(__DIR__.'/../../init.php'); class equeue_app_archive { private $db; public function readArchive($params){ $db = $this->db ?: $this->db = connectDb(); $mapEnterpriseCode = getSessionValue('mapEnterpriseCode'); $serviceId = (int) $params->service; $optionId = (int) $params->option; $categoryId = (int) $params->category; $state = (int) $params->state; $infoboxId = (int) $params->infobox; $owpId = (int) $params->owp; $userId = (int)$params->user; if (isset($params->page)) $page = (int) $params->page; ELSE $page = 1; $limit = (int) $params->limit; $search = (string) $params->search; if ($limit <= 0) $limit = 25; $start = (int)(($page-1) * $limit) + 1; if ($start <= 0) $start = 1; if (safe_strlen($search) == 1) $start = 1; $sort = isEmptyProperty($params, 'sort', ''); $sort = $sort == 'stateText' ? 'state' : $sort; $dir = $params->dir; $sqlTextState = ''; switch ($state){ CASE 1: $sqlTextState = 'AND mec.state = 2'; break; CASE 2: $sqlTextState = 'AND mec.state IN (-1, 0, 1)'; break; CASE 3: $sqlTextState = 'AND mec.state = 3'; break; CASE 4: $sqlTextState = 'AND mec.state IN (4, 5)'; break; } $sql = <<<EOT
            SELECT
                COUNT(*) total
            FROM medEqCoupon mec
                LEFT JOIN medEqService mes ON mes.medEqServiceCode = mec.medEqServiceCode
                LEFT JOIN medEqPatientCategory mepc ON mepc.medEqPatientCategoryCode = mec.medEqPatientCategoryCode
                LEFT JOIN medEqInfobox mei ON mei.medEqInfoboxCode = mec.medEqInfoboxCode AND mei.enterpriseCode = $mapEnterpriseCode
                LEFT JOIN medEqServiceOption meso ON meso.medEqServiceOptionCode = mec.medEqServiceOptionCode
                LEFT JOIN medPatient mp ON mp.medPatientCode = mec.medPatientCode AND mp.enterpriseCode = $mapEnterpriseCode
                LEFT JOIN medEqOWP meo ON meo.medEqOWPCode = mec.medEqOWPCode AND meo.enterpriseCode = $mapEnterpriseCode
                LEFT JOIN medLogStudy mls ON mls.medLogStudyCode = mec.medLogStudyCode
                LEFT OUTER JOIN webUser wu ON wu.userCode = mec.userCode
            WHERE (
                    (mec.medEqServiceCode = $serviceId) OR ($serviceId = 0)
                ) AND (
                    (mec.medEqServiceOptionCode = $optionId) OR ($optionId = 0)
                ) AND (
                    (mec.medEqPatientCategoryCode = $categoryId) OR ($categoryId = 0)
                ) AND (
                    (mec.medEqInfoboxCode = $infoboxId) OR ($infoboxId = 0)
                ) AND (
                    (mec.medEqOWPCode = $owpId) OR ($owpId = 0)
                ) AND (
                    (mec.userCode = $userId) OR ($userId = 0)
                ) AND (
                    (E'$search' = E'') OR (
                        (
                            (
                                CASE WHEN NULLIF (mes.cipher, E'') IS NULL THEN E''
                                    ELSE mes.cipher || E' - '
                                END
                            ) || mes.name LIKE E'%$search%'
                        )
                        OR (mepc.name LIKE E'%$search%')
                        OR (
                            (
                                CASE WHEN NULLIF (mei.cipher, E'') IS NULL THEN E''
                                    ELSE mei.cipher || E' - '
                                END
                            ) || mei.name LIKE E'%$search%'
                        )
                        OR (meso.name LIKE E'%$search%')
                        OR (
                            CASE WHEN mec.medLogStudyCode IS NULL THEN Fn_cmFIO(mp.ManCode) ||
                                (
                                    CASE WHEN mp.numberHistorySickness IS NULL THEN E''
                                        ELSE E'[' || mp.numberHistorySickness || E'] '
                                    END
                                )
                            ELSE mls.PatientFIO ||
                                (
                                    CASE WHEN mls.numberHistorySickness IS NULL THEN E''
                                        ELSE E' [' || mls.numberHistorySickness || E'] '
                                    END
                                ) END LIKE E'%$search%'
                        )
                        OR (
                            (
                                CASE WHEN meo.cabinet IS NULL THEN E''
                                    ELSE meo.cabinet || E', '
                                END
                            ) || meo.number || E' ' || meo.name LIKE E'%$search%'
                        )
                        OR (mec.DocumentNumber LIKE E'%$search%')
                        OR (mec.PIN LIKE E'%$search%')
                        OR (
                            (
                                CASE WHEN NULLIF (mls.DirNumber, E'') IS NULL THEN E''
                                    ELSE mls.DirNumber || E', '
                                END
                            ) || DATEFORMAT (mls.DirTime, E'YYYY-MM-DD') LIKE E'%$search%'
                        )
                        OR (wu.nick LIKE E'%$search%')
                    )
                ) $sqlTextState
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $total = (int)fetchValue($stmt, 'total'); if ($total < $start){ $start = 1; } $sql = <<<EOT
            SELECT
                mec.medEqCouponCode                             "id",
                mec.medEqServiceCode                            "serviceId",
                (
                    CASE WHEN NULLIF (mes.cipher, E'') IS NULL THEN E''
                        ELSE mes.cipher || E' - '
                    END
                ) || mes.name                                   "serviceName",
                mec.medEqPatientCategoryCode                    "categoryId",
                mepc.name                                       "categoryName",
                mec.medEqInfoboxCode                            "infoboxId",
                (
                    CASE WHEN NULLIF (mei.cipher, E'') IS NULL THEN E''
                        ELSE mei.cipher || E' - '
                    END
                ) || mei.name                                   "infoboxName",
                mec.medEqServiceOptionCode                      "optionId",
                meso.name                                       "optionName",
                DATE_TRUNC('second', mec.createTime)            "createTime",
                DATE_TRUNC('second', mec.startTime)             "startTime",
                DATE_TRUNC('second', mec.finishTime)            "finishTime",
                mec.medPatientCode                              "patientId",
                CASE WHEN mec.medLogStudyCode IS NULL THEN Fn_cmFIO(mp.manCode) ||
                    (
                        CASE WHEN mp.numberHistorySickness IS NULL THEN E''
                            ELSE E'[' || mp.numberHistorySickness || E'] '
                        END
                    )
                    ELSE mls.patientFIO ||
                        (
                            CASE WHEN mls.numberHistorySickness IS NULL THEN E''
                                ELSE E' [' || mls.numberHistorySickness || E'] '
                            END
                        )
                END                                             "patientName",
                mec.medEqOWPCode                                "owpId",
                (
                    CASE WHEN meo.cabinet IS NULL THEN E''
                        ELSE meo.cabinet || E', '
                    END
                ) || meo.number || E' ' || meo.name             "owpName",
                mec.documentNumber                              "documentNumber",
                mec.state                                       "state",
                mec.resultPriority                              "resultPriority",
                mec.pin                                         "pin",
                mec.medLogStudyCode                             "logStudyId",
                (
                    CASE WHEN NULLIF (mls.DirNumber, E'') IS NULL THEN E''
                        ELSE mls.DirNumber || E', '
                    END
                ) || DATEFORMAT (mls.DirTime, E'YYYY-MM-DD')    "logStudyName",
                mec.userCode                                    "userId",
                wu.Nick                                         "userName"
            FROM medEqCoupon mec
            LEFT JOIN medEqService mes ON mes.medEqServiceCode = mec.medEqServiceCode
            LEFT JOIN medEqPatientCategory mepc ON mepc.medEqPatientCategoryCode = mec.medEqPatientCategoryCode
            LEFT JOIN medEqInfobox mei ON mei.medEqInfoboxCode = mec.medEqInfoboxCode AND mei.enterpriseCode = $mapEnterpriseCode
            LEFT JOIN medEqServiceOption meso ON meso.medEqServiceOptionCode = mec.medEqServiceOptionCode
            LEFT JOIN medPatient mp ON mp.medPatientCode = mec.medPatientCode AND mp.enterpriseCode = $mapEnterpriseCode
            LEFT JOIN medEqOWP meo ON meo.medEqOWPCode = mec.medEqOWPCode AND meo.enterpriseCode = $mapEnterpriseCode
            LEFT JOIN medLogStudy mls ON mls.medLogStudyCode = mec.medLogStudyCode
            LEFT OUTER JOIN webUser wu ON wu.userCode = mec.userCode
            WHERE (
                (mec.medEqServiceCode = $serviceId) OR ($serviceId = 0)
            ) AND (
                (mec.medEqServiceOptionCode = $optionId) OR ($optionId = 0)
            ) AND (
                (mec.medEqPatientCategoryCode = $categoryId) OR ($categoryId = 0)
            ) AND (
                (mec.medEqInfoboxCode = $infoboxId) OR ($infoboxId = 0)
            ) AND (
                (mec.medEqOWPCode = $owpId) OR ($owpId = 0)
            ) AND (
                (wu.WebUserCode = $userId) OR ($userId = 0)
            ) AND (
                (E'$search' = E'') OR (
                    (
                        (
                            CASE WHEN NULLIF (mes.cipher, E'') IS NULL THEN E''
                                ELSE mes.cipher || E' - '
                            END
                        ) || mes.name LIKE E'%$search%'
                    )
                    OR (mepc.name LIKE E'%$search%')
                    OR (
                        (
                            CASE WHEN NULLIF (mei.cipher, E'') IS NULL THEN E''
                                ELSE mei.cipher || E' - '
                            END
                        ) || mei.name LIKE E'%$search%'
                    )
                    OR (meso.name LIKE E'%$search%')
                    OR (
                        CASE WHEN mec.medLogStudyCode IS NULL THEN Fn_cmFIO(mp.ManCode) ||
                            (
                                CASE WHEN mp.numberHistorySickness IS NULL THEN E''
                                    ELSE E'[' || mp.numberHistorySickness || E'] '
                                END
                            )
                            ELSE mls.PatientFIO ||
                            (
                                CASE WHEN mls.numberHistorySickness IS NULL THEN E''
                                    ELSE E' [' || mls.numberHistorySickness || E'] '
                                END
                            )
                        END LIKE E'%$search%'
                    )
                    OR (
                        (
                            CASE WHEN meo.cabinet IS NULL THEN E''
                                ELSE meo.cabinet || E', '
                            END
                        ) || meo.number || E' ' || meo.name LIKE E'%$search%'
                    )
                    OR (mec.documentNumber LIKE E'%$search%')
                    OR (mec.pin LIKE E'%$search%')
                    OR (
                        (
                            CASE WHEN NULLIF (mls.dirNumber, E'') IS NULL THEN E''
                                ELSE mls.DirNumber || E', '
                            END
                        ) || DATEFORMAT(mls.dirTime, E'YYYY-MM-DD') LIKE E'%$search%'
                    )
                    OR (wu.nick LIKE E'%$search%')
                )
            ) $sqlTextState
            ORDER BY "$sort" $dir
            LIMIT $limit OFFSET $start
EOT;
$stmt = $db->prepare($sql); $stmt->execute(); $root = new stdClass(); $root->total = $total; $root->items = $stmt->fetchAll(); return $root; } } handleJsonRpc('equeue_app_archive');