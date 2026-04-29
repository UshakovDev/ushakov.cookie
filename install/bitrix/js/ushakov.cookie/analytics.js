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
 * только пишет console.warn для диагностики.
 */
(function () {
  var SCRIPT_VERSION = 'p2.2.v1';

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
      log('init() skipped: analytics not allowed');
      return false;
    }
    return isInitialized();
  }

  function reachGoal(goalName, params) {
    if (!isAllowed() || !isYmAvailable()) {
      log('reachGoal() skipped', { goal: goalName, allowed: isAllowed(), ym: isYmAvailable() });
      return false;
    }
    var goal = String(goalName || '').trim();
    if (goal === '') {
      return false;
    }
    try {
      window.ym(getCounterId(), 'reachGoal', goal, params || undefined);
      return true;
    } catch (e) {
      log('reachGoal() failed', e);
      return false;
    }
  }

  function hit(url, options) {
    if (!isAllowed() || !isYmAvailable()) {
      log('hit() skipped', { url: url, allowed: isAllowed(), ym: isYmAvailable() });
      return false;
    }
    var hitUrl = (url && String(url)) || (typeof window !== 'undefined' && window.location ? window.location.href : '');
    if (!hitUrl) {
      return false;
    }
    try {
      window.ym(getCounterId(), 'hit', hitUrl, options || undefined);
      return true;
    } catch (e) {
      log('hit() failed', e);
      return false;
    }
  }

  function getEcommerceContainerName() {
    var cfg = getAnalyticsConfig();
    return cfg.ecommerceContainer ? String(cfg.ecommerceContainer) : null;
  }

  function pushEcommerce(payload) {
    if (!isAllowed()) {
      log('pushEcommerce() skipped: analytics not allowed');
      return false;
    }
    var name = getEcommerceContainerName();
    if (!name) {
      log('pushEcommerce() skipped: e-commerce container is not configured');
      return false;
    }
    try {
      if (!Array.isArray(window[name])) {
        window[name] = window[name] || [];
      }
      window[name].push(payload);
      return true;
    } catch (e) {
      log('pushEcommerce() failed', e);
      return false;
    }
  }

  function isDebug() {
    var cfg = getAnalyticsConfig();
    return !!cfg.debug;
  }

  function log() {
    if (!isDebug() || typeof console === 'undefined' || typeof console.log !== 'function') {
      return;
    }
    var args = Array.prototype.slice.call(arguments);
    args.unshift('[ushakov.cookie:analytics]');
    console.log.apply(console, args);
  }

  function warnAboutExternalMetrika() {
    if (typeof window === 'undefined' || typeof console === 'undefined' || typeof console.warn !== 'function') {
      return;
    }
    var managed = isManaged();
    var ymPresent = isYmAvailable();
    var initializedByModule = window.__ushakovCookieMetrikaInitialized === true;

    if (!ymPresent) {
      return;
    }

    if (!managed) {
      console.warn('[ushakov.cookie] Detected window.ym but managed Yandex Metrica mode is disabled in this module. ' +
        'The module will NOT control or remove the external counter. ' +
        'For full consent gating, switch the counter under module management.');
      return;
    }

    if (!initializedByModule) {
      console.warn('[ushakov.cookie] Detected window.ym not initialized by this module. ' +
        'External Yandex.Metrica integration is not gated by ushakov.cookie. ' +
        'See README — section "Managed Yandex Metrica".');
    }
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
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', warnAboutExternalMetrika);
    } else {
      warnAboutExternalMetrika();
    }
  }
})();
