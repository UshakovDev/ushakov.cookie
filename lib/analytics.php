<?php
/**
 * P2.2 — Managed Yandex Metrica gating v1
 *
 * Маленький helper-класс без namespace и без зависимостей от внешнего autoloader,
 * чтобы не менять существующую структуру модуля. Подключается через require_once
 * из include.php.
 *
 * Назначение:
 * - читать per-site настройки управляемой Метрики;
 * - читать решение пользователя из site-specific cookie;
 * - решать на сервере, можно ли подключать Метрику;
 * - собирать runtime-config для фронта;
 * - рендерить inline-snippet Метрики (tag.js) только если все условия gating выполнены.
 *
 * Класс намеренно работает только с одним провайдером — yandex_metrika — и только
 * с одним счётчиком на сайт. Это сознательное ограничение v1.
 */

if (!class_exists('UshakovCookieAnalytics')) {

    final class UshakovCookieAnalytics
    {
        public const PROVIDER = 'yandex_metrika';
        public const MODULE_ID = 'ushakov.cookie';
        public const COOKIE_PREFIX = 'ushakov_cookie_';

        /**
         * Возвращает строковый код решения пользователя ('accepted' | 'rejected') либо null.
         */
        public static function getDecision(string $siteId, array $cookies): ?string
        {
            $cookieName = self::COOKIE_PREFIX . $siteId;
            if (!isset($cookies[$cookieName])) {
                return null;
            }

            $value = strtolower(trim((string) $cookies[$cookieName]));
            if ($value === '') {
                return null;
            }

            if ($value === 'accepted' || $value === '1') {
                return 'accepted';
            }
            if ($value === 'rejected') {
                return 'rejected';
            }

            return null;
        }

        /**
         * Включён ли managed mode для сайта.
         */
        public static function isManaged(string $siteId): bool
        {
            return \Bitrix\Main\Config\Option::get(self::MODULE_ID, 'ym_managed_' . $siteId, 'N') === 'Y';
        }

        /**
         * Нормализует ID счётчика. Возвращает положительное целое или 0 (некорректно/не задан).
         */
        public static function normalizeCounterId($raw): int
        {
            if (is_int($raw)) {
                return $raw > 0 ? $raw : 0;
            }
            $raw = trim((string) $raw);
            if ($raw === '' || !preg_match('/^\d+$/', $raw)) {
                return 0;
            }

            $id = (int) $raw;

            return $id > 0 ? $id : 0;
        }

        /**
         * Имя контейнера e-commerce. Только латиница / цифры / подчёркивания, иначе fallback.
         */
        public static function normalizeEcommerceContainer($raw): string
        {
            $raw = trim((string) $raw);
            if ($raw === '') {
                return 'dataLayer';
            }
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $raw)) {
                return 'dataLayer';
            }

            return $raw;
        }

        /**
         * Собирает runtime-config для фронта.
         *
         * Поля:
         * - managed:    включён ли managed mode для сайта
         * - allowed:    разрешена ли аналитика прямо сейчас (managed + accepted + valid counter)
         * - decision:   'accepted' | 'rejected' | null
         * - provider:   'yandex_metrika'
         * - counterId:  int|null
         * - init:       параметры инициализации Метрики
         * - ecommerceContainer: имя контейнера или null
         * - debug:      bool
         */
        public static function buildRuntimeConfig(string $siteId, array $cookies): array
        {
            $managed = self::isManaged($siteId);
            $decision = self::getDecision($siteId, $cookies);

            $counterId = self::normalizeCounterId(
                \Bitrix\Main\Config\Option::get(self::MODULE_ID, 'ym_counter_id_' . $siteId, '')
            );

            $webvisor       = \Bitrix\Main\Config\Option::get(self::MODULE_ID, 'ym_webvisor_' . $siteId, 'Y') === 'Y';
            $clickmap       = \Bitrix\Main\Config\Option::get(self::MODULE_ID, 'ym_clickmap_' . $siteId, 'Y') === 'Y';
            $trackLinks     = \Bitrix\Main\Config\Option::get(self::MODULE_ID, 'ym_track_links_' . $siteId, 'Y') === 'Y';
            $accurate       = \Bitrix\Main\Config\Option::get(self::MODULE_ID, 'ym_accurate_track_bounce_' . $siteId, 'Y') === 'Y';
            $ecommerce      = \Bitrix\Main\Config\Option::get(self::MODULE_ID, 'ym_ecommerce_' . $siteId, 'N') === 'Y';
            $ecommerceName  = self::normalizeEcommerceContainer(
                \Bitrix\Main\Config\Option::get(self::MODULE_ID, 'ym_ecommerce_container_' . $siteId, 'dataLayer')
            );
            $debug          = \Bitrix\Main\Config\Option::get(self::MODULE_ID, 'ym_debug_' . $siteId, 'N') === 'Y';

            $allowed = $managed && $decision === 'accepted' && $counterId > 0;

            return [
                'managed'   => $managed,
                'allowed'   => $allowed,
                'decision'  => $decision,
                'provider'  => self::PROVIDER,
                'counterId' => $counterId > 0 ? $counterId : null,
                'init'      => [
                    'webvisor'            => $webvisor,
                    'clickmap'            => $clickmap,
                    'trackLinks'          => $trackLinks,
                    'accurateTrackBounce' => $accurate,
                    'ecommerce'           => $ecommerce,
                ],
                'ecommerceContainer' => $ecommerce ? $ecommerceName : null,
                'debug'              => $debug,
            ];
        }

        /**
         * Можно ли подключать Метрику на сервере прямо сейчас.
         */
        public static function shouldEmitMetrika(array $runtimeConfig): bool
        {
            return !empty($runtimeConfig['allowed'])
                && !empty($runtimeConfig['counterId'])
                && ($runtimeConfig['provider'] ?? '') === self::PROVIDER;
        }

        /**
         * Возвращает HTML inline-сниппет Yandex.Metrica (tag.js) с защитой от двойной инициализации.
         *
         * Все значения, попадающие в JS-объект init, формируются через json_encode,
         * чтобы исключить инъекцию в HTML. Counter ID — целое число.
         */
        public static function renderMetrikaSnippet(array $runtimeConfig): string
        {
            if (!self::shouldEmitMetrika($runtimeConfig)) {
                return '';
            }

            $counterId = (int) $runtimeConfig['counterId'];
            $init = is_array($runtimeConfig['init'] ?? null) ? $runtimeConfig['init'] : [];

            $params = [
                'clickmap'            => !empty($init['clickmap']),
                'trackLinks'          => !empty($init['trackLinks']),
                'accurateTrackBounce' => !empty($init['accurateTrackBounce']),
                'webvisor'            => !empty($init['webvisor']),
            ];

            if (!empty($init['ecommerce']) && !empty($runtimeConfig['ecommerceContainer'])) {
                $params['ecommerce'] = (string) $runtimeConfig['ecommerceContainer'];
            }

            $paramsJson = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($paramsJson === false) {
                $paramsJson = '{}';
            }

            $noscriptUrl = 'https://mc.yandex.ru/watch/' . $counterId;

            return <<<HTML
<!-- Yandex.Metrika counter (managed by ushakov.cookie) -->
<script>
(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
m[i].l=1*new Date();
for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
(window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");
ym({$counterId}, "init", {$paramsJson});
window.__ushakovCookieMetrikaInitialized = true;
</script>
<noscript><div><img src="{$noscriptUrl}" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
<!-- /Yandex.Metrika counter -->
HTML;
        }
    }
}
