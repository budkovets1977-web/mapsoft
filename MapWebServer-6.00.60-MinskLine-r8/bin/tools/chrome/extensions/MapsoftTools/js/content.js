(function () {
    /**
     * Основной скрипт для работы с DOM текущего окна, а так же приема\передачи сообщений между
     * веб приложением и расширением
     */

    const MAP_TYPE_MESSAGE = "MAPSOFT_EXTENSION"; // тип мессаги окну
    const MAP_ACTION_CHOOSE_FILE = "EXTJS_CHOOSE_FILE"; // выбор файла
    const MAP_ACTION_FILE_INFO = "EXTJS_FILE_INFO"; // информация по файлам

    /**
     * Обработка сообщений из веб приложения (из текущего окна)
     * @param {object} message объект сообщения
     * @returns {undefined}
     */
    const receiveMessageWindow = (message) => {
        // только из текущего окна
        if (message.source !== window) {
            return;
        }
        let data = message.data;
        // проверяем типы мессаги
        if (data.type && (data.type === "REQUEST_" + MAP_TYPE_MESSAGE)) {
            switch (data.detail.action) {
                case "EXTJS_OPEN_FILE_LINK": // открытие по линку
                    sendMessage({
                        method: 'CONTENT_OPEN_LOCAL_FILE',
                        detail: {// дополняем путь
                            fileUrl: 'file:///' + data.detail.path
                        }
                    });
                    break;
                case MAP_ACTION_CHOOSE_FILE: // если диалог выбора файлов
                    sendMessage({
                        method: 'CONTENT_CHOOSE_FILE_DIALOG'
                    });
                    break;
                case MAP_ACTION_FILE_INFO: // данные по файлу
                    delete data.detail.action;
                    sendMessage({
                        method: 'CONTENT_GET_FILE_INFO',
                        detail: {
                            dir: data.detail.dir,
                            files: data.detail.files
                        }
                    });
                    break;
            }
        }
    };

    /**
     * Обработка внутрениих сообщений из background.js
     * @param {object} message сообщение
     * @param {object} sender объект сообщения
     * @param {object} sendResponse объект для отправи ответа
     * @returns {undefined}
     */
    const receiveMessageBackground = (message, sender, sendResponse) => {
        switch (message.method) {// проверяем месссагу
            case "BG_SET_CHOOSE_FILE": // выбор файла
                postMessageWindow({ // отправляем в веб приложение
                    action: MAP_ACTION_CHOOSE_FILE,
                    filePath: message.detail
                });
                break;
            case "BG_SET_FILE_INFO": // инфа по файлу
                postMessageWindow({ // отправляем в веб приложение
                    action: MAP_ACTION_FILE_INFO,
                    fileInfo: message.detail.fileInfo
                });
                break;
        }
    };

    // прослушка сообщений от окна (через нее передаются сообщения от Веб приложений Мапсофт)
    window.addEventListener("message", receiveMessageWindow, false);

    // прослушка внутренних сообщений (из бэкграунда) background.js
    chrome.runtime.onMessage.addListener(receiveMessageBackground);

    /**
     * Отправка внутреннего сообщения в бэкграунд (background.js)
     * @param {object} message объект сообщения
     * @returns {undefined}
     */
    const sendMessage = (message) => {
        // рубим возможные ошибки при перезагрузки расширения
        try {
            chrome.runtime.sendMessage(message);
        } catch (e) {}
    };

    /**
     * Отправка сообщения в window барузера для Веб приложений Мапсофт
     * @param {object} detail объект данных сообщения
     * @returns {undefined}
     */
    const postMessageWindow = (detail) => {
        // отправляем мессагу в окно Веб приложения Мапсофт
        window.postMessage({
            // наш тип мессаги
            type: "RESPONSE_" + MAP_TYPE_MESSAGE,
            detail: detail
        }, window);
    };

})();
