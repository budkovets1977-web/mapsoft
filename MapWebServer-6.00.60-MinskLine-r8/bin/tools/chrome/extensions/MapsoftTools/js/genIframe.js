(function () {
	/**
     * Скрипт фоновой страницы, предназначен для работы с iframe для создания и удаления. Находится на
	 * одной странице с background.js
     */

	const IFRAME_PARAM_DIR = "dir";
	const IFRAME_PARAM_IFRAME = "iframe";
	const GET_TIMEOUT = 10;

	// счетчик ID фреймов
	let frameCnt = 0;

	/**
	 * Обработка сообщений window
	 * @param {object} message объект сообщения
	 * @returns {undefined}
	 */
	const processMessageWindowIframe = (message) => {
		// только из текущего окна
		if (message.source !== window)
			return;
		let data = message.data;
		if (data.type && (data.type === "REQUEST_EXTENSION")) {// если наш
			switch (data.detail.action) {
				case "BG_GET_FILE_INFO": //
					message.stopPropagation();
					createIframe(data.detail);
					break;
				case "BG_DESTROY_FRAME": //
					message.stopPropagation();
					destoyIframe(data.detail.iframeId);
					break;
			}
		}
	};

	// Прослушка сообщений от окна для доступа к текущему DOM из другого (background.js)
	window.addEventListener("message", processMessageWindowIframe, false);

	/**
	 * Очистка и удаление фрейма
	 * @param {string} frameId frame Id
	 * @returns {undefined}
	 */
	const destoyIframe = (iframeId) => {
		let iframe = document.getElementById(iframeId);
		// удаляем фрейм
		iframe && iframe.remove();
	};

	/**
	 * Проверка загрузки iframe
	 * @param {string} frameId frameId
	 * @returns {undefined}
	 */
	const checkLoadIframe = (iframeId) => {
		// если возможно, то уничтожаем фрейм
		destoyIframe && destoyIframe(iframeId);
	};

	/**
	 * Обработка сообщений window браузера
	 * @param {object} detail объект сообщения
	 * @returns {undefined}
	 */
	const createIframe = (detail) => {
		let str_params = "";
		// добавляем переданную директорию
		str_params += `?${IFRAME_PARAM_DIR}=${detail.dir}`;
		// собираем строку из переданных параметров файлов
		for (let i = 0; i < detail.files.length; i++) {
			str_params += `&file${i}=${detail.files[i]}`;
		}
		// добавляем iframe ID
		let iframeId = `${IFRAME_PARAM_IFRAME}${++frameCnt}`;
		str_params += `&${IFRAME_PARAM_IFRAME}=${iframeId}`;
		// склеиваем и нормализируем параметры для URL запроса
		str_params = detail.dir + encodeURI(str_params);
		// создаем новый iframe (загружается на фоновой странице), в котором по переданному URL загружается
		// через браузер страница с директорией, так же передаем параметры URL, которые явлются загружаемой
		// директорией, iframe id и проверяемыми файлами, т.к. по другому на внутренний DOM с источником,
		// расопложенный на другом хосте, передать параметры не получится. Затем, после загрузки страницы,
		// можно отыскав необходимую строку с файлом и украв информацию из innerText, получить дату измнения файла
		document.body.insertAdjacentHTML('afterend', `<iframe id="${iframeId}" src="${str_params}"></iframe>`);
		// вешаем таймер чтобы убрать мусор если вдруг фрейм не успел загрузиться или нет доступа к директории
		//setTimeout(checkLoadIframe.bind(this, iframeId), GET_TIMEOUT * 1000);
		setTimeout(checkLoadIframe, GET_TIMEOUT * 1000, iframeId);
	};
})();
