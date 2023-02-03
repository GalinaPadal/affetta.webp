<?
use Bitrix\Main;
use Bitrix\Main\IO;
use Bitrix\Main\UI\Viewer;
use Bitrix\Main\File;
use Bitrix\Main\Web;
use Bitrix\Main\File\Image;
use Bitrix\Main\File\Image\Rectangle;
use Bitrix\Main\File\Internal;
use Bitrix\Main\ORM\Query;
use Bitrix\Main\Security;

/*
Данил. Конвертор в Webp
Николай. Доработанная стандартная функция ресайза CFile::ResizeImageGet(). Ресайз с чпу с использованием webp. Доработал getWebp вывод url свойства в стандартный ключ SRC и src.
---------------------
Подключение в init.php
Файл кидаем сюда /local/php_interface/classes/affetta/AffettaCFile.php

CModule::AddAutoloadClasses("", array(
    'AffettaCFile' => '/local/php_interface/classes/affetta/AffettaCFile.php'
));

Используется точто также как CFile::ResizeImageGet ,
только добавлена первая переменная AffettaCFile::ResizeImageGet($arParams = []

Короткая запись
$webFile = AffettaCFile::ResizeImageGet(['FILE_NAME_NEW' => 'Тест фото 1'], 196454, array( "width" => 700, "height" => 400 ));
dump($webFile);

Полная запись
$webFile = AffettaCFile::ResizeImageGet([
        'FILE_NAME_NEW' => 'Тест фото 1',
        'FILE_NAME_NEW_USE' => true,
        'WEBP_USE' => true,
], CFile::GetFileArray(196454), array( "width" => 700, "height" => 400 ), BX_RESIZE_IMAGE_EXACT);

------------------------

if(!empty($actualItem["MORE_PHOTO"])){
    $actualItem["MORE_PHOTO_COUNT"] = count($actualItem["MORE_PHOTO"]);
    foreach ($actualItem["MORE_PHOTO"] as $key => $file) {
        $actualItem["MORE_PHOTO"][$key] =[
            'thumb' => AffettaCFile::ResizeImageGet(['FILE_NAME_NEW' => $actualItem['NAME'].'. Маленькое фото'.($key+1)], $file, ["width" => 100, "height" => 100 ]),
            'image' => AffettaCFile::ResizeImageGet(['FILE_NAME_NEW' => $actualItem['NAME'].'. Фото'.($key+1)], $file, ["width" => 700, "height" => 700 ]),
        ];
    }
}

<? foreach ($actualItem["MORE_PHOTO"] as $key => $file) {
    $file = $file['thumb'];
    ?>
    <div class="swiper-slide">
        <div class="section-product-card__block-slide-image">
            <img src="<?=$file['SRC']?>" alt="<?=$file['ALT']?>" title="<?=$file['TITLE']?>" class="section-product-card__slide-image"/>
        </div>
    </div>
<? } ?>
---------------------

<?
$B1_IMG = AffettaCFile::ResizeImageGet(['FILE_NAME_NEW' => $arResult['GOS']['B1_NAME']['VALUE']], $arResult['GOS']['B1_IMG']['VALUE'], ["width" => 512, "height" => 510 ]);
?>
<img class="about-us__image" src="<?=$B1_IMG['SRC']?>" alt="<?=$B1_IMG['ALT']?>" title="<?=$B1_IMG['TITLE']?>">

 */
class AffettaCFile extends \CFile
{
    private static $jpgQuality = 90; // по дефолту качество сжатия int либо false, перебивает в ResizeImageGet
    private static $bInitSizes = true;  // по дефолту выводит размер картинки true,false, перебивает в ResizeImageGet
    private static $webpUse = true; // по дефолту вовертировать в webp формат,можно перебить в ResizeImageGet  $arParams['WEBP_USE'] = true или false
    private static $fileNameNewUse = true; // глобально включает использование другого имение
    private static $isPng = true; // используется для getWebp в одной из функций

