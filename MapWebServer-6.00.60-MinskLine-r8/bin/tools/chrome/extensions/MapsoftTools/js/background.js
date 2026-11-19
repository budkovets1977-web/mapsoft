(function () {
    /**
     * Фоновый скрипт, отвечает за обработку и распределение сообщений как внутри расширения, так и от
     * других расширений\приложений
     */

     // ID приложения, формируется при упаковке приложения в .crx файл, на основе .pem файла. Сохраняется при
     // переупаковки обновленной версии если добавить его вторым парамтером в упаковщик гугла. Поэтому файл .pem
     // лучше не терять
     const APP_ID_DEF = "penjmijfhbbabigdiajhecggdhijaphh";
     const OPTIONS_KEY_APPID = "map_app_id";
     const OPTIONS_KEY_APPID_DEF = "map_app_id_def";

     // приходится каждый раз при запуске устанавливать значение, т.к. "ув." гугол блокирует обработку события
     // onInstalled для расширений не из его магазина
     chrome.storage.local.set({
        map_app_id_def: APP_ID_DEF // устанавливаем APP_ID по умолчанию
     });

     // прослушка установки расширения (походу будет скоро бесполезной)
     chrome.runtime.onInstalled.addListener(() => {
         // чистим локальное хранилище приложения
         localStorage.clear();
         // устанавливаем APP_ID по умолчанию
         chrome.storage.local.set({
 			map_app_id: APP_ID_DEF
 		 });
         // выполняем скрипт для открытых вкладок при установке\перезагрузке расширения
         chrome.tabs.query({
             url: '*://*/*',
         }, (tabs) => {
             tabs.forEach((tab) => {
                 chrome.tabs.executeScript(tab.id, {
                     file: 'content.js',
                     allFrames: true,
                 }, (result) => {
                     if (typeof result === 'undefined') {
                         const message = chrome.i18n.getMessage('page_not_loaded');
                     }
                 });
             });
         });
     });

     /**
     * Обработка внутреннего сообщения (content.js, genIframe.js, iframe.js)
     * @param {object} message сообщение
     * @param {object} sender объект сообщения
     * @param {object} sendResponse объект для отправи ответа
     * @returns {undefined}
     */
     const receiveMessageContent = (message, sender, sendResponse) => {
         //console.log(message);
         switch (message.method) {// проверяем мессагу
             case "CONTENT_OPEN_LOCAL_FILE": // открытие файла по линку
                 openLocalFile(message.detail.fileUrl, sender.tab);
                 break;
             case "CONTENT_CHOOSE_FILE_DIALOG": // диалог выбора файла
                 chooseFileDialog();
                 break;
             case "CONTENT_GET_FILE_INFO": // инфа по файлу
                 getFileInfo(message.detail);
                 break;
             case "IFRAME_SET_FILE_INFO": // установка инфы
                 setContentFileInfo(message.detail);
                 break;
         }
     };

     /**
     * Обработка полученного сообщения из других расширений\приложений (Mapsoft Tools App)
     * @param {object} message сообщение
     * @param {object} sender объект сообщения
     * @param {object} sendResponse объект для отправи ответа
     * @returns {undefined}
     */
     const receiveMessageExternal = (message, sender, sendResponse) => {
         switch (message.method) {// проверяем месссагу
             case "APP_SET_CHOOSE_FILE": // если вернули выбор файлов
                 // шлем обратку, нужно чтобы сработал код в калбэке (закрылось окно приложения Mapsoft Tools App)
                 sendResponse('OK');
                 // обработка выбора файла
                 processChooseFile(message.detail);
                 break;
         }
     };

     // прослушка внутренних сообщений (content.js, genIframe.js, iframe.js)
     chrome.runtime.onMessage.addListener(receiveMessageContent);

     // прослушка событий внешних сообщений, обрабатываются простые запросы из
     // других расширений\приложений (Mapsoft Tools App), отправляются только этому расширению по ID
     chrome.runtime.onMessageExternal.addListener(receiveMessageExternal);

     /**
     * Отправка сообщения на в контент на текущую вкладку (content.js)
     * @param {object} params объект параметров передачи
     * @returns {undefined}
     */
     const sendMessageContent = (params) => {
         // ищем вкладку
         getCurrentTab((tab) => {
             // отправляем мессагу в контент
             tab && chrome.tabs.sendMessage(tab.id, params);
         });
     };

     /**
     * Отправка сообщения на контент бэкграунд страницы (background.js)
     * @param {object} detail объект сообщения
     * @returns {undefined}
     */
     const sendBackgroundMessage = (detail) => {
         chrome.runtime.getBackgroundPage((wnd) => {
             wnd.postMessage({
                 type: "REQUEST_EXTENSION",
                 detail: detail
             }, wnd);
         });
     };

     /**
     * Достает AppID из локального хранилища
     * @param {function} callback функция дальнейшей обработки
     * @returns {undefined}
     */
     const getAppID = (callback) => {
         chrome.storage.local.get([OPTIONS_KEY_APPID, OPTIONS_KEY_APPID_DEF], function(result) {
             // если нету установленного, берем по умолчанию
             callback(result[OPTIONS_KEY_APPID] ? result[OPTIONS_KEY_APPID] : result[OPTIONS_KEY_APPID_DEF]);
         });
     };

     /**
     * Отправка сообщения другому расщирению\приложению по ID (Maposft Tools App)
     * @param {object} message объект сообщения {method: <method_name>, ... }
     * @returns {undefined}
     */
     const sendMessageExternal = (message) => {
         getAppID((appID) => {
             // простой запрос в приложение Mapsoft Tools App ID из Chrome Web Store
             chrome.runtime.sendMessage(appID, message);
         });
     };

     /**
     * Отправка сообщения в контент
     * @param {object} detail объект сообщения
     * @returns {undefined}
     */
     const setContentFileInfo = (detail) => {
         // удаляем уже ненужный фрейм
         sendBackgroundMessage({
             action: "BG_DESTROY_FRAME",
             iframeId: detail.fileInfo.iframe
         });
         // удаляем лишнее
         delete detail.fileInfo.iframe;
         // шлем мессагу на контент
         sendMessageContent({
             method: "BG_SET_FILE_INFO",
             detail: {
                 fileInfo: detail.fileInfo
             }
         });
     };

     /**
     * Шлет запрос информаци о файлах
     * @param {object} detail объект сообщения
     * @returns {undefined}
     */
     const getFileInfo = (detail) => {
         //console.log(detail);
         // оправляем сообщение фоновой странице для добычи информации
         sendBackgroundMessage({
             action: "BG_GET_FILE_INFO",
             dir: detail.dir,
             files: detail.files
         });
     };

     /**
     * Обработка полученного файла
     * @param {object} detail объект параметров передачи
     * @returns {undefined}
     */
     const processChooseFile = (detail) => {
         let path = detail.filePath;
         // достаем инфу по файлу, по идее должно отбработать позже чем отправка пути, поэтому для
         // сокращения времени отправляем первей
         getFileInfo({
             dir: path.substring(0, path.lastIndexOf("\\") + 1),
             files: [path.replace(/^.*[\\\/]/, '')]
         });
         // пересылаем на контент
         sendMessageContent({
             method: "BG_SET_CHOOSE_FILE",
             detail: {
                 path: path
             }
         });
     };

     /**
     * Поиск текущей вкладки
     * @param {function} callback функция для дальнейшей обработки
     * @returns {undefined}
     */
     const getCurrentTab = (callback) => {
         // ищем активную страницу
         chrome.tabs.query({
             active: true,
             currentWindow: true
         }, (tabs) => {
             callback(tabs[0]);
         });
     };

     /**
     * Открывает файл по ссылке
     * @param {string} fileUrl путь к файлу
     * @param {object} baseTab текущая вкладка
     * @returns {undefined}
     */
     const openLocalFile = (fileUrl, baseTab) => {
         // создаем новую вкладку и открываем в ней файл (если можем, если нет - идет на загрузку)
         chrome.tabs.create({
             url: fileUrl,
             index: baseTab.index + 1,
         });
     };

     /**
     * Открытие диалога выбора файла
     * @returns {undefined}
     */
     const chooseFileDialog = () => {
         // запускаем приложение (на случай если вдруг не запущено) Mapsoft Tools App,
         // через которое будем открывать диалог выбора файла,
         // т.к. только у него есть доступ к chrome.fileSystem API
         launchMapApp((result) => {
             // создаем простой запрос в приложение Mapsoft Tools App ID из Chrome Web Store
             result && sendMessageExternal({
                 method: "EXT_GET_CHOOSE_FILE"
             });
         });
     };

     /**
     * Запуск приложения chrome Mapsoft Tool App
     * @param {function} callback функция для дальнейшей обработки
     * @returns {undefined}
     */
     const launchMapApp = (callback) => {
         getAppID((appID) => {
             // пробуем запустить
             chrome.management.launchApp(appID, () => {
                 if (chrome.runtime.lastError) {// не смогли открыть - беда, ругаем
                     //console.error(chrome.runtime.lastError);
                     callback(false);
                 } else { // смогли - молодцы, хвалим себя за это
                     //console.info("App launched!!! Юхуу");
                     callback(true);
                 };
             });
         });
     };
})();
