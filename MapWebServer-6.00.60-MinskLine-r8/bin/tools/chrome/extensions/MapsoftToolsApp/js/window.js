(function () {
    /**
     * Обработка загрузки окна
     * @param {string} ext_id id расширения Mapsoft Tools
     * @returns {undefined}
     */
    const load = () => {
        // открываем стандартный диалог выбора файла
        chrome.fileSystem.chooseEntry({
            // параметры диалога если нужно
        }, (entry) => {
            if (chrome.runtime.lastError) { // если возникли ошибки
                window.close(); // закрываем созданное окно приложения
            } else {// достаем реальный путь до файла
                chrome.fileSystem.getDisplayPath(entry, (path, test) => {
                    // создаем простой запрос в расширение Mapsoft Tools,
                    // т.к. у него есть доступ к контенту страницы и передаем путь до файла
                    // ID из Chrome Web Store
                    chrome.runtime.sendMessage(window.ext_id, {
                        method: "APP_SET_CHOOSE_FILE",
                        detail: {
                            filePath: path
                        }
                    }, (response) => {// чтобы сработало на этой стороне обязательно нужно отправить ответ
                        window.close(); // закрываем созданное окно приложения
                    });
                });
            }
        });
    };

    // прослушка загрузки окна
    document.addEventListener('DOMContentLoaded', load, false);
})();