    public static function CopyFileMod($arParams = [], $file, $bRegister = true, $newPath = "")
    {

        if (!is_array($file) && intval($file) > 0)
        {
            $file = static::GetFileArray($file);
        }

        $file['FILE_SIZE_SHOW'] = static::FormatSize($file['FILE_SIZE']);
        $newFilePath = '';

        if(!empty($newPath)) {
            $newFilePath = $newPath;
        } else {

            if(isset($arParams['FILE_NAME_NEW_USE'])) {
                if ($arParams['FILE_NAME_NEW_USE'] == true) {
                    self::$fileNameNewUse = true;
                } else if ($arParams['FILE_NAME_NEW_USE'] == false) {
                    self::$fileNameNewUse = false;
                }
            }

            if(self::$fileNameNewUse) {

                if (!empty($file['DESCRIPTION'])) {
                    $arParams['FILE_NAME_NEW'] = $file['DESCRIPTION'];
                }

                $file['ALT'] = $file['TITLE'] = $arParams['FILE_NAME_NEW'];

                $arParams['FILE_NAME_NEW'] = trim($arParams['FILE_NAME_NEW']);

                if (!empty($arParams['FILE_NAME_NEW'])) {
                    $FILE_NAME_TRANSLIT = array("replace_space" => "_", "replace_other" => "_");
                    $FILE_NAME_NEW = Cutil::translit($arParams['FILE_NAME_NEW'], "ru", $FILE_NAME_TRANSLIT);
                    $ext = end(explode('.', $file["FILE_NAME"]));
                    $file["FILE_NAME"] = $FILE_NAME_NEW . '.' . $ext;
                    $file['ORIGINAL_NAME'] = $arParams['FILE_NAME_NEW'] . '.' . $ext;
                } else {
                    $FILE_NAME_TRANSLIT = array("replace_space" => "_", "replace_other" => "_");
                    $FILE_NAME_NEW = Cutil::translit($file["ORIGINAL_NAME"], "ru", $FILE_NAME_TRANSLIT);
                    $file["FILE_NAME"] = $FILE_NAME_NEW;
                }
                $newFilePath = 'resize_cache/af_files/' . $file['SUBDIR'] . '/' . $file['FILE_NAME'];

            }

        }

        if(!empty($newFilePath)) {
            if (!file_exists($_SERVER['DOCUMENT_ROOT'] . '/upload/' . $newFilePath)) {
                $fileCopy = AffettaCFile::CopyFile($file['ID'], $bRegister, $newFilePath);
//        dump($fileCopy);
            }
            $file["SRC"] = '/upload/' . $newFilePath;

        }
        return $file;
    }

