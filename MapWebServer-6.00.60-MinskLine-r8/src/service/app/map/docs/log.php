<?php
 require_once(__DIR__.'/../../init.php'); class map_docs_log { private $db; public function readLog($params) { $db = $this->db ?: $this->db = connectDb(); $id = isUndefinedProperty($params, 'pageId', 0); return selectSql($db, 'select
                h.htmPageCode "id",
                h.Type || \' - \' || h.Name "name",
                coalesce(w.Nick, u.Nick) "userName",
                h.Note "note",
                h.CreateTime "createTime"
            from htmPageT h
            left outer join Users u on u.UserCode = h.UserCode
            left outer join WebUser w on w.UserCode = u.UserCode
            where h.htmPageCodeParent = ?', [$id]); } } $service = new Junior\Server(new map_docs_log()); $service->process(); 