/**
 * ushakov.cookie — JS helper для безопасной работы с Яндекс.Метрикой (P2.2 v1).
 *
 * Доступен как window.ushakovCookieAnalytics:
 *   - isAllowed()
 *   - isInitialized()
 *   - init()
 *   - reachGoal(goalName, params)
 *   - hit(url, options)
 *   - pushEcommerce(payload)
 *   - getEcommerceContainerName()
 *
 * Helper намеренно ничего не делает, если:
 *   - аналитика запрещена (rejected / нет решения / managed выключен / нет counterId);
 *   - Метрика ещё не инициализирована.
 *
 * Также helper не пытается автоматически вырезать Метрику, подключённую извне —
 * только пишет событие в диагностику модуля.
 */
(function () {
  var SCRIPT_VERSION = 'p2.2.v1';
  var DEBUG_EVENT_LIMIT = 50;
  var debugEventsSent = 0;

  function getRuntimeConfig() {
    if (typeof window !== 'undefined' && window.ushakovCookieConfig && typeof window.ushakovCookieConfig === 'object') {
      return window.ushakovCookieConfig;
    }
    return {};
  }

  function getAnalyticsConfig() {
    var rc = getRuntimeConfig();
    return rc && rc.analytics && typeof rc.analytics === 'object' ? rc.analytics : {};
  }

  function getRuntimeEndpoints() {
    var rc = getRuntimeConfig();
    var endpoints = rc && rc.endpoints && typeof rc.endpoints === 'object' ? rc.endpoints : {};
    return {
      debugUrl: endpoints.debugUrl || '/bitrix/tools/ushakov_cookie_debug.php'
    };
  }

  function getSessid() {
    var rc = getRuntimeConfig();
    if (rc && rc.sessid) {
      return String(rc.sessid);
    }
    if (typeof BX !== 'undefined' && BX && typeof BX.bitrix_sessid === 'function') {
      return String(BX.bitrix_sessid());
    }
    return '';
  }

  function getSiteId() {
    var rc = getRuntimeConfig();
    if (rc && rc.siteId) {
      return String(rc.siteId);
    }
    if (typeof BX !== 'undefined' && BX && typeof BX.message === 'function') {
      var siteId = BX.message('SITE_ID');
      if (siteId) {
        return String(siteId);
      }
    }
    return '';
  }

  function getCookieValue(name) {
    if (typeof document === 'undefined' || typeof document.cookie !== 'string') {
      return '';
    }
    var prefix = name + '=';
    var row = document.cookie.split('; ').find(function (item) {
      return item.indexOf(prefix) === 0;
    });
    if (!row) {
      return '';
    }
    try {
      return decodeURIComponent(row.substring(prefix.length));
    } catch (e) {
      return '';
    }
  }

  function normalizeDecision(value) {
    var normalized = String(value || '').trim().toLowerCase();
    if (normalized === 'accepted' || normalized === '1') {
      return 'accepted';
    }
    if (normalized === 'rejected') {
      return 'rejected';
    }
    return null;
  }

  function getDecision() {
    var siteId = getSiteId();
    if (!siteId) {
      return null;
    }
    return normalizeDecision(getCookieValue('ushakov_cookie_' + siteId));
  }

  function getCounterId() {
    var cfg = getAnalyticsConfig();
    var raw = cfg.counterId;
    if (raw === null || raw === undefined || raw === '') {
      return 0;
    }
    var num = parseInt(raw, 10);
    return isNaN(num) || num <= 0 ? 0 : num;
  }

  function isManaged() {
    var cfg = getAnalyticsConfig();
    return !!cfg.managed;
  }

  function isAllowed() {
    if (!isManaged()) {
      return false;
    }
    if (getCounterId() === 0) {
      return false;
    }
    return getDecision() === 'accepted';
  }

  function isYmAvailable() {
    return typeof window !== 'undefined' && typeof window.ym === 'function';
  }

  function isInitialized() {
    if (!isAllowed()) {
      return false;
    }
    return isYmAvailable() && window.__ushakovCookieMetrikaInitialized === true;
  }

  /**
   * v1: Метрика подключается через server-side rendering в include.php после accepted.
   * Метод init() оставлен как точка совместимости и возвращает текущее состояние;
   * собственного запуска счётчика на лету в этой версии нет.
   */
  function init() {
    if (!isAllowed()) {
      debug('SKIP', 'init_skipped', 'init() skipped: analytics not allowed');
      return false;
    }
    return isInitialized();
  }

  function reachGoal(goalName, params) {
    var allowed = isAllowed();
    var ymAvailable = isYmAvailable();
    if (!allowed || !ymAvailable) {
      debug('SKIP', 'reach_goal_skipped', 'reachGoal() skipped', {
        goal: String(goalName || '').trim(),
        allowed: allowed,
        ymAvailable: ymAvailable
      });
      return false;
    }
    var goal = String(goalName || '').trim();
    if (goal === '') {
      debug('SKIP', 'reach_goal_empty', 'reachGoal() skipped: empty goal name');
      return false;
    }
    try {
      window.ym(getCounterId(), 'reachGoal', goal, params || undefined);
      debug('INFO', 'reach_goal_sent', 'reachGoal() sent', { goal: goal });
      return true;
    } catch (e) {
      debug('ERROR', 'reach_goal_failed', 'reachGoal() failed', {
        goal: goal,
        error: getErrorMessage(e)
      });
      return false;
    }
  }

  function hit(url, options) {
    var allowed = isAllowed();
    var ymAvailable = isYmAvailable();
    if (!allowed || !ymAvailable) {
      debug('SKIP', 'hit_skipped', 'hit() skipped', {
        hasUrl: !!url,
        allowed: allowed,
        ymAvailable: ymAvailable
      });
      return false;
    }
    var hitUrl = (url && String(url)) || (typeof window !== 'undefined' && window.location ? window.location.href : '');
    if (!hitUrl) {
      debug('SKIP', 'hit_empty_url', 'hit() skipped: empty URL');
      return false;
    }
    try {
      window.ym(getCounterId(), 'hit', hitUrl, options || undefined);
      debug('INFO', 'hit_sent', 'hit() sent', { hasUrl: true });
      return true;
    } catch (e) {
      debug('ERROR', 'hit_failed', 'hit() failed', {
        error: getErrorMessage(e)
      });
      return false;
    }
  }

  function getEcommerceContainerName() {
    var cfg = getAnalyticsConfig();
    return cfg.ecommerceContainer ? String(cfg.ecommerceContainer) : null;
  }

  function pushEcommerce(payload) {
    if (!isAllowed()) {
      debug('SKIP', 'ecommerce_skipped_not_allowed', 'pushEcommerce() skipped: analytics not allowed');
      return false;
    }
    var name = getEcommerceContainerName();
    if (!name) {
      debug('SKIP', 'ecommerce_skipped_no_container', 'pushEcommerce() skipped: e-commerce container is not configured');
      return false;
    }
    try {
      if (!Array.isArray(window[name])) {
        window[name] = window[name] || [];
      }
      window[name].push(payload);
      debug('INFO', 'ecommerce_pushed', 'pushEcommerce() payload pushed', { ecommerceContainer: name });
      return true;
    } catch (e) {
      debug('ERROR', 'ecommerce_failed', 'pushEcommerce() failed', {
        error: getErrorMessage(e)
      });
      return false;
    }
  }

  function isDebug() {
    var cfg = getAnalyticsConfig();
    return !!cfg.debug;
  }

  function debug(level, event, message, details) {
    if (!isDebug()) {
      return;
    }

    sendDebugEvent(level, event, message, getDiagnosticDetails(details || {}));
  }

  function sendDebugEvent(level, event, message, details) {
    if (debugEventsSent >= DEBUG_EVENT_LIMIT) {
      return;
    }
    if (typeof window === 'undefined' || typeof window.fetch !== 'function' || typeof URLSearchParams === 'undefined') {
      return;
    }

    var endpoints = getRuntimeEndpoints();
    if (!endpoints.debugUrl) {
      return;
    }

    debugEventsSent += 1;

    var body = new URLSearchParams({
      sessid: getSessid(),
      SITE_ID: getSiteId() || 's1',
      level: String(level || 'INFO'),
      event: String(event || 'event'),
      message: String(message || ''),
      details: JSON.stringify(details || {})
    });

    window.fetch(endpoints.debugUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      credentials: 'same-origin',
      keepalive: true,
      body: body
    }).catch(function () {});
  }

  function getDiagnosticDetails(extra) {
    var cfg = getAnalyticsConfig();
    var details = {
      provider: cfg.provider || '',
      managed: isManaged(),
      allowed: isAllowed(),
      decision: getDecision(),
      counterId: getCounterId() > 0 ? getCounterId() : null,
      ymAvailable: isYmAvailable(),
      initializedByModule: typeof window !== 'undefined' && window.__ushakovCookieMetrikaInitialized === true,
      ecommerceConfigured: !!cfg.ecommerceContainer
    };

    Object.keys(extra || {}).forEach(function (key) {
      details[key] = sanitizeDebugValue(key, extra[key]);
    });

    return details;
  }

  function sanitizeDebugValue(key, value) {
    if (/(url|href|email|phone|ip|userAgent|payload|params)/i.test(String(key))) {
      return '[redacted]';
    }
    if (value === null || value === undefined || typeof value === 'boolean' || typeof value === 'number') {
      return value;
    }
    if (typeof value === 'string') {
      return value.substring(0, 160);
    }
    if (value instanceof Error) {
      return getErrorMessage(value);
    }
    if (Array.isArray(value)) {
      return '[array]';
    }
    if (typeof value === 'object') {
      return '[object]';
    }
    return String(value).substring(0, 160);
  }

  function getErrorMessage(error) {
    if (!error) {
      return '';
    }
    if (error.message) {
      return String(error.message).substring(0, 160);
    }
    return String(error).substring(0, 160);
  }

  function warnAboutExternalMetrika() {
    if (typeof window === 'undefined') {
      return;
    }
    var managed = isManaged();
    var ymPresent = isYmAvailable();
    var initializedByModule = window.__ushakovCookieMetrikaInitialized === true;

    if (!ymPresent) {
      return;
    }

    if (!managed) {
      warnExternal('external_ym_managed_disabled', 'Detected window.ym but managed Yandex Metrica mode is disabled in this module. ' +
        'The module will NOT control or remove the external counter. For full consent gating, switch the counter under module management.');
      return;
    }

    if (!initializedByModule) {
      warnExternal('external_ym_not_managed', 'Detected window.ym not initialized by this module. ' +
        'External Yandex.Metrica integration is not gated by ushakov.cookie.');
    }
  }

  function warnExternal(event, message) {
    debug('WARN', event, message);
  }

  function exposeApi() {
    window.ushakovCookieAnalytics = {
      version: SCRIPT_VERSION,
      isAllowed: isAllowed,
      isInitialized: isInitialized,
      init: init,
      reachGoal: reachGoal,
      hit: hit,
      pushEcommerce: pushEcommerce,
      getEcommerceContainerName: getEcommerceContainerName,
    };
  }

  exposeApi();

  if (typeof document !== 'undefined') {
    var afterDomReady = function () {
      debug('INFO', 'helper_ready', 'analytics helper loaded');
      warnAboutExternalMetrika();
    };

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', afterDomReady);
    } else {
      afterDomReady();
    }
  }
})();