    /*
     * Доработанная стандартная функция ресайза CFile::ResizeImageGet(). Николай
     *
     * $arParams = []
     * FILE_NAME_NEW: Собственное имя для файла $arParams['FILE_NAME_TRANSLIT'] = 'file_imag_1'
     * FILE_NAME_TRANSLIT: Изменение стандартной настройки транслита. По дефолту $arParams['FILE_NAME_TRANSLIT'] = array("replace_space" => "_", "replace_other" => "_")
     * WEBP_USE: конвертировать в webp формат $arParams['WEBP_USE'] = true
     *
     */
    public static function ResizeImageGet($arParams = [], $file, $arSize, $resizeType = BX_RESIZE_IMAGE_PROPORTIONAL, $bInitSizes = false, $arFilters = false, $bImmediate = false, $jpgQuality = false)
    {
        if (!is_array($file) && intval($file) > 0)
        {
            $file = static::GetFileArray($file);
        } elseif (!empty($file['ID'])){
            $file = static::GetFileArray($file['ID']);
        }

        if (!is_array($file) || !array_key_exists("FILE_NAME", $file) || $file["FILE_NAME"] == '')
            return false;

        if ($resizeType !== BX_RESIZE_IMAGE_EXACT && $resizeType !== BX_RESIZE_IMAGE_PROPORTIONAL_ALT)
            $resizeType = BX_RESIZE_IMAGE_PROPORTIONAL;

        if (!is_array($arSize))
            $arSize = array();
        if (!array_key_exists("width", $arSize) || intval($arSize["width"]) <= 0)
            $arSize["width"] = 0;
        if (!array_key_exists("height", $arSize) || intval($arSize["height"]) <= 0)
            $arSize["height"] = 0;
        $arSize["width"] = intval($arSize["width"]);
        $arSize["height"] = intval($arSize["height"]);

        if(!empty(self::$jpgQuality)) {
            $jpgQuality = self::$jpgQuality;
        }
        if(!empty(self::$bInitSizes)) {
            $bInitSizes = self::$bInitSizes;
        }

        $uploadDirName = COption::GetOptionString("main", "upload_dir", "upload");

        $imageFile = "/".$uploadDirName."/".$file["SUBDIR"]."/".$file["FILE_NAME"];
        $arImageSize = false;
        $bFilters = is_array($arFilters) && !empty($arFilters);

//        if (
//            ($arSize["width"] <= 0 || $arSize["width"] >= $file["WIDTH"])
//            && ($arSize["height"] <= 0 || $arSize["height"] >= $file["HEIGHT"])
//        )
//        {
//            if($bFilters)
//            {
//                //Only filters. Leave size unchanged
//                $arSize["width"] = $file["WIDTH"];
//                $arSize["height"] = $file["HEIGHT"];
//                $resizeType = BX_RESIZE_IMAGE_PROPORTIONAL;
//            }
//            else
//            {
//                global $arCloudImageSizeCache;
//                $arCloudImageSizeCache[$file["SRC"]] = array($file["WIDTH"], $file["HEIGHT"]);
//
//                return array(
//                    "src" => $file["SRC"],
//                    "width" => intval($file["WIDTH"]),
//                    "height" => intval($file["HEIGHT"]),
//                    "size" => $file["FILE_SIZE"],
//                );
//            }
//        }

        $io = CBXVirtualIo::GetInstance();

        if(isset($arParams['FILE_NAME_NEW_USE'])) {
            if ($arParams['FILE_NAME_NEW_USE'] == true) {
                self::$fileNameNewUse = true;
            } else if ($arParams['FILE_NAME_NEW_USE'] == false) {
                self::$fileNameNewUse = false;
            }
        }

        if(self::$fileNameNewUse){

            if (!empty($file['DESCRIPTION'])) {
                $arParams['FILE_NAME_NEW'] = $file['DESCRIPTION'];
            }

            $file['ALT'] = $file['TITLE'] = $arParams['FILE_NAME_NEW'];

            $arParams['FILE_NAME_NEW'] = trim($arParams['FILE_NAME_NEW']);

            // Транслит имени файла
            $FILE_NAME_TRANSLIT = array("replace_space" => "_", "replace_other" => "_");
            if(!empty($arParams['FILE_NAME_TRANSLIT'])){ // Изменение стандартной настройки транслита FILE_NAME_TRANSLIT
                $FILE_NAME_TRANSLIT = array_merge($FILE_NAME_TRANSLIT, $arParams['FILE_NAME_TRANSLIT']);
            }
            $arParams['FILE_NAME_NEW'] = Cutil::translit($arParams['FILE_NAME_NEW'], "ru", $FILE_NAME_TRANSLIT);

            // Собственное имя для файла FILE_NAME_NEW
            $ext = end(explode('.', $file["FILE_NAME"]));
            $file["FILE_NAME"] = $arParams['FILE_NAME_NEW'] . '.' . $ext;
        }

        $cacheImageFile = "/".$uploadDirName."/resize_cache/".$file["SUBDIR"]."/".$arSize["width"]."_".$arSize["height"]."_".$resizeType.(is_array($arFilters)? md5(serialize($arFilters)): "")."/".$file["FILE_NAME"];


        $cacheImageFileCheck = $cacheImageFile;
        if ($file["CONTENT_TYPE"] == "image/bmp")
            $cacheImageFileCheck .= ".jpg";

        static $cache = array();
        $cache_id = $cacheImageFileCheck;
        if(isset($cache[$cache_id]))
        {
            return $cache[$cache_id];
        }
        elseif (!file_exists($io->GetPhysicalName($_SERVER["DOCUMENT_ROOT"].$cacheImageFileCheck)))
        {
            if(!is_array($arFilters))
                $arFilters = array(
                    array("name" => "sharpen", "precision" => 15),
                );

            $sourceImageFile = $_SERVER["DOCUMENT_ROOT"].$imageFile;
            $cacheImageFileTmp = $_SERVER["DOCUMENT_ROOT"].$cacheImageFile;
            $bNeedResize = true;
            $callbackData = null;

            foreach(GetModuleEvents("main", "AffettaOnBeforeResizeImage", true) as $arEvent)
            {
                if(ExecuteModuleEventEx($arEvent, array(
                    $file,
                    array($arSize, $resizeType, array(), false, $arFilters, $bImmediate),
                    &$callbackData,
                    &$bNeedResize,
                    &$sourceImageFile,
                    &$cacheImageFileTmp,
                )))
                    break;
            }

            if ($bNeedResize && static::ResizeImageFile($sourceImageFile, $cacheImageFileTmp, $arSize, $resizeType, array(), $jpgQuality, $arFilters))
            {
                $cacheImageFile = mb_substr($cacheImageFileTmp, mb_strlen($_SERVER["DOCUMENT_ROOT"]));

                /****************************** QUOTA ******************************/
                if (COption::GetOptionInt("main", "disk_space") > 0)
                    CDiskQuota::updateDiskQuota("file", filesize($io->GetPhysicalName($cacheImageFileTmp)), "insert");
                /****************************** QUOTA ******************************/
            }
            else
            {
                $cacheImageFile = $imageFile;
            }

            foreach(GetModuleEvents("main", "OnAfterResizeImage", true) as $arEvent)
            {
                if(ExecuteModuleEventEx($arEvent, array(
                    $file,
                    array($arSize, $resizeType, array(), false, $arFilters),
                    &$callbackData,
                    &$cacheImageFile,
                    &$cacheImageFileTmp,
                    &$arImageSize,
                )))
                    break;
            }

            $cacheImageFileCheck = $cacheImageFile;
        }
        elseif (defined("BX_FILE_USE_FLOCK"))
        {
            $hLock = $io->OpenFile($_SERVER["DOCUMENT_ROOT"].$imageFile, "r+");
            if ($hLock)
            {
                flock($hLock, LOCK_EX);
                flock($hLock, LOCK_UN);
                fclose($hLock);
            }
        }

        if ($bInitSizes && !is_array($arImageSize))
        {
            $imageInfo = (new File\Image($_SERVER["DOCUMENT_ROOT"].$cacheImageFileCheck))->getInfo();
            if($imageInfo)
            {
                $arImageSize[0] = $imageInfo->getWidth();
                $arImageSize[1] = $imageInfo->getHeight();
            }
            else
            {
                $arImageSize = [0, 0];
            }

            $f = $io->GetFile($_SERVER["DOCUMENT_ROOT"].$cacheImageFileCheck);
            $arImageSize[2] = $f->GetFileSize();
        }

        if (!is_array($arImageSize))
        {
            $arImageSize = [0, 0, 0];
        }
        $file = array_merge( $file, array(
            "src" => $cacheImageFileCheck,
            "SRC" => $cacheImageFileCheck,
            "width" => intval($arImageSize[0]),
            "WIDTH" => intval($arImageSize[0]),
            "height" => intval($arImageSize[1]),
            "HEIGHT" => intval($arImageSize[1]),
            "size" => $arImageSize[2],
        ));

        if(isset($arParams['WEBP_USE'])) {
            if ($arParams['WEBP_USE'] == true) {
                self::$webpUse = true;
            } else if ($arParams['WEBP_USE'] == false) {
                self::$webpUse = false;
            }
        }

        if(self::$webpUse) {
            $file = self::getWebp($file, $jpgQuality);
        }

        $cache[$cache_id] = $file;



        return $cache[$cache_id];
    }

