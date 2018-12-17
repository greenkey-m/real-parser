/* Создание таблицы в базе данных */
CREATE TABLE IF NOT EXISTS `oc_category_markup` (
      `markup_id` int(11) NOT NULL AUTO_INCREMENT,
      `category_id` int(11) NOT NULL,
      `markup` int(3),
      `status` tinyint(1),
      `date_added` datetime,
      `date_modified` datetime,
      PRIMARY KEY (`markup_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
