<!--
<meta property="og:image" content="docs/assets/logo.png">
<meta property="og:image:width" content="1280">
<meta property="og:image:height" content="640">
<meta property="og:title" content="ushakov.cookie — модуль плашки согласия на cookie для 1С-Битрикс">
<meta property="og:description" content="Гибкая и легкая плашка согласия на использование cookie для 1С-Битрикс с полной кастомизацией и интеграцией с реестром согласий">
-->

# ushakov.cookie — модуль плашки согласия на cookie для 1С-Битрикс

[![version](https://img.shields.io/badge/version-0.0.1-blue)](../../releases)
[![license](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
![php](https://img.shields.io/badge/PHP-7.4%2B%20|%208.0%2B-blue)
![bitrix](https://img.shields.io/badge/1C--Bitrix-main%20module%20required-lightgrey)
[![issues](https://img.shields.io/github/issues/UshakovDev/ushakov.cookie)](../../issues)

Гибкая и легкая плашка согласия на использование cookie для **1С-Битрикс**:

- позиция и выравнивание,
- две явные кнопки выбора: согласиться / отказаться,
- задержка показа,
- хранение согласия (сессионно / N дней),
- интеграция с **Реестром согласий Битрикс** (опционально).

## 📸 Скриншоты

### Основной интерфейс
![Плашка согласия на десктопе](docs/assets/screenshot-desktop.png)

### Мобильная версия
![Плашка согласия на мобильном](docs/assets/screenshot-mobile.png)

### Панель настроек
![Настройки модуля в админке](docs/assets/screenshot-admin.png)

---

## ✨ Возможности

- Позиция плашки: **верх / низ**, выравнивание: **слева / центр / справа**
- Две явные кнопки выбора: **согласиться** и **отказаться** (без крестика)
- Задержка показа, ширина, отступы, скругление, тень, `z-index`
- Хранение решения: **`accepted` / `rejected`** в site-specific cookie `ushakov_cookie_<siteId>` (`SameSite=Lax`, `Secure` при HTTPS)
- Legacy-совместимость: старое значение cookie `1` трактуется как `accepted`
- (Опционально) запись факта согласия в **Реестр согласий** 1С-Битрикс
- Мультисайтовость: отдельные настройки на каждый сайт
- Текст плашки через визуальный редактор, автоматическая ссылка на `/cookies-agreement.php`
- **Hover-эффекты**: автоматическое затемнение цвета кнопки согласия при наведении
- **Валидация CSS**: проверка корректности значений px, rem, em, % для всех размерных параметров
- **Антидублирование**: предотвращение создания дублирующих записей согласий в реестре
- **Guest client id**: технический site-specific идентификатор гостя для более устойчивой дедупликации в реестре согласий
- **Обратная совместимость**: поддержка как новых, так и старых ядер Bitrix (UserConsent API)

---

## ⚙️ Совместимость

- PHP **7.4+ / 8.0+**
- 1С-Битрикс (модуль `main`)
- HTTPS поддерживается; при HTTPS выставляется флаг `secure`

---

## 📦 Установка

### Вариант A — из релиза
1. Скачайте ZIP из раздела **[Releases](../../releases)**.
2. Распакуйте в `/local/modules/` (или `/bitrix/modules/`) так, чтобы получилась папка `/local/modules/ushakov.cookie`.
3. В админке Битрикс: **Marketplace → Установленные решения → Установить модуль**.
4. В мастере установки выберите сайты — **модуль автоматически создаст страницу** `/cookies-agreement.php` в корне каждого выбранного сайта.
   > При удалении модуля эти файлы также будут **автоматически удалены**.

### Вариант B — из GitHub (git)
```bash
cd /path/to/bitrix/local/modules
git clone https://github.com/UshakovDev/ushakov.cookie.git ushakov.cookie
```

Затем активируйте модуль в админке; страница `/cookies-agreement.php` будет создана автоматически (см. выше).

---

## 🔧 Настройка

![Панель настроек модуля](docs/assets/screenshot-admin.png)

Админка → Настройки → Настройки продукта → Настройки модулей → ushakov.cookie

| Опция | Описание |
|-------|----------|
| Активность | Включить/выключить плашку |
| Позиция/выравнивание | Верх/низ; слева/центр/справа |
| Кнопки выбора | Общая позиция блока + тексты и цвета для «Согласиться» / «Отказаться» (без крестика) |
| Задержка, z-index, отступы | Управление отображением |
| Хранение согласия | Сессия или N дней |
| Текст плашки | HTML/визуальный редактор + ссылка на `/cookies-agreement.php` |
| Реестр согласий | Включить запись и указать ID соглашения |

Пример текста плашки:

```html
Мы используем файлы cookie для работы сайта и сбора статистики.
Продолжая пользоваться сайтом, вы соглашаетесь с нашей
<a href="/cookies-agreement.php" target="_blank" rel="noopener">Политикой использования cookie</a>.
```

---

## 🔧 Технические особенности

### Поддержка старых ядер
Модуль автоматически определяет версию API UserConsent и использует соответствующие классы:
- **Новые ядра**: `\Bitrix\Main\UserConsent\Consent`
- **Старые ядра**: `\Bitrix\UserConsent\Consent`

### Hover-эффекты
Интерактивные элементы баннера имеют hover-эффекты:
- **Кнопка согласия**: затемнение фона на 20% при наведении
- **Поддержка цветов**: RGB, RGBA, HEX, HSL, HSLA с сохранением прозрачности

### Валидация CSS-значений
Модуль проверяет корректность всех CSS-параметров:
- **Размеры**: px, rem, em, % (автоматическая нормализация)
- **Цвета**: проверка форматов и fallback на значения по умолчанию
- **Z-index**: валидация числовых значений

### Антидублирование согласий
Система предотвращает создание дублирующих записей:
- **Проверка по**: ID соглашения + источник + `originId`
- **Техническая заметка по `originId`**:
В реестре согласий Bitrix поле `ORIGIN_ID` имеет ограничение по длине.  
Поэтому модуль использует **короткий стабильный `originId`**, а не полный “читаемый” идентификатор гостя.

Это сделано специально, чтобы:

- значение `ORIGIN_ID` полностью помещалось в поле Bitrix;
- Bitrix/БД не обрезали идентификатор молча;
- exact-match поиск уже существующей записи работал корректно;
- дедупликация согласий по одному и тому же гостю оставалась стабильной между сессиями.

Иными словами, `originId` в модуле должен быть не “красивым”, а **коротким, детерминированным и совместимым с ограничениями Bitrix**.
- **Логика**: для авторизованного пользователя используется `user:<id>`, для гостя — `guest:<guestClientId>`
- **Сессионный флаг**: предотвращение повторного логирования в рамках одной сессии

### Адаптивность и мобильная версия
![Мобильная версия плашки](docs/assets/screenshot-mobile.png)

Модуль автоматически адаптируется под мобильные устройства:
- **Responsive-дизайн**: плашка корректно отображается на всех экранах
- **Touch-friendly**: оптимизировано для сенсорных устройств
- **Опциональное отключение**: можно скрыть на мобильных устройствах

---

## 🧩 Интеграция с Реестром согласий

Если включить опцию, модуль при выборе `accepted` добавит запись в Реестр согласий (укажите ID соглашения в настройках).
При выборе `rejected` запись в реестр не создаётся — сохраняется только локальное решение в cookie.
Для гостей модуль использует технический cookie `ushakov_cookie_guest_<siteId>` для более устойчивой дедупликации записей, а для авторизованных пользователей опирается на ID пользователя.

---

## 🛡️ Безопасность и приватность

- Куки ставятся с `SameSite=Lax`, флаг `Secure` включается при HTTPS
- Основная cookie согласия изолирована по сайту через `ushakov_cookie_<siteId>`
- HTML плашки пропускается через белый список тегов (минимизация XSS)
- Конфигурация и куки устанавливаются только с текущего домена
- Валидация всех входящих параметров

---

## 🚀 Roadmap

- [x] **Hover-эффекты** с автоматическим затемнением цветов
- [x] **Валидация CSS** для всех параметров
- [x] **Антидублирование** записей согласий
- [x] **Обратная совместимость** со старыми ядрами
- [x] Явная модель `accepted/rejected` без крестика
- [ ] Кнопка «Настройки»
- [ ] Доп. триггеры показа (скролл, клик, таймаут)
- [ ] Локализации (EN/DE/RO)
- [ ] Пресеты оформления

---

## 🙌 Вклад

PR и Issues приветствуются: баг-репорты, фич-реквесты, UX-идеи. См. [CONTRIBUTING](CONTRIBUTING.md).

---

## 📜 Лицензия

MIT — см. [LICENSE](LICENSE).

---

---

# English

ushakov.cookie — Cookie consent banner for 1C-Bitrix

Flexible banner for 1C-Bitrix with full customization: position, alignment, explicit accept/reject actions (without a close cross), delay, consent storage, optional integration with Bitrix Consent Registry.

**Features:**
- Position: top/bottom, alignment: left/center/right
- Explicit user choice: Accept / Reject (no close cross)
- Delay, z-index, margins, border radius, shadows
- Decision storage: `accepted` / `rejected` in site-specific cookie (SameSite=Lax, Secure over HTTPS)
- Legacy compatibility: old cookie value `1` is treated as `accepted`
- Optional integration with Bitrix Consent Registry
- Multi-site support with per-site settings
- Banner text via visual editor, auto-link to `/cookies-agreement.php`
- **Hover effects**: automatic color darkening for accept button
- **CSS validation**: px, rem, em, % validation for all dimensional parameters
- **Anti-duplication**: prevents duplicate consent records in registry
- **Backward compatibility**: supports both new and old Bitrix cores (UserConsent API)

**Compatibility:**
- PHP 7.4+ / 8.0+
- 1C-Bitrix (main module required)
- HTTPS support with Secure flag

**Installation:**
1. Download ZIP from [Releases](../../releases) and extract to `/local/modules/ushakov.cookie`
2. Install via Bitrix admin: Marketplace → Installed solutions → Install module
3. Select sites during installation — module automatically creates `/cookies-agreement.php` in each selected site root
   > When uninstalling, these files are automatically removed as well.

**Screenshots:**
- Desktop version: [screenshot-desktop.png](docs/assets/screenshot-desktop.png)
- Mobile version: [screenshot-mobile.png](docs/assets/screenshot-mobile.png)  
- Admin panel: [screenshot-admin.png](docs/assets/screenshot-admin.png)

**Settings:**
Admin → Product settings → Module settings → ushakov.cookie.
Options: enable, position/alignment, shared button block position, explicit accept/reject actions with separate texts/colors, delay/z-index/margins, storage lifetime, banner HTML, (optional) Consent Registry integration.

**Technical features:**
- Automatic detection of UserConsent API version (new/old cores)
- Hover effects with 20% color darkening
- CSS value validation (px, rem, em, %)
- Anti-duplication system for consent records
- Session-based logging prevention

Licensed under [MIT](LICENSE).
