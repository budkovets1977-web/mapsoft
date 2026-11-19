(function () {
	/**
     * Скрипт страницы настроек options.html
     */

	/**
	 * Обработка загрузки окна
	 * @returns {undefined}
	 */
	const load = () => {
		getEl("btnApply").addEventListener('click', saveOptions);
		restoreOptions();
	};

	/**
	 * Получение элемента по ID
	 * @param {string} id элемента на форме
	 * @returns {object} объект элемента
	 */
	const getEl = (id) => {
		return document.getElementById(id);
	};

	/**
	 * Сохранение настроек расширения
	 * @returns {undefined}
	 */
	const saveOptions = () => {
		chrome.storage.local.set({
			map_app_id: getEl("appID").value
		}, () => {
			let status = getEl("status");
			status.textContent = "Изменения сохранены...";
			setTimeout(function () {
				status.textContent = "";
			}, 750);
		});
	};

	/**
	 * Загрузка настроек расширения
	 * @returns {undefined}
	 */
	const restoreOptions = () => {
		chrome.storage.local.get({
		  map_app_id: "",
		  map_app_id_def: ""
		}, function(items) {			
			getEl("appID").value = items.map_app_id ? items.map_app_id : items.map_app_id_def;
		});
	};

	// загрузка документа
	document.addEventListener('DOMContentLoaded', load);
})();