    // Преобразование в webp. Данила
    private static function checkFormat($str)
    {
        if ($str === 'image/png')
        {
            self::$isPng = true;

            return true;
        }
        elseif ($str === 'image/jpeg')
        {
            self::$isPng = false;

            return true;
        }
        else return false;
    }

    private static function implodeSrc($arr)
    {
        $arr[count($arr) - 1] = '';

        return implode('/', $arr);
    }

    private static function generateSrc($str)
    {
        $arPath = explode('/', $str);

        if ($arPath[2] === 'resize_cache')
        {
            $arPath = self::implodeSrc($arPath);
            return str_replace('resize_cache/iblock', 'resize_cache/af_webp/iblock', $arPath);
        }
        else
        {
            $arPath = self::implodeSrc($arPath);
            return str_replace('upload/iblock', 'upload/af_webp/iblock', $arPath);
        }
    }

    public static function getWebp($file, $intQuality = 70)
    {
        $array = $file;
        if (self::checkFormat($array['CONTENT_TYPE']))
        {

            $array['WEBP_PATH'] = self::generateSrc($array['SRC']);

            if (self::$isPng)
            {
                $array['WEBP_FILE_NAME'] = str_replace('.png', '.webp', strtolower($array['FILE_NAME']));
            }
            else
            {
                $array['WEBP_FILE_NAME'] = str_replace('.jpg', '.webp', strtolower($array['FILE_NAME']));
                $array['WEBP_FILE_NAME'] = str_replace('.jpeg', '.webp', strtolower($array['WEBP_FILE_NAME']));
            }

            if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $array['WEBP_PATH']))
            {
                mkdir($_SERVER['DOCUMENT_ROOT'] . $array['WEBP_PATH'], 0777, true);
            }

