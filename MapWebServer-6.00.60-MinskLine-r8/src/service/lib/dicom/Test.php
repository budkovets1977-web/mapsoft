<?php
 $query = new \DicomDcm; $query->setDump(<<<"EOT"
(0008,0052) CS [IMAGE]     	# QueryRetrieveLevel
(0008,0016) UI [1.2.840.10008.5.1.4.1.1.88.11] # SOP Class UID
(0008,0020) DA [20180212]           # StudyDate
(0010,0020) LO []           # PatientID
(0020,000d) UI []           # StudyInstanceUID
(0020,000e) UI []           # SeriesInstanceUID
(0008,0018) UI []           # SOP Instance UID
(0020,0010) SH []           # StudyID
(0008,0030) TM []           # StudyTime
(0010,0010) PN []     		# PatientName
(0010,0030) DA []           # PatientBirthDate
(0010,0040) CS []           # PatientSex
EOT
); $query->set('0010,0020 [12345678]'); $query->get('0010,0010'); $query->setDump((object)[ '0008,0052' => 'IMAGE', '0008,0016' => '1.2.840.10008.5.1.4.1.1.88.11', '0008,0020' => ['vr' => 'DA', 'Value' => '20180212'], '0020,000d' => '', '0020,0020' => '', '0020,000e' => '', '0010,0020' => '', '0010,0020' => '', '0010,0020' => '', '0010,0020' => '', '0010,0020' => '', (0020,000d) UI [] (0020,000e) UI [] (0008,0018) UI [] (0020,0010) SH [] (0008,0030) TM [] (0010,0010) PN [] (0010,0030) DA [] (0010,0040) CS [] <<<"EOT"
(0008,0052) CS [IMAGE]     	# QueryRetrieveLevel
(0008,0016) UI [1.2.840.10008.5.1.4.1.1.88.11] # SOP Class UID
(0008,0020) DA [20180212]           # StudyDate
(0010,0020) LO []           # PatientID
(0020,000d) UI []           # StudyInstanceUID
(0020,000e) UI []           # SeriesInstanceUID
(0008,0018) UI []           # SOP Instance UID
(0020,0010) SH []           # StudyID
(0008,0030) TM []           # StudyTime
(0010,0010) PN []     		# PatientName
(0010,0030) DA []           # PatientBirthDate
(0010,0040) CS []           # PatientSex
EOT
); $sr = new \DicomDcm("file.dcm"); $cnt = $sr->count("0040,a730"); for ($i = 0; $i < $cnt; ++$i) { $label = $sr->get("0040,a730[$i]0040,a043[0]0008,0104"); $content = $sr->get("0040,a730[$i]0040,a160"); } $sr = new \DicomDcm("file.dcm"); $sr->to_key('0040,a730'); for ($i = 0; $i < $sr->count(); ++$i) { $label = $sr->get($i, '0040,a043[0]0008,0104'); $content = $sr->get($i, '0040,a160'); } $sr = new \DicomDcm("file.dcm"); $contents = $sr->tag('0040,a730')->tag(0, '0040,a043')->value() for ($i = 0; $i < $contents->count(); ++$i) { $c = $contents->at[$i]; $label = $c->get(0, '0040,a043'); $content = $c->get('0040,a160'); } $query = new DicomDcm(); $query->add(new DicomElement('0008,0020', '20180212')); $query->add(new DicomElement('0010,0020')); $content = new DicomElement('0040,a730', null, 'SQ'); $item = new DicomItem(); $item->add(new DicomElement('0008,0020', '20180212')) $query = new DicomDcm(); $query->add('0008,0020', '20180212'); $query->add('0010,0020'); $content = $query->add('0040,a730', null, 'SQ'); $item = $content->add(); $item->add('0008,0020', '20180212'); 