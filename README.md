# shef.haschangefiles
Учёт исправленных файлов ядра.

> Добавляет ссылку в меню пользователя в закладку Расширения на [страницу учета правок](/local/admin/shef.haschangefiles/list.php)
> 
> В админке на странице [установки обновления](/bitrix/admin/update_system.php) предупреждает  о необходимости контролировать изменения.

* [change log](CHANGELOG.md)

## Установка
После установки нужно сбросить кеш.

## Настройки
В настройках указывается путь к проекту на машине разработчика, для реализации открытия кода по клику в _PhpStorm_.

## Принцип работы
Пусть есть файл `somecode/test.php`

### 1. Вносим правку
Обрамляем изменения блоком `// change ////` ... `// change stop ////`

```php
<?php
function test(): void
{
  // some code ////
  foreach($list as $item)
  {
    // change ////
    // $item['NAME'] = $item['CODE']; ////
    $item['NAME'] = sprintf('[%s] %s', $item['ID'], $item['CODE']);
    // change stop ////
  }
  // some code //// 
}
?>
```

### 2. Создаем копию
Файл копируем -> `somecode/has-change_test.php`, выгружаем на сервер.

Теперь у нас 2 одинаковые файла:

* `somecode/test.php`
* `somecode/has-change_test.php`

Если их в _PhpStorm_ выделить и нажать _Ctrl+B_ то сравнение файлов покажет что они одинаковые.

### 3. Загружаем обновления
Обновления Битрикс перетирает файл `somecode/test.php`

Теперь у нас 2 разных файла:

* `somecode/test.php` -> обновленный код
* `somecode/has-change_test.php` -> старый код с правками

![shef.haschangefiles::страница учета правок](/bitrix/images/shef.haschangefiles/docs/sf1.png)

[Страница учета правок](/local/admin/shef.haschangefiles/list.php) показывает наличие расхождений.

### 4. Восстанавливаем правки
Открываем [страницу учета правок](/local/admin/shef.haschangefiles/list.php)

Кликаем на файл `somecode/test.php`, он откроется в _PhpStorm_ (если верно настроили модуль).

Выкачиаем с сервера обновленный файл `somecode/test.php`.

Выделяем 2 файла и `somecode/test.php` и `somecode/has-change_test.php`, нажимаем _Ctrl+B_ для открытия окна сравнения. 

Из `somecode/has-change_test.php` в `somecode/test.php` переносим правки.

![shef.haschangefiles::перенос правок](/bitrix/images/shef.haschangefiles/docs/sf2.png)

Создаем копию `somecode/test.php` в `somecode/has-change_test.php`.

Выгружаем на сервер.

Теперь у нас снова 2 одинаковые файла. 

[Страница учета правок](/local/admin/shef.haschangefiles/list.php) показывает отсутствие расхождений.



