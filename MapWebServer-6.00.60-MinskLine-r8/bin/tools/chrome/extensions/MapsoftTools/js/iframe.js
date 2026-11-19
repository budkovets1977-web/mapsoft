(function () {
    /**
     * Скрипт фоновой страницы, предназначен для работы с iframe для добывания информации по файлу,
     * внедряется и выполняется после создания страницы внутри iframe.
     */

    const IFRAME_PARAM_DIR = "dir";
    const IFRAME_PARAM_IFRAME = "iframe";

    /**
     * Парсит параметры URL запроса в iframe
     * @returns {array} массив параметров
     */
    const paramParse = () => {
    	let url = window.location.search.substring(1), // достаем параметры
    	    qArray = url.split('&'),
    		pArray = {};
    	// парсим парамтеры и создаем массив параметр->значение
    	for (let key in qArray) {
    		let tArr = qArray[key].split('=');
    		tArr.length > 0 && (pArray[tArr[0]] = decodeURI(tArr[1]));
    	}

    	return pArray;
    };

    /**
     * Проверка объекта на пустоту
     * @param {object} obj проверяемый объект
     * @returns {bool} пустой\не пустой
     */
    const isEmptyObject = (obj) => {
    	let has = {}.hasOwnProperty;
    	for (let key in obj) {
    		if (has.call(obj, key)) {
    			return false;
    		}
    	}
    	return true;
    };

    /**
     * Поиск информации по файлу
     * @returns {array} масив файлов с их информацией, первым элементом всегда будет директория
     */
    const getInfo = () => {
    	let td = document.getElementsByTagName('td'),
    	    pArray = paramParse(),
    		iArray = {};
        // небольшой трюк по краже информации из открытой директории в окне iframe браузера
    	// пробегаемся по всем тэгам td и ищем в них аттрибут с указанием файла, если нашли, по строке в
    	// последнем элементе крадем инфомацию о файле
    	for (let i = 0; i < td.length; i++) {
    		for (let key in pArray)  {
    			if (pArray[key] !== IFRAME_PARAM_DIR && pArray[key] !== IFRAME_PARAM_IFRAME
                    && td[i].getAttribute('data-value') === pArray[key]) {
    				iArray[pArray[key]] = td[i].parentElement.children[2].innerText;
    			}
    		}
    	}
    	// передаем iframe ID для идентификации этого фрейма
    	iArray[IFRAME_PARAM_IFRAME] = pArray[IFRAME_PARAM_IFRAME];
    	if (!isEmptyObject(iArray)) {
    		// добавляем директорию чтобы понять откуда файлы
    		iArray[IFRAME_PARAM_DIR] = pArray[IFRAME_PARAM_DIR];
    	}

    	return iArray;
    };

    // шлем найденную информацию в background.js
    chrome.runtime.sendMessage({
    	method: "IFRAME_SET_FILE_INFO",
    	detail: {
    		fileInfo: getInfo()
    	}
    });
})();
