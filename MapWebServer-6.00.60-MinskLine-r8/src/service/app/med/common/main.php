<?php
require_once(__DIR__.'/../../map/common/main.php'); $serviceClass = 'med_common_main'; class med_common_main extends map_common_main { public function changeApp () { $ret = parent::changeApp(); $db = $this->db ?: $this->db = connectDb(); $sql = <<<EOT
            SELECT {$db->sybase('first')}
                md.medDoctorCode        "MedDoctorCode",
                md.medProfessionCode    "MedProfessionCode",
                md.departmentCode       "DepartmentCode"
            FROM medDoctor md
            -- join Department d on d.DepartmentCode = md.DepartmentCode            -- WS-970
            --    and coalesce(d.CloseDate, 30000101) > {$db->dialect("now()", "(clock_timestamp()::timestamp)")}
            -- join Branch b on b.BranchCode = d.BranchCode
            --    and coalesce(b.CloseDate, 30000101) > {$db->dialect("now()", "(clock_timestamp()::timestamp)")}
            --    and b.EnterpriseCode = ?
            WHERE md.userCode = ?
            {$db->postgre('LIMIT 1')}
EOT;
$stmt = $db->prepare($sql); $stmt->execute([ getSessionValue('mapUserCode') ]); if (empty($res = $stmt->fetch())) { $res = [ 'MedDoctorCode' => null, 'MedProfessionCode' => null, 'DepartmentCode' => null ]; } setSessionValue('mapMedDoctor', $res); $ret->mapMedDoctor = $res; $ret->access = getUserAccess([ 'med-common-doctors-setting-publication', 'med-ambulance-map', 'med-common-patients-add', 'med-common-patients-edit-and-delete', 'med-reception-homevisit-delete-transferred', 'med-reception-homevisit-add-past-date', 'med-ambulance-log-add', 'med-ambulance-log-deletetobin', 'med-ambulance-log-delete', 'med-ambulance-log-edit', 'med-ambulance-logmapcallbrigades-callnumber-edit', 'med-ambulance-graph-edit', 'med-ambulance-teamscontrol-brigade-activation', 'med-card-cards_deny_delete_visit_not_own', 'med-card-cards_deny_update_visit_not_own', 'med-card-cards_deny_delete_html_not_own', 'med-card-cards_deny_update_html_not_own', 'med-qualitycontrol-log-adding', 'med-qualitycontrol-log-other-users-editing', 'med-qualitycontrol-log-other-users-deleting', 'med-qualitycontrol-log-deleting', 'med-qualitycontrol-log-editing-responses-to-criteria', 'med-qualitycontrol-adding-medical-document', 'med-qualitycontrol-deleting-medical-document', 'med-qualitycontrol-log-editing-closed', 'med-dayhospital-log-add', 'med-dayhospital-log-other-users-edit', 'med-dayhospital-log-other-users-delete', 'med-clinicstats-jobdoctorlist-fact-edit', 'med-reception-coupons-previous-add', 'map-common-organizations-add-edit-delete', 'map-common-offices-add-edit-delete', 'med-common-certificatelog-status-request' ]); $ret->settingsMed = new stdClass(); $settingsMed = readJsonSettings('settings_med'); if ($settingsMed) { $ret->settingsMed->autoRefreshCardsInterval = $settingsMed['autoRefreshCardsInterval'] ?? 10; } return $ret; } }