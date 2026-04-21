(function () {
  const currentSiteId = getSiteId()

  if (hasConsentCookie(getConsentCookieName(currentSiteId))) {
    return
  }

  fetch('/bitrix/tools/ushakov_cookie_options.php', {
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
    handleContentLoaded(options)
  })
  .catch(error => {
    console.error('Ошибка при запросе опций модуля ushakov.cookie', error)
  })

  function getSiteId () {
    if (typeof BX !== 'undefined' && BX && typeof BX.message === 'function') {
      const siteId = BX.message('SITE_ID')
      if (siteId) {
        return siteId
      }
    }

    return 's1'
  }

  function getSessid () {
    if (typeof BX !== 'undefined' && BX && typeof BX.bitrix_sessid === 'function') {
      return BX.bitrix_sessid()
    }

    return ''
  }

  function getConsentCookieName (siteId) {
    return 'ushakov_cookie_' + siteId
  }

  function hasConsentCookie (cookieName) {
    return document.cookie.split('; ').some(row => row.startsWith(cookieName + '='))
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

  function handleContentLoaded (response) {
    const cfg = response && response.data ? response.data : {};
    cfg.siteId = cfg.siteId || currentSiteId;
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
    // Создаем элементы
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
    innerDiv.classList.add('ushakov-cookie-bg-custom');
    innerDiv.style.setProperty('--ushakov-cookie-bg', options.bgColor);

    innerDiv.style.setProperty('--ushakov-cookie-text-color', options.textColor);

    innerDiv.style.setProperty('--ushakov-cookie-font-size', options.fontSize);

    // радиус
    if (options.borderRadius) {
      innerDiv.style.setProperty('--ushakov-cookie-radius', options.borderRadius);
    }

    // тень (вкл/выкл)
    if (options.shadow === 'Y') {
      innerDiv.style.setProperty('--ushakov-cookie-shadow', '0 8px 24px rgba(0, 0, 0, 0.85)');
    } else {
      innerDiv.style.setProperty('--ushakov-cookie-shadow', 'none');
    }

    // выравнивание
    const align = options.align || 'center';
    cookieDiv.classList.add('ushakov-cookie--align-' + align);

    // макс. ширина и горизонтальные отступы
    innerDiv.style.setProperty('--ushakov-cookie-max-width', options.maxWidth)
    innerDiv.style.setProperty('--ushakov-cookie-offset-x', options.offsetX)

    let cookieText = document.createElement('div')
    cookieText.className = 'ushakov-cookie__text'

    cookieText.innerHTML = options.text



    // Вставляем либо кнопку "Согласен", либо крестик (иконка)
    // + применяем цвет/позицию из настроек
    let closeElement;
    const hasTextButton = options.textButton && options.textButton.trim() !== '';

    if (hasTextButton) {
      // КНОПКА СОГЛАСИЯ
      closeElement = document.createElement('span');
      closeElement.classList.add('button');
      closeElement.textContent = options.textButton.trim();
      closeElement.onclick = function () {
        acceptConsent(options.siteId || currentSiteId);
      };

      // Цвета кнопки из настроек (если заданы)
      if (options.acceptBtnBgColor)  closeElement.style.backgroundColor = options.acceptBtnBgColor;
      if (options.acceptBtnTextColor) closeElement.style.color = options.acceptBtnTextColor;
      
      // Добавляем hover-эффект затемнения
      closeElement.addEventListener('mouseenter', function() {
        if (options.acceptBtnBgColor) {
          // Затемняем фон кнопки на 20%
          const darkerColor = darkenColor(options.acceptBtnBgColor, 0.2);
          this.style.backgroundColor = darkerColor;
        }
      });
      
      closeElement.addEventListener('mouseleave', function() {
        if (options.acceptBtnBgColor) {
          // Возвращаем исходный цвет фона
          this.style.backgroundColor = options.acceptBtnBgColor;
        }
      });

      // Позиция кнопки относительно текста
      // left  — кнопка слева от текста (перед)
      // right — справа (по умолчанию, после)
      // bottom — снизу отдельной строкой
      const btnPos = (options.acceptBtnPosition || 'right');

      // Сбрасываем возможные стили из CSS темы
      innerDiv.style.display = '';
      innerDiv.style.flexDirection = '';
      innerDiv.style.alignItems = '';
      cookieText.style.margin = '';
      closeElement.style.margin = '0';

      // Универсальные стили для "рядом"
      function asRow() {
        innerDiv.style.display = 'flex';
        innerDiv.style.alignItems = 'center';
        // gap защищает от "слипания" без ручных margin
        innerDiv.style.gap = '10px';
      }

      if (btnPos === 'left') {
        asRow();
        // на всякий случай уберём левый margin, если он задан в .button
        closeElement.style.marginLeft = '0';
        closeElement.style.marginRight = '0';
        // помещаем кнопку перед текстом
        innerDiv.appendChild(closeElement);
        innerDiv.appendChild(cookieText);
      } else if (btnPos === 'bottom') {
        // столбец: текст сверху, кнопка ниже
        innerDiv.style.display = 'flex';
        innerDiv.style.flexDirection = 'column';
        
        // Текст выравниваем по align плашки
        const align = (options.align || 'center');
        innerDiv.style.alignItems =
          align === 'left'  ? 'flex-start' :
          align === 'right' ? 'flex-end'  : 'center';

        innerDiv.appendChild(cookieText);
        
        // Кнопка ВСЕГДА по центру, независимо от выравнивания плашки
        closeElement.style.display = 'inline-block';
        closeElement.style.marginTop = '10px';
        closeElement.style.alignSelf = 'center'; // принудительно по центру
        innerDiv.appendChild(closeElement);
      } else { // 'right' по умолчанию
        asRow();
        innerDiv.appendChild(cookieText);
        innerDiv.appendChild(closeElement);
      }
    } else {
      // ---- КРЕСТИК ----
      const rawPos  = (options.closeBtnPosition || 'right-top');
      const crossPos = String(rawPos).trim();

      // создаём элемент крестика (span × — чтобы красить цветом; иначе <img>)
      let closeElement;
      // Если цвет не задан, используем красный по умолчанию
      const closeBtnColor = options.closeBtnColor || 'rgb(255, 7, 7)';
      
      // Всегда создаем span-крестик с цветом (по умолчанию красный)
      closeElement = document.createElement('span');
      closeElement.textContent = '×';
      closeElement.setAttribute('aria-label', 'Закрыть');
      closeElement.setAttribute('role', 'button');
      closeElement.tabIndex = 0;
      closeElement.style.fontSize = '22px';
      closeElement.style.lineHeight = '1';
      closeElement.style.cursor = 'pointer';
      closeElement.style.userSelect = 'none';
      closeElement.style.color = closeBtnColor;
      closeElement.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          closeBanner();
        }
      });
      
      // Добавляем hover-эффект затемнения для span-крестика
      closeElement.addEventListener('mouseenter', function() {
        const darkerColor = darkenColor(closeBtnColor, 0.2);
        this.style.color = darkerColor;
      });
      
      closeElement.addEventListener('mouseleave', function() {
        this.style.color = closeBtnColor;
      });
      closeElement.onclick = closeBanner;

      if (crossPos === 'left-top' || crossPos === 'right-top') {
        // ВЕРХНИЕ позиции: FLEX-ряд (крестик и текст в одной строке)
        innerDiv.style.display = 'flex';
        innerDiv.style.flexWrap = 'nowrap';
        innerDiv.style.alignItems = 'flex-start'; // выравнивание по верху
        innerDiv.style.gap = '10px';
        innerDiv.style.flexDirection = 'row';

        if (crossPos === 'left-top') {
          innerDiv.appendChild(closeElement);
          innerDiv.appendChild(cookieText);
        } else { // right-top
          innerDiv.appendChild(cookieText);
          innerDiv.appendChild(closeElement);
        }

      } else {
        // СЕРЕДИНА слева/справа: FLEX-ряд (крестик и текст в одной строке)
        innerDiv.style.display = 'flex';
        innerDiv.style.flexWrap = 'nowrap';   // НЕ переносим на новую строку
        innerDiv.style.alignItems = 'center';
        innerDiv.style.gap = '10px';
        innerDiv.style.flexDirection = 'row'; // явно указываем направление

        if (crossPos === 'left-middle') {
          innerDiv.appendChild(closeElement);
          innerDiv.appendChild(cookieText);
        } else { // right-middle
          innerDiv.appendChild(cookieText);
          innerDiv.appendChild(closeElement);
        }
      }
    }

    // Финальная сборка
    cookieDiv.appendChild(innerDiv);
    document.body.appendChild(cookieDiv);

    // // Вставляем или img, или span в зависимости от textButton
    // let closeElement
    // if (options.textButton && options.textButton.trim() !== '') {
    //   closeElement = document.createElement('span')
    //   closeElement.classList.add('button')
    //   closeElement.textContent = options.textButton
    // } else {
    //   closeElement = document.createElement('img')
    //   closeElement.src = '/bitrix/images/ushakov.cookie/close.svg'
    // }
    // closeElement.onclick = sendCookieRequestAndRemoveElement

    // // Собираем и вставляем в документ
    // innerDiv.appendChild(cookieText)
    // innerDiv.appendChild(closeElement)
    // cookieDiv.appendChild(innerDiv)
    // document.body.appendChild(cookieDiv)
  }

  function closeBanner () {
    removeBanner()
  }

  function removeBanner () {
    const element = document.getElementById('ushakov-cookie-wrap')
    if (element) {
      element.remove()
    }
  }

  function acceptConsent (siteId) {
    const consentSiteId = siteId || currentSiteId;

    saveConsent(consentSiteId)
    .then(() => {
      return saveConsentRegistry(consentSiteId)
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

  function saveConsent (siteId) {
    return fetch('/bitrix/tools/ushakov_cookie_save.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      },
      credentials: 'same-origin',
      body: new URLSearchParams({
        'sessid': getSessid(),
        'SITE_ID': siteId,
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
    })
  }

  function saveConsentRegistry (siteId) {
    return fetch('/bitrix/tools/ushakov_cookie_consent.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        credentials: 'same-origin',
        body: new URLSearchParams({
          'sessid': getSessid(),
          'SITE_ID': siteId,
          'url': window.location.href
        })
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
