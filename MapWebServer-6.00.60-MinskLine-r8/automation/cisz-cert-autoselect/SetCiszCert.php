<?php
/**
 * SetCiszCert — записывает friendly name активного сертификата ЭЦП
 * в секцию [sigma] cert= файла NTClientSoftware.ini (Windows-1251).
 *
 * Убирает окно выбора сертификата при подписи через NT Client Software.
 *
 * Имя сертификата читается вспомогательным скриптом GetCertName.ps1 (рядом).
 *
 * Запуск (на КЛИЕНТСКОЙ машине):
 *   php.exe SetCiszCert.php              — автоопределение имени
 *   php.exe SetCiszCert.php "Фамилия И.О."  — задать имя вручную
 *   php.exe SetCiszCert.php --show       — только показать
 *   php.exe SetCiszCert.php --restart    — также перезапустить NTClientSoftware.exe
 */

$iniPath = 'C:\NTClientSoftware\config\NTClientSoftware.ini';
$exePath = 'C:\NTClientSoftware\NTClientSoftware.exe';
$psPath  = __DIR__ . '\GetCertName.ps1';

$manualName = null;
$showOnly   = false;
$doRestart  = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--show')         { $showOnly = true; }
    elseif ($arg === '--restart')  { $doRestart = true; }
    else                           { $manualName = $arg; }
}

// 1. Определить friendly name
if ($manualName !== null) {
    $friendlyName = $manualName;
} else {
    $friendlyName = getActiveCertFriendlyName($psPath);
}

if ($friendlyName === null || trim($friendlyName) === '') {
    fwrite(STDERR, "Не удалось определить friendly name сертификата.\n");
    exit(2);
}
echo "Friendly name: [$friendlyName]\n";

if ($showOnly) { exit(0); }

// 2. Прочитать ini как Windows-1251
if (!is_file($iniPath)) {
    fwrite(STDERR, "INI не найден: $iniPath\n");
    exit(4);
}
$text = mb_convert_encoding(file_get_contents($iniPath), 'UTF-8', 'Windows-1251');

// 3. Заменить cert= только в секции [sigma]
$lines = preg_split('/\r\n|\r|\n/', $text);
$inSigma = false;
$found = false;
foreach ($lines as $i => $line) {
    if (preg_match('/^\s*\[/', $line)) {
        $inSigma = (bool)preg_match('/^\s*\[sigma\]\s*$/i', $line);
        continue;
    }
    if ($inSigma && preg_match('/^\s*cert=/', $line)) {
        $lines[$i] = 'cert=' . $friendlyName;
        $found = true;
    }
}
if (!$found) {
    fwrite(STDERR, "Строка cert= не найдена в [sigma].\n");
    exit(5);
}

file_put_contents($iniPath, mb_convert_encoding(implode("\r\n", $lines), 'Windows-1251', 'UTF-8'));
echo "Записано cert=$friendlyName\n";

if ($doRestart) {
    shell_exec('taskkill /IM NTClientSoftware.exe /F 2>&1');
    sleep(2);
    shell_exec('start "" "' . $exePath . '"');
    echo "NTClientSoftware перезапущен.\n";
}
echo "Готово.\n";


function getActiveCertFriendlyName($psPath) {
    if (!is_file($psPath)) return null;
    // PowerShell выводит friendly name; читаем как UTF-8 (скрипт сам ставит OutputEncoding)
    $out = shell_exec('powershell -NoProfile -ExecutionPolicy Bypass -File "' . $psPath . '" 2>&1');
    if ($out === null) return null;
    $out = trim($out);
    $lines = array_values(array_filter(explode("\n", $out), function ($l) { return trim($l) !== ''; }));
    return end($lines) ?: null;
}
