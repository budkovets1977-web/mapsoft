-- ============================================================================
-- Добавление пункта меню «Отправить в ЦИСЗ за день» (виджет med-cisz-sendbydate)
-- БД: meddb (схема map), PostgreSQL  —  КОДИРОВКА БД: WIN1251
-- Версия: 6.00.60 · Дата: 12.11.2026
--
-- ВАЖНО:
--  1) Имя пункта (Name) ДОЛЖНО записываться в кодировке WIN1251 (серверная
--     кодировка БД = WIN1251). Если записать в UTF-8, readNavigatorItems упадёт
--     с ошибкой 22P05 («character with byte sequence ... in encoding WIN1251
--     has no equivalent in UTF8»), и навигатор «Администрирования» не откроется.
--  2) ExData — ОБЯЗАТЕЛЬНЫЙ столбец (триггер tg_01_t_sysmenu_bi падает при
--     пустом значении).
--  3) Виджет добавлен append-блоком в конец app/med/cisz.js (как вендор делает для
--     соседних виджетов, напр. med-cisz-setting). Алиас widget.med-cisz-sendbydate
--     регистрируется при загрузке cisz.js (входит в скрипты задач медицины).
--
-- ЗАПУСКАТЬ ТОЛЬКО ПО ЯВНОЙ КОМАНДЕ (изменяет таблицу map.SysMenu).
-- Перед запуском — бэкап:
--   CREATE TABLE map.SysMenu_bak_20261111 AS SELECT * FROM map.SysMenu;
-- ============================================================================

-- Обязательно выставить клиентскую кодировку WIN1251 для корректной записи Name.
SET client_encoding TO 'WIN1251';

BEGIN;

-- Новый пункт меню в разделе «Обмены» (родитель 641106),
-- рядом с существующим «Настройка связи с ЦИСЗ» (med-cisz-setting, position 25).
--
-- Столбцы SysMenu (схема map) сверены с боевой БД 12.11.2026:
--   SysMenuCode (seq seq_sysmenu), SysMenuParentCode, Name, ModuleFileName,
--   State (0=обычный пункт, 5=корень, 7=раздел-папка, 2=скрыт),
--   Param (JSON), Permission, Position, TaskNumbers, FlagTask, ExData.
INSERT INTO map.SysMenu (
    SysMenuCode,
    SysMenuParentCode,
    Name,
    ModuleFileName,
    State,
    Param,
    Permission,
    Position,
    ExData,
    FlagTask
) VALUES (
    nextval('map.seq_sysmenu'),        -- автоинкремент PK
    641106,                            -- родитель: «Обмены»
    'Отправить в ЦИСЗ за день',        -- имя в WIN1251 (клиентская кодировка выше)
    'med-cisz-sendbydate',             -- алиас виджета (Med.cisz.view.SendByDate)
    0,                                 -- обычный пункт меню
    '{"iconCls": "x-fa fa-paper-plane-o"}',
    'med-cisz-setting',                -- переиспользуем право доступа ЦИСЗ-настройки
    26,                                -- позиция (после «Настройка связи с ЦИСЗ»=25)
    md5(random()::text || clock_timestamp()::text),  -- уникальный ExData (hex 32)
    0
);

COMMIT;

-- ============================================================================
-- ОТКАТ:
--   DELETE FROM map.SysMenu WHERE ModuleFileName = 'med-cisz-sendbydate';
-- ============================================================================

-- Примечания:
-- * КОДИРОВКА (критично): имя пункта пишется в WIN1251. При запуске из psql
--   использовать `SET client_encoding TO 'WIN1251';` (см. выше) или подать
--   скрипт в кодировке WIN1251. Иначе имя запишется байтами UTF-8 и сломает
--   readNavigatorItems (ошибка 22P05).
-- * Permission = 'med-cisz-setting' — переиспользовано право доступа настройки
--   ЦИСЗ, чтобы пункт видели те же пользователи, кому разрешён ЦИСЗ.
-- * Виджет: добавлен в app/med/cisz.js (append) + модель Med.cisz.model.SendByDate
--   в app/med/cisz/cisz-model.js. Боевой сервер имеет плоскую структуру
--   (cisz.js лежит в app/med/ без подпапки).
-- * TaskNumbers — пусто: пункт виден во всех под-задачах, как у med-cisz-setting.
-- * Проверка видимости: навигатор читает FN_webGetNVItems(:task) -> FN_sysMenuRead,
--   фильтр по fn_WebUserAccess(Permission, :userId) и State <> 2.
