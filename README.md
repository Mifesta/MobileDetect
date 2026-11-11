# mifesta/mobiledetect

Определяет телефон, планшет и краулер по User-Agent и HTTP-заголовкам. Каталог устройств берётся из `mobiledetect/mobiledetectlib`: у веток 2.8, 3.74 и 4.x разный PHP и разный конструктор, поэтому у пакета несколько релизов, а клиент сам подстраивается под установленную ветку.

На PHP 7.4 и новее достаточно:

```bash
composer require mifesta/mobiledetect
```

Composer возьмёт `2.5` и поставит каталог, который живёт на этом PHP: `3.74` на 7.4, `4.8`–`4.9` на 8.0–8.1, `4.10` и новее на 8.2+.

| PHP | Ограничение в проекте | Что встанет при обновлении |
| --- | --- | --- |
| 7.0 – 7.3 | `^1.4` | 1.4 и mobiledetectlib 2.8 |
| 7.4 | `^1.3` или `^1.5` | 1.5 и mobiledetectlib 3.74 |
| 7.4 и новее | без ограничения или `^2.4` | 2.5; каталог выбирается по PHP |
| 8.0 и новее | `^1.3` или `^1.6` | 1.6 и mobiledetectlib 4.8+ |

Релиз `2.4.0` вызывал класс `\Mobile_Detect`, которого в ветке 4.x нет. На PHP 8 его заменяет `2.5.0`. `2.5.1`, `1.4.1`, `1.5.1` и `1.6.1` читают `X-Device-User-Agent` и остальные заголовки устройства, даже когда основной User-Agent уже задан.

## Использование

```php
use MifestaBrowserData\BrowserData;

if (BrowserData::isBot()) {
    // обычный краулер, не мобильный
}

if (BrowserData::isMobile()) {
    // телефон, планшет или мобильный краулер
}

if (BrowserData::isTablet()) {
    // планшет; isMobile() для него тоже true
}

BrowserData::is('iPhone');
BrowserData::is('AndroidOS');
BrowserData::version('iOS');
```

`is('mobile')`, `is('tablet')`, `is('bot')`, `is('anybot')` и `is('mobilebot')` не зависят от регистра.

Мобильный краулер (`Googlebot` в UA телефона, `Googlebot-Mobile`, `AdsBot-Google-Mobile`, `YandexMobileBot`) попадает в `isAnyBot()` и `isMobile()`, но не в `isBot()`. Имена телефонов вроде Cubot и iRobot краулерами не считаются.

Вне запроса UA можно задать явно:

```php
BrowserData::setUserAgent($userAgent);
```

Детектор хранится, только пока не изменились заголовки `HTTP_*` в `$_SERVER`. Следующий запрос в том же воркере PHP-FPM получает свой результат.

## Credits

Sergei Gruzdov — <https://github.com/cardee2000>

## Security

Sergei Gruzdov <cardee@mifesta.com>
