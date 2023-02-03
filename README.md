# Файл сжатия изображений и перевод их в формат WEBP
## Общий функционал
Разработан для сжатия изображений и перевод их в формат WEBP 
## Установка 
Загружаем файл на сайт и подключаем его в init.php в самом классе
## Полезная информация
``` 
CModule::AddAutoloadClasses("", array(
    'AffettaCFile' => '/local/php_interface/classes/affetta/AffettaCFile.php',
    'AffettaHelpers' => '/local/php_interface/classes/affetta/AffettaHelpers.php',
    'AffettaWishlist' => '/local/php_interface/classes/affetta/AffettaWishlist.php'
));
 ``` 
Пример подключения указан выше, глобально можно чпу и webp для картинок отключить, если у картинок есть описание то чпу с него строится.
Все хранится в папке upload/resize_cache и там идет разбиение по папкам, для удобного удаления.

Пример для файлов ЧПУ (без сжатия)
``` 
<? foreach ($arResult['GOS']['B1_FILES']['VALUE'] as $file) {
                        $file = AffettaCFile::CopyFileMod([], $file);
                        ?>
                        <a href="<?=$file['SRC']?>" class="link-reset" target="_blank" title="<?=$file['DESCRIPTION']?>">
                            <div class="block-docs__doc">
                                <span class="block-docs__name"><?=$file['ORIGINAL_NAME']?></span>
                                <span class="block-docs__size"><?=$file['FILE_SIZE_SHOW']?></span>
                            </div>
                        </a>
                    <? } ?>
 ``` 
