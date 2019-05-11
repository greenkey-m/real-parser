# opencart-3 electrozon parser

License: GPLv3

Парсер:
+ Подгрузка файла config.php (данные базы)
+ Загрузка параметра наценки из базы, и таблицы с наценками (если есть)
+ Создает лог temp, для записи событий
+ Удаление категорий которые пропали из базы, и товары тоже
+ Характеристики - подгрузка с сайта
+ Цена округление до 10 – свыше 1000
+ контроль за потерей доступа к файлу (проверка его обновления - если меньше суток, не копировать)
+ загрузка в базу блоками по 1000 единиц, переподключение в БД, паузы
- контроль за потерей доступа к БД - запускать заново, или переподключаться, загрузка блоками, фиксировать в логе
- сделать чекинг таблиц БД по товара на предмет ошибок
- если каких-то записей в БД не хватает, дополнить (исправить)
- чистка (проверка) картинок, удаление неиспольземых
Парсит разделы:
+ сравнить разделы, которые есть в БД с теми, которые в файле, если есть удаленные – записать в лог
+ удаляет исключенные
+ если раздел есть обновить
+ если раздела нет - создать
+ Картинка, если есть в базе, сохранить
Парсит товары:
+ сравнивает товары – если какие-то удалены, записывает в лог
+ удаляет исключенные
+ если товар есть – обновить
+ если товара нет – создать
+ сохранять seo категории
+ сохранять сео товара
+ при создании (обновлении) товара: создавать его параметры, 
+ загружать спецификацию с его страницы, 
+ загружать картинки с его страницы.
+ записывать вендора/производителя товара
+ наценка определяется из таблицы (если есть), если нет – ставится из параметра по дефолту
Настройка парсера в админке:
- Проверка наличия таблицы в базе, если нет – создать
- Сделать в настройках форму внесения наценок на категории
- сделать парсер по классам, попробовать сделать универсальную схему работы парсера
- чекинг БД
- Чтение файлов лога 2018-12-06_12-34, чтение текущей даты/времени
- Определение диапазона, сколько прошло
- если срок вышел, запустить парсер
- вывод информации по последнему логу
- создать группы категорий, для которых можно задавать наценку
- наценку проработать по категориям – параметры компонента (расширенная форма)
- Сделать кнопку – reset, которая очищает таблицы и все файлы
- настраиваемые параметры парсера (посмотреть в его коде TODO)
- параметры парсера – в настройках, что делать с исключенными товарами (автоудаление?)
- разобраться с кавычками в поиске модуля товара (избранные), возможно подменять их?

O-8v6Z2-d2

TODO
- поставщик (электрозон, ситилинк) – можно в таблице наценок держать
- либо задавать префикс идентификатора (задан в SKU)
- поставщик товара – в местоположении, 

v-bind or : - привязка к переменным
v-if условия (видимости) <span v-if="seen">
v-for - циклы <li v-for="todo in todos"> {{ todo.text }}
v-on or @click - события
v-model - для форм связывание
v-html - подставляет данные вместо {{}} <span v-html="rawHtml"></span>

