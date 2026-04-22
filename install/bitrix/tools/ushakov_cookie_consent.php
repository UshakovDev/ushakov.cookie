<?php
/**
 * AJAX: запись согласия в реестр через API userconsent (совместимо со старым и новым ядром).
 */
define('STOP_STATISTICS', true);
define('NO_AGENT_CHECK', true);
define('DisableEventsCheck', true);

require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Context;
use Bitrix\Main\SystemException;

header('Content-Type: application/json; charset=UTF-8');

$response = function(array $data, int $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

// ---- ШИМ ДЛЯ КЛАССОВ (поддержка старого и нового пространств имён) ----
$ConsentClass       = class_exists('\Bitrix\Main\UserConsent\Consent')
    ? '\Bitrix\Main\UserConsent\Consent'
    : (class_exists('\Bitrix\UserConsent\Consent') ? '\Bitrix\UserConsent\Consent' : null);

$AgreementTableClass = class_exists('\Bitrix\Main\UserConsent\Internals\AgreementTable')
    ? '\Bitrix\Main\UserConsent\Internals\AgreementTable'
    : (class_exists('\Bitrix\UserConsent\Internals\AgreementTable') ? '\Bitrix\UserConsent\Internals\AgreementTable' : null);

$ConsentTableClass   = class_exists('\Bitrix\Main\UserConsent\Internals\ConsentTable')
    ? '\Bitrix\Main\UserConsent\Internals\ConsentTable'
    : (class_exists('\Bitrix\UserConsent\Internals\ConsentTable') ? '\Bitrix\UserConsent\Internals\ConsentTable' : null);

try {
    if (!$ConsentClass || !$AgreementTableClass || !$ConsentTableClass) {
        $response([
            'success' => false,
            'error'   => 'UserConsent API is not available in this core (no classes found)',
            'code'    => 'API_NOT_AVAILABLE'
        ], 500);
    }

    $context = Context::getCurrent();
    $request = $context->getRequest();

    if (!$request->isPost()) {
        $response(['success' => false, 'error' => 'Only POST is allowed'], 405);
    }
    if (function_exists('check_bitrix_sessid') && !check_bitrix_sessid()) {
        $response(['success' => false, 'error' => 'Bad sessid'], 403);
    }

    $siteId         = (string)($request->getPost('SITE_ID') ?: (defined('SITE_ID') ? SITE_ID : 's1'));
    $siteId         = preg_replace('/[^a-zA-Z0-9_]/', '', trim($siteId));
    if ($siteId === '') {
        $siteId = 's1';
    }
    $moduleId       = 'ushakov.cookie';
    $saveToRegistry = Option::get($moduleId, 'save_to_registry_'.$siteId, 'N');
    $agreementId    = (int)Option::get($moduleId, 'agreement_id_'.$siteId, 0);
    $oncePerSession = Option::get($moduleId, 'log_once_per_session_'.$siteId, 'N') === 'Y';

    if ($saveToRegistry !== 'Y' || $agreementId <= 0) {
        $response([
            'success' => true,
            'message' => 'Registry saving disabled (option off or no agreement ID)',
            'debug'   => compact('saveToRegistry','agreementId','siteId')
        ]);
    }

    // Проверяем что соглашение существует и активно
    /** @var \Bitrix\Main\ORM\Query\Result $agr */
    $agr = $AgreementTableClass::getList([
        'filter' => ['=ID' => $agreementId, '=ACTIVE' => 'Y'],
        'select' => ['ID','NAME','TYPE']
    ])->fetch();
    if (!$agr) {
        $response([
            'success' => false,
            'error'   => 'Agreement not found or inactive',
            'code'    => 'AGREEMENT_NOT_FOUND',
            'debug'   => ['agreementId' => $agreementId]
        ], 404);
    }

    global $USER, $APPLICATION;
    $userId = (is_object($USER) && $USER->IsAuthorized()) ? (int)$USER->GetID() : null;
    $ip     = $request->getRemoteAddress();
    $ua     = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $url    = (string)($request->getPost('url') ?: ($APPLICATION ? $APPLICATION->GetCurPageParam() : ($request->getRequestUri() ?: '')));
    $source = 'cookie_banner';

    $text    = (string)$request->getPost('text');     // если показывать СВОЙ текст
    $options = $request->getPost('options');          // чекбоксы, если есть

    if ($oncePerSession) {
        $flagKey = 'cookie_accept_logged_'.$agreementId;
        if (!isset($_SESSION)) { session_start(); }
        if (!empty($_SESSION[$flagKey])) {
            $response(['success'=>true,'skipped'=>true,'message'=>'Already logged in this session']);
        }
    }

    // Антидубликаты: тот же AGREEMENT_ID и ORIGIN_ID и (USER_ID или IP)
    $existing = $ConsentTableClass::getList([
        'filter' => [
            '=AGREEMENT_ID' => $agreementId,
            '=ORIGIN_ID'    => $source,
            [
                'LOGIC' => 'OR',
                ['=USER_ID' => $userId],
                ['=IP'      => $ip],
            ],
        ],
        'select' => ['ID','DATE_INSERT','USER_ID','IP','URL'],
        'order'  => ['ID' => 'DESC'],
        'limit'  => 1,
    ])->fetch();

    if ($existing) {
        if ($oncePerSession) { $_SESSION[$flagKey] = true; }
        $response([
            'success'   => true,
            'existing'  => true,
            'message'   => 'Consent already exists',
            'consentId' => (int)$existing['ID'],
        ]);
    }

    // Контекст для addByContext
    $ctx = [
        'USER_ID'           => $userId,
        'IP'                => $ip,
        'URL'               => $url,
        'USER_AGENT'        => $ua,
        'ORIGINATOR_ID'     => $source,
        'ORIGIN_ID'         => $source,
        'ORIGINAL_TEXT'     => $text ?: null,
        'ORIGINAL_TEXT_HASH'=> $text ? hash('sha256', $text) : null,
        'OPTIONS_JSON'      => is_array($options) ? json_encode($options, JSON_UNESCAPED_UNICODE) : (is_string($options) ? $options : null),
    ];

    // Вызов совместим: статический метод вызываем по строке класса (не работает)
    // $result = $ConsentClass::addByContext($agreementId, $ctx);

    // if (!$result || (method_exists($result,'isSuccess') && !$result->isSuccess())) {
    //     $errs = method_exists($result,'getErrorMessages') ? implode('; ', $result->getErrorMessages()) : 'Unknown error';
    //     throw new SystemException($errs);
    // }

    // $consentId = method_exists($result,'getId') ? (int)$result->getId() : null;
    
    // --- стало: универсальная обработка ---
    $result = $ConsentClass::addByContext($agreementId, $ctx);

    $ok = false;
    $consentId = null;
    $errors = null;

    if (is_object($result)) {
        // Новые ядра: объект результата
        if (method_exists($result, 'isSuccess')) {
            $ok = (bool)$result->isSuccess();
        } else {
            // На всякий случай: если объект, но без isSuccess — считаем, что ок
            $ok = true;
        }
        if (method_exists($result, 'getId')) {
            $consentId = (int)$result->getId();
        }
        if (!$ok && method_exists($result, 'getErrorMessages')) {
            $errors = implode('; ', (array)$result->getErrorMessages());
        }
    } elseif (is_int($result)) {
        // Старые ядра: сразу ID
        $consentId = $result;
        $ok = ($consentId > 0);
    } elseif (is_bool($result)) {
        // Некоторые билды могут вернуть просто true/false
        $ok = $result;
    } else {
        // Неподдерживаемый тип
        $errors = 'Unexpected return type from addByContext: ' . gettype($result);
    }

    if (!$ok) {
        throw new SystemException($errors ?: 'Unknown error on addByContext');
    }

    // дописываем источник (на некоторых ядрах addByContext это «проглатывает»)
    $originUpdated = false;
    try {
        $ConsentTableClass::update($consentId, [
            'ORIGINATOR_ID' => $source,      // 'cookie_banner'
            'ORIGIN_ID'     => $source,
        ]);
        $originUpdated = true;
    } catch (Exception $e) {
        // Логируем ошибку, но не прерываем выполнение
        error_log('Failed to update ORIGIN fields: ' . $e->getMessage());
    }

    if ($oncePerSession) { $_SESSION[$flagKey] = true; }

    $response([
        'success'   => true,
        'message'   => 'Consent saved successfully (API)',
        'consentId' => $consentId,
        'debug'     => [
            'agreement' => ['id'=>(int)$agr['ID'],'name'=>(string)$agr['NAME'],'type'=>(string)$agr['TYPE']],
            'ctx'       => ['USER_ID'=>$userId,'IP'=>$ip,'URL'=>$url,'ORIGIN_ID'=>$source],
            'siteId'    => $siteId,
            'retType'   => is_object($result) ? 'object' : (is_int($result) ? 'int' : (is_bool($result) ? 'bool' : gettype($result))),
            'originUpdated' => $originUpdated,
            'source'    => $source,
        ]
    ]);

} catch (\Throwable $e) {
    $response([
        'success' => false,
        'error'   => $e->getMessage(),
    ], 500);
}
