(function () {
	const MAP_TYPE_MESSAGE = "MAPSOFT_EXTENSION"; // тип мессаги окну

	/**
	 * Обработка полученного сообщения из других расширений\приложений
	 * @param {object} message сообщение
	 * @param {object} sender объект сообщения
	 * @param {object} sendResponse объект для отправи ответа
	 * @returns {undefined}
	 */
	const receiveMessageExternal = (message, sender, sendResponse) => {
		switch (message.method) {
			case "EXT_GET_CHOOSE_FILE":
				createWindow(sender.id);
				break;
		}
	};

	// прослушка событий внешних сообщений, обрабатываются простые запросы из
	// других расширений\приложений, отправляются только этому расширению по ID
	chrome.runtime.onMessageExternal.addListener(receiveMessageExternal);

	/**
	 * Загрузка окна
	 * @param {string} ext_id переданный в бинде ID Maposft Tools
	 * @param {object} wnd объект созданного окна
	 * @returns {undefined}
	 */
	const onLoadWindow = (ext_id, wnd) => {
		// отправляем на окно ID
		wnd.contentWindow.ext_id = ext_id;
	};

	/**
	 * Создает окно приложения
	 * @param {string} ext_id ID расширения Mapsoft Tools
	 * @returns {undefined}
	 */
	const createWindow = (ext_id) => {
		// делаем окно невидимым
		let options = {
			frame: 'chrome',
			width: 0,
			height: 0,
			hidden: true
		};

		// создаем новое окно и передаем через бинд дополнительный параметр
		chrome.app.window.create('window.html', options, onLoadWindow.bind(this, ext_id));
	};
})();