            $array['WEBP_SRC'] = $array['WEBP_PATH'] . $array['WEBP_FILE_NAME'];

            if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $array['WEBP_SRC']))
            {
                if (self::$isPng)
                {
                    $im = imagecreatefrompng($_SERVER['DOCUMENT_ROOT'] . $array['SRC']);
                }
                else
                {
                    $im = imagecreatefromjpeg($_SERVER['DOCUMENT_ROOT'] . $array['SRC']);
                }

                imagewebp($im, $_SERVER['DOCUMENT_ROOT'] . $array['WEBP_SRC'], $intQuality);

                imagedestroy($im);

                if (filesize($_SERVER['DOCUMENT_ROOT'] . $array['WEBP_SRC']) % 2 == 1)
                {
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . $array['WEBP_SRC'], "\0", FILE_APPEND);
                }
            }

            $file['SRC'] = $file['src'] = $array['WEBP_SRC'];
        }

        return $file;
    }

    public static function resizePict($file, $width, $height, $isProportional = true, $intQuality = 70)
    {
        $file = \CFile::ResizeImageGet($file, array('width'=>$width, 'height'=>$height), ($isProportional ? BX_RESIZE_IMAGE_PROPORTIONAL : BX_RESIZE_IMAGE_EXACT), false, false, false, $intQuality);

        return $file['src'];
    }

    public static function getResizeWebp($file, $width, $height, $isProportional = true, $intQuality = 70)
    {
        $file['SRC'] = self::resizePict($file, $width, $height, $isProportional, $intQuality);
        $file = self::getWebp($file, $intQuality);

        return $file;
    }

    public static function getResizeWebpSrc($file, $width, $height, $isProportional = true, $intQuality = 70)
    {
        $file['SRC'] = self::resizePict($file, $width, $height, $isProportional, $intQuality);

        $file = self::getWebp($file, $intQuality);

        return $file['WEBP_SRC'];
    }
}?>