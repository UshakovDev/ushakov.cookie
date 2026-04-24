(function () {
  scheduleInit()

  function scheduleInit () {
    setTimeout(init, 0)
  }

  function init () {
    const currentSiteId = getSiteId()
    const endpoints = getRuntimeEndpoints()

    if (getConsentDecision(currentSiteId) !== null) {
      return
    }

    fetch(endpoints.optionsUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      credentials: 'same-origin',
      body: new URLSearchParams({
        'SITE_ID': currentSiteId,
      }),
    })
    .then(response => response.json())
    .then(options => {
      handleContentLoaded(options, currentSiteId)
    })
    .catch(error => {
      console.error('Ошибка при запросе опций модуля ushakov.cookie', error)
    })
  }

  function getRuntimeConfig () {
    if (typeof window !== 'undefined' && window.ushakovCookieConfig && typeof window.ushakovCookieConfig === 'object') {
      return window.ushakovCookieConfig
    }

    return {}
  }

  function getRuntimeEndpoints () {
    const runtimeConfig = getRuntimeConfig()
    const endpoints = runtimeConfig.endpoints || {}

    return {
      optionsUrl: endpoints.optionsUrl || '/bitrix/tools/ushakov_cookie_options.php',
      saveUrl: endpoints.saveUrl || '/bitrix/tools/ushakov_cookie_save.php',
      consentUrl: endpoints.consentUrl || '/bitrix/tools/ushakov_cookie_consent.php',
    }
  }

  function getSiteId () {
    const runtimeConfig = getRuntimeConfig()

    if (runtimeConfig.siteId) {
      return runtimeConfig.siteId
    }

    if (typeof BX !== 'undefined' && BX && typeof BX.message === 'function') {
      const siteId = BX.message('SITE_ID')
      if (siteId) {
        return siteId
      }
    }

    return 's1'
  }

  function getSessid () {
    const runtimeConfig = getRuntimeConfig()

    if (runtimeConfig.sessid) {
      return runtimeConfig.sessid
    }

    if (typeof BX !== 'undefined' && BX && typeof BX.bitrix_sessid === 'function') {
      return BX.bitrix_sessid()
    }

    return ''
  }

  function getConsentCookieName (siteId) {
    return 'ushakov_cookie_' + siteId
  }

  function getCookieValue (cookieName) {
    const prefix = cookieName + '='
    const row = document.cookie.split('; ').find(item => item.startsWith(prefix))
    if (!row) {
      return ''
    }

    return decodeURIComponent(row.substring(prefix.length))
  }

  function normalizeDecision (value) {
    const normalized = String(value || '').trim().toLowerCase()
    if (normalized === 'accepted' || normalized === 'rejected') {
      return normalized
    }

    // Backward compatibility with previous storage format.
    if (normalized === '1') {
      return 'accepted'
    }

    return null
  }

  function getConsentDecision (siteId) {
    return normalizeDecision(getCookieValue(getConsentCookieName(siteId)))
  }


  // Функция для затемнения цвета (для hover-эффекта кнопки)
  function darkenColor(color, amount) {
    // Проверяем, это RGBA цвет с прозрачностью
    if (color.startsWith('rgba')) {
      // Парсим RGBA цвет: rgba(76, 175, 80, 0.8)
      const rgbaMatch = color.match(/rgba\((\d+),\s*(\d+),\s*(\d+),\s*([\d.]+)\)/);
      if (rgbaMatch) {
        const r = parseInt(rgbaMatch[1]);
        const g = parseInt(rgbaMatch[2]);
        const b = parseInt(rgbaMatch[3]);
        const a = parseFloat(rgbaMatch[4]);
        
        // Затемняем на указанное количество (0.2 = на 20%)
        const darkerR = Math.max(0, Math.floor(r * (1 - amount)));
        const darkerG = Math.max(0, Math.floor(g * (1 - amount)));
        const darkerB = Math.max(0, Math.floor(b * (1 - amount)));
        
        // Возвращаем в RGBA формате с сохранением прозрачности
        return `rgba(${darkerR}, ${darkerG}, ${darkerB}, ${a})`;
      }
    }
    // Проверяем, это RGB цвет без прозрачности
    else if (color.startsWith('rgb')) {
      // Парсим RGB цвет: rgb(76, 175, 80)
      const rgbMatch = color.match(/rgb\((\d+),\s*(\d+),\s*(\d+)\)/);
      if (rgbMatch) {
        const r = parseInt(rgbMatch[1]);
        const g = parseInt(rgbMatch[2]);
        const b = parseInt(rgbMatch[3]);
        
        // Затемняем на указанное количество (0.2 = на 20%)
        const darkerR = Math.max(0, Math.floor(r * (1 - amount)));
        const darkerG = Math.max(0, Math.floor(g * (1 - amount)));
        const darkerB = Math.max(0, Math.floor(b * (1 - amount)));
        
        // Возвращаем в RGB формате
        return `rgb(${darkerR}, ${darkerG}, ${darkerB})`;
      }
    } 
    // Проверяем, это HEX цвет
    else if (color.startsWith('#')) {
      // Парсим HEX цвет: #4CAF50
      const hex = color.replace('#', '');
      
      // Парсим RGB компоненты
      const r = parseInt(hex.substr(0, 2), 16);
      const g = parseInt(hex.substr(2, 2), 16);
      const b = parseInt(hex.substr(4, 2), 16);
      
      // Затемняем на указанное количество (0.2 = на 20%)
      const darkerR = Math.max(0, Math.floor(r * (1 - amount)));
      const darkerG = Math.max(0, Math.floor(g * (1 - amount)));
      const darkerB = Math.max(0, Math.floor(b * (1 - amount)));
      
      // Возвращаем в hex формате
      return '#' + 
             (darkerR < 16 ? '0' : '') + darkerR.toString(16) +
             (darkerG < 16 ? '0' : '') + darkerG.toString(16) +
             (darkerB < 16 ? '0' : '') + darkerB.toString(16);
    }
    // Проверяем, это HSLA цвет с прозрачностью
    else if (color.startsWith('hsla')) {
      // Парсим HSLA цвет: hsla(120, 61%, 50%, 0.8)
      const hslaMatch = color.match(/hsla\((\d+),\s*(\d+)%,\s*(\d+)%,\s*([\d.]+)\)/);
      if (hslaMatch) {
        const h = parseInt(hslaMatch[1]);
        const s = parseInt(hslaMatch[2]);
        const l = parseInt(hslaMatch[3]);
        const a = parseFloat(hslaMatch[4]);
        
        // Затемняем только Lightness (яркость) на указанное количество
        const darkerL = Math.max(0, Math.min(100, Math.floor(l * (1 - amount))));
        
        // Возвращаем в HSLA формате с сохранением прозрачности
        return `hsla(${h}, ${s}%, ${darkerL}%, ${a})`;
      }
    }
    // Проверяем, это HSL цвет без прозрачности
    else if (color.startsWith('hsl')) {
      // Парсим HSL цвет: hsl(120, 61%, 50%)
      const hslMatch = color.match(/hsl\((\d+),\s*(\d+)%,\s*(\d+)%\)/);
      if (hslMatch) {
        const h = parseInt(hslMatch[1]);
        const s = parseInt(hslMatch[2]);
        const l = parseInt(hslMatch[3]);
        
        // Затемняем только Lightness (яркость) на указанное количество
        const darkerL = Math.max(0, Math.min(100, Math.floor(l * (1 - amount))));
        
        // Возвращаем в HSL формате
        return `hsl(${h}, ${s}%, ${darkerL}%)`;
      }
    }
    
    // Если не удалось распарсить, возвращаем исходный цвет
    console.warn('Не удалось распарсить цвет:', color);
    return color;
  }

  function handleContentLoaded (response, fallbackSiteId) {
    const cfg = response && response.data ? response.data : {};
    cfg.siteId = cfg.siteId || fallbackSiteId || getSiteId();
    const delay = parseInt(cfg.delayMs, 10);
    const run = () => {
      if (!isNaN(delay) && delay > 0) {
        setTimeout(() => insertCookieDiv(cfg), delay);
      } else {
        insertCookieDiv(cfg);
      }
    };

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', run);
    } else {
      run();
    }
  }

  function insertCookieDiv(options) {
    let cookieDiv = document.createElement('div')
    cookieDiv.style.zIndex = options.zIndex
    cookieDiv.id = 'ushakov-cookie-wrap'
    cookieDiv.className = 'ushakov-cookie'
    if (options.disableMob === 'Y') {
      cookieDiv.classList.add('ushakov-cookie--d-mob-none')
    }

    // позиция + вертикальный отступ для контейнера
    if (options.position === 'top') {
      cookieDiv.classList.add('ushakov-cookie--pos-top')
      cookieDiv.style.top = options.offsetY || '0'
      cookieDiv.style.bottom = 'auto'          // важно
    } else {
      cookieDiv.classList.add('ushakov-cookie--pos-bottom')
      cookieDiv.style.bottom = options.offsetY || '0'
      cookieDiv.style.top = 'auto'             // важно
    }

    let innerDiv = document.createElement('div')
    innerDiv.classList.add('ushakov-cookie-bg-custom')
    innerDiv.style.setProperty('--ushakov-cookie-bg', options.bgColor)

    // радиус
    if (options.borderRadius) {
      innerDiv.style.setProperty('--ushakov-cookie-radius', options.borderRadius)
    }

    // тень (вкл/выкл)
    if (options.shadow === 'Y') {
      innerDiv.style.setProperty('--ushakov-cookie-shadow', '0 8px 24px rgba(0, 0, 0, 0.85)')
    } else {
      innerDiv.style.setProperty('--ushakov-cookie-shadow', 'none')
    }

    // выравнивание
    const align = options.align || 'center'
    cookieDiv.classList.add('ushakov-cookie--align-' + align)

    // макс. ширина и горизонтальные отступы
    innerDiv.style.setProperty('--ushakov-cookie-max-width', options.maxWidth)
    innerDiv.style.setProperty('--ushakov-cookie-offset-x', options.offsetX)

    const siteId = options.siteId || getSiteId()
    const cookieText = document.createElement('div')
    cookieText.className = 'ushakov-cookie__text'
    cookieText.innerHTML = options.text

    const acceptElement = document.createElement('span')
    acceptElement.classList.add('button')
    acceptElement.textContent = options.textButton && options.textButton.trim() !== '' ? options.textButton.trim() : 'Согласиться'
    acceptElement.onclick = function () {
      acceptConsent(siteId)
    }

    if (options.acceptBtnBgColor) {
      acceptElement.style.backgroundColor = options.acceptBtnBgColor
    }
    if (options.acceptBtnTextColor) {
      acceptElement.style.color = options.acceptBtnTextColor
    }

    acceptElement.addEventListener('mouseenter', function () {
      if (options.acceptBtnBgColor) {
        this.style.backgroundColor = darkenColor(options.acceptBtnBgColor, 0.2)
      }
    })
    acceptElement.addEventListener('mouseleave', function () {
      if (options.acceptBtnBgColor) {
        this.style.backgroundColor = options.acceptBtnBgColor
      }
    })

    const rejectElement = document.createElement('span')
    rejectElement.classList.add('button', 'button--reject')
    rejectElement.textContent = options.rejectButtonText && options.rejectButtonText.trim() !== '' ? options.rejectButtonText.trim() : 'Отказаться'
    rejectElement.onclick = function () {
      rejectConsent(siteId)
    }
    if (options.rejectBtnBgColor) {
      rejectElement.style.backgroundColor = options.rejectBtnBgColor
    }
    if (options.rejectBtnTextColor) {
      rejectElement.style.color = options.rejectBtnTextColor
    }
    rejectElement.addEventListener('mouseenter', function () {
      if (options.rejectBtnBgColor && options.rejectBtnBgColor !== 'transparent') {
        this.style.backgroundColor = darkenColor(options.rejectBtnBgColor, 0.2)
      }
    })
    rejectElement.addEventListener('mouseleave', function () {
      if (options.rejectBtnBgColor) {
        this.style.backgroundColor = options.rejectBtnBgColor
      }
    })

    const actionsDiv = document.createElement('div')
    actionsDiv.className = 'ushakov-cookie__actions'
    actionsDiv.appendChild(acceptElement)
    actionsDiv.appendChild(rejectElement)

    const btnPos = options.acceptBtnPosition || 'right'
    innerDiv.style.display = ''
    innerDiv.style.flexDirection = ''
    innerDiv.style.alignItems = ''

    if (btnPos === 'bottom') {
      innerDiv.style.display = 'flex'
      innerDiv.style.flexDirection = 'column'
      innerDiv.style.gap = '10px'
      innerDiv.style.alignItems =
        align === 'left' ? 'flex-start' :
        align === 'right' ? 'flex-end' : 'center'
      actionsDiv.style.alignSelf = 'center'
      actionsDiv.style.flexDirection = 'row'
      innerDiv.appendChild(cookieText)
      innerDiv.appendChild(actionsDiv)
    } else if (btnPos === 'left') {
      innerDiv.style.display = 'flex'
      innerDiv.style.alignItems = 'center'
      innerDiv.style.gap = '10px'
      actionsDiv.style.flexDirection = 'column'
      innerDiv.appendChild(actionsDiv)
      innerDiv.appendChild(cookieText)
    } else {
      innerDiv.style.display = 'flex'
      innerDiv.style.alignItems = 'center'
      innerDiv.style.gap = '10px'
      actionsDiv.style.flexDirection = 'column'
      innerDiv.appendChild(cookieText)
      innerDiv.appendChild(actionsDiv)
    }

    // Финальная сборка
    cookieDiv.appendChild(innerDiv);
    document.body.appendChild(cookieDiv);
  }

  function removeBanner () {
    const element = document.getElementById('ushakov-cookie-wrap')
    if (element) {
      element.remove()
    }
  }

  function acceptConsent (siteId) {
    const consentSiteId = siteId || getSiteId();

    saveConsent(consentSiteId, 'accepted')
    .then(saveResult => {
      return saveConsentRegistry(consentSiteId, saveResult && saveResult.guestClientId ? saveResult.guestClientId : '')
      .catch(error => {
        console.warn('Failed to save consent to Bitrix registry:', error)
      })
    })
    .then(() => {
      removeBanner()
    })
    .catch(error => {
      console.error('Error saving consent:', error)
    })
  }

  function rejectConsent (siteId) {
    const consentSiteId = siteId || getSiteId()

    saveConsent(consentSiteId, 'rejected')
      .then(() => {
        removeBanner()
      })
      .catch(error => {
        console.error('Error saving rejection:', error)
      })
  }

  function saveConsent (siteId, decision) {
    const endpoints = getRuntimeEndpoints()

    return fetch(endpoints.saveUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      },
      credentials: 'same-origin',
      body: new URLSearchParams({
        'sessid': getSessid(),
        'SITE_ID': siteId,
        'decision': decision,
      })
    })
    .then(response => {
      return response.json().then(data => ({ ok: response.ok, data }))
    })
    .then(({ ok, data }) => {
      if (!ok || !data.success) {
        throw new Error(data && data.error ? data.error : 'Failed to save consent cookie')
      }

      console.log('Cookie saved successfully')
      return data
    })
  }

  function saveConsentRegistry (siteId, guestClientId) {
    const endpoints = getRuntimeEndpoints()
    const body = new URLSearchParams({
      'sessid': getSessid(),
      'SITE_ID': siteId,
      'url': window.location.href
    })

    if (guestClientId) {
      body.set('GUEST_CLIENT_ID', guestClientId)
    }

    return fetch(endpoints.consentUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        credentials: 'same-origin',
        body: body
      })
    .then(response => response.json())
    .then(consentData => {
      if (consentData.success) {
        console.log('Consent saved to Bitrix registry:', consentData.message)
        if (consentData.existing) {
          console.log('Existing consent found, no new record created')
        }
        // Выводим отладочную информацию
        if (consentData.debug) {
          console.log('Debug info:', consentData.debug)
        }
      } else {
        console.warn('Failed to save consent:', consentData.error)
        // Выводим отладочную информацию при ошибке
        if (consentData.debug) {
          console.log('Debug info:', consentData.debug)
        }
      }
    })
  }
})();
