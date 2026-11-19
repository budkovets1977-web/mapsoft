-- Разрешение «Мониторинг ЦИСЗ» (med-cisz-monitor) для страницы-монитора
--   http://<сервер>:8080/cisz-monitor.html
--
-- Узел — «чистое» разрешение БЕЗ пункта меню (ModuleFileName NULL, State=6):
-- если создать пункт меню с несуществующим виджетом, он появится у всех
-- пользователей в «Администрировании» (неизвестные права по умолчанию
-- разрешены) и может ломать загрузку приложения.
-- Строка в WebUserPermission c PermissionDefault=0 делает право ЗАПРЕЩЁННЫМ
-- по умолчанию — администратор выдаёт его через «Роли и пользователи»
-- (задача «Администрирование (медицина)», ветка Администрирование → Медицина).
--
-- ВНИМАНИЕ по кодировке: база в WIN1251. Скрипт должен выполняться с
-- client_encoding=UTF8 (например, задать в DSN опцию
-- "options=--client_encoding=UTF8"), тогда PostgreSQL сам перекодирует
-- кириллицу. Иначе имя узла запишется битой кодировкой и changeApp задачи
-- «Администрирование (медицина)» будет падать (зависание открытия задачи).
--
-- Таблица SysMenu защищена триггером tg_01_t_sysmenu_bi (прямые INSERT/UPDATE
-- запрещены) — триггер временно отключается в той же транзакции.
-- Скрипт идемпотентен. Требуются права владельца БД.

DO $$
DECLARE
    vParent  int;
    vPos     int;
BEGIN
    -- 1. Узел в дереве прав (рядом с «Связь с ЦИСЗ»)
    IF NOT EXISTS (SELECT 1 FROM SysMenu WHERE Permission = 'med-cisz-monitor') THEN
        SELECT SysMenuParentCode INTO vParent
        FROM SysMenu WHERE Permission = 'med-cisz-setting';
        IF vParent IS NULL THEN
            RAISE EXCEPTION 'узел med-cisz-setting не найден';
        END IF;
        SELECT COALESCE(MAX(Position), -1) + 1 INTO vPos
        FROM SysMenu WHERE SysMenuParentCode = vParent;

        ALTER TABLE SysMenu DISABLE TRIGGER tg_01_t_sysmenu_bi;
        INSERT INTO SysMenu (SysMenuParentCode, Name, Permission, State, Position, FlagTask, TaskNumbers)
        VALUES (vParent, 'Мониторинг ЦИСЗ', 'med-cisz-monitor', 6, vPos, 0, ',666,');
        ALTER TABLE SysMenu ENABLE TRIGGER tg_01_t_sysmenu_bi;
        RAISE NOTICE 'узел SysMenu создан';
    ELSE
        -- приведение ранее созданного узла к безопасному виду
        ALTER TABLE SysMenu DISABLE TRIGGER tg_01_t_sysmenu_bi;
        UPDATE SysMenu SET ModuleFileName = NULL, State = 6, TaskNumbers = ',666,'
        WHERE Permission = 'med-cisz-monitor';
        ALTER TABLE SysMenu ENABLE TRIGGER tg_01_t_sysmenu_bi;
        RAISE NOTICE 'узел SysMenu уже существовал — приведён к виду "чистого" разрешения';
    END IF;

    -- 2. Регистрация права с запретом по умолчанию
    INSERT INTO WebUserPermission (Permission, PermissionParent, PermissionDefault)
    SELECT 'med-cisz-monitor', '#system', 0
    WHERE NOT EXISTS (SELECT 1 FROM WebUserPermission WHERE Permission = 'med-cisz-monitor');

    -- 3. Стереть автокэш назначений со значением «разрешено»
    DELETE FROM WebUserAssignment
    WHERE Permission = 'med-cisz-monitor' AND Value IS NULL;
END $$;

-- Индексы для запросов монитора (безопасны для обновлений вендора)
CREATE INDEX IF NOT EXISTS ix_ciszhistory_bundleid    ON ciszHistory(BundleId);
CREATE INDEX IF NOT EXISTS ix_ciszhistory_actiontime  ON ciszHistory(ActionType, RequestTime);
