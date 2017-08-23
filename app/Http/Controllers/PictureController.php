<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class PictureController extends Controller
{

    const APP_PATH = 'app/';

    const TEMPO_PARENT_PATH = 'tempo/';

    Const MAX_FILE_PER_DIR = 1024;
    Const MAX_DIR = 100;

    CONST WATERMARK_FILENAME = 'watermark';


    private $appPath;
    private $appTempoPath;



    public function __construct() {
        $this->middleware('privateAccess', ['except' => ['getNormal', 'getThumb', 'infoLocalFiles']]);
        $this->appPath = storage_path(self::APP_PATH);
        $this->appTempoPath = $this->appPath . self::TEMPO_PARENT_PATH;
        if(!is_dir($this->appPath)){ mkdir($this->appPath); }
        if(!is_dir($this->appTempoPath)){ mkdir($this->appTempoPath); }
    }

    public function get(string $format, string $dir, string $hashName, string $ext)  {
        $path = $this->pathFile($format, $dir, $hashName, $ext);
        $mime = static::mime($ext);

        if (file_exists($path) && $mime){
            return response(file_get_contents($path),200)->header("Content-Type", $mime);
        } else {
            return response('not found', 404);
        }
    }

    public function destroy(string $format, string $dir, string $hashName, string $ext) {
        $path = $this->pathFile($format, $dir, $hashName, $ext);
        $mime = static::mime($ext);

        if (file_exists($path) && $mime){
            unlink($path);
            return response('ok');
        } else {
            return response('not found', 404);
        }
    }

    public function infos() {
        $freeSpaceServer = disk_free_space(__DIR__);
        $files = [];
        $size = 0;
        try {
            $files = Storage::allFiles();
            foreach ($files as $file){
                $size += Storage::size($file);
            }

            $count_load = round(100*count($files)/(self::MAX_FILE_PER_DIR*self::MAX_DIR),2);
            $bytes_load  = round(100*(1/$freeSpaceServer),2);
            $most_restrictive_load = max($count_load, $bytes_load);
            return response()->json([
                'name' => $_SERVER['HTTP_HOST'],
                'count' => count($files),
                'size' =>$this->octectToMbytes($size),
                'count_load' => $count_load,
                'bytes_load' => $bytes_load,
                'load' => $most_restrictive_load
            ]);
        } catch (\Exception $e) {
            return response()->json(['name' => $_SERVER['HTTP_HOST'],'count' => count($files), 'size' =>$this->octectToMbytes($size), 'count_load' => 100, 'bytes_load' => 100]);
        }
    }

    public function getMd5(Request $request) {
        try {
            if(!$request->file('addpicture')
                || !$request->csrf)
            {
                throw new \Exception("Request error");
            }

            //return response($request->file('addpicture')->getRealPath(),200);
            $md5Name = md5_file($request->file('addpicture')->getRealPath());
            $guessExtension = $request->file('addpicture')->guessExtension();
            $csrfPath = $request->csrf;
            $remoteServerPath = str_replace('.','',$request->ip());

            if($md5Name && $guessExtension  && $csrfPath) {
                $file = $request->file('addpicture')->storeAs(static::TEMPO_PARENT_PATH . $remoteServerPath . '/' . $csrfPath  , $md5Name . '.' . $guessExtension);
                return response()->json(['md5_name' => $md5Name, 'guess_extension' => $guessExtension]);
            } else {
                throw new \Exception("Save error");
            }
        } catch (\Exception $e) {
            return response('exception' . $e->getMessage(), 500);
        }

    }

    public function cancelMd5(Request $request) {
        try {
            if(!$request->csrf) {
                throw new \Exception("Request error");
            }

            //return response($request->file('addpicture')->getRealPath(),200);
            $csrfPath = $request->csrf;
            $remoteServerPath = str_replace('.','',$request->ip());

            if($csrfPath) {
                $this->delDir($this->appTempoPath. $remoteServerPath . '/' .$csrfPath);
                return response('ok');
            } else {
                throw new \Exception("Save error");
            }
        } catch (\Exception $e) {
            return response('exception' . $e->getMessage(), 500);
        }

    }

    public function save(Request $request) {
        try {
            if(!$request->file('watermark')
                || !$request->csrf
                || !$request->md5_name
                || !$request->guess_extension
                || !$request->formats)
            {
                throw new \Exception("Request error");
            }

            //Extract Array from formats
            try {
                $formats = json_decode($request->formats, true);
                foreach ($formats as $format) {
                    if(!key_exists('name',$format)
                        || !key_exists('width',$format)
                        || !key_exists('ratio',$format)
                        || !key_exists('back_color',$format)
                        || !key_exists('format_encoding',$format)
                        || !is_string($format['name'])
                        || !is_string($format['back_color'])
                        || !is_string($format['format_encoding'])
                        || !filter_var($format['width'], FILTER_VALIDATE_INT)
                        || !filter_var($format['width'], FILTER_VALIDATE_FLOAT)
                    ) { throw new \Exception("Request Error"); }
                }
            } catch (\Exception $e) {
                throw new \Exception("Request error");
            }

            $md5Name = $request->md5_name;
            $guessExtension = $request->guess_extension;
            $watermarkExtension = $request->file('watermark')->guessExtension();
            $csrfPath = $request->csrf;
            $remoteServerPath = str_replace('.','',$request->ip());

            if($md5Name && $guessExtension && $watermarkExtension && $csrfPath) {
                ini_set('memory_limit','256M');
                $watermark = $request->file('watermark')->storeAs(static::TEMPO_PARENT_PATH . $remoteServerPath . '/' . $csrfPath  , static::WATERMARK_FILENAME . '.' . $watermarkExtension);

                $urls = ['hashName' => $md5Name];
                foreach ($formats as $format) {
                    $url = $this->createPicture($remoteServerPath, $csrfPath, $md5Name, $guessExtension, $watermarkExtension, (int)$format['width'], (float)$format['ratio'] , $format['back_color'], $format['format_encoding']);
                    $urls[$format['name']] = $url;
                }

                $this->delDir($this->appTempoPath. $remoteServerPath . '/' . $csrfPath);
                return response()->json($urls);
            } else {
                throw new \Exception("Save error");
            }
        } catch (\Exception $e) {
            return response('exception' . $e->getMessage(), 500);
        }

    }

    //private parts
    private function pathFile(string $format, string $dir, string $hashName, string $ext) {
        return $this->appPath
            . $dir . '/'
            . $hashName .'-'
            . $format
            . '.' . $ext;
    }

    private static function mime(string $ext) {
        switch ($ext) {
            case 'jpg':
                return 'image/jpeg';
                break;
            case 'png':
                return 'image/png';
                break;
            case 'webp':
                return 'image/webp';
                break;
            default:
                return null;
        }
    }

    private function freePath() {
        for($i=1; $i<static::MAX_DIR; $i++){
            $dir = $this->appPath . $i;
            if(!is_dir($dir) || count(scandir($dir)) < static::MAX_FILE_PER_DIR){
                return $i;
            }
        }
        return false;
    }

    private function createPicture(string $ip, string $csrf, string $md5, string $fileExtention, string $watermarkExtension, int $size, float $ratio, string $back_color, string $format_encoding){

        //File for process
        $filePathToProcess = $this->appTempoPath . $ip . '/' . $csrf . '/' . $md5 . '.' . $fileExtention;
        $watermarkPathForProcess = $this->appTempoPath . $ip . '/' . $csrf . '/' . static::WATERMARK_FILENAME . '.' . $watermarkExtension;

        //max height
        $picture_height = round($size/$ratio);
        $format = $size . 'x' . $picture_height;

        //Output path
        $freePath = $this->freePath();
        $outputFilepath =  $this->appPath . $freePath . '/' . $md5 . '-'. $format . '.' . $format_encoding;


        //picture to process
        $picture = Image::make($filePathToProcess);

        //resize picture
        $picture->resize($size, $picture_height, function ($constraint) {
            //$constraint->upsize();
            $constraint->aspectRatio();
        });

        $raw = Image::canvas($size, $picture_height, $back_color);
        $raw->insert($picture, 'center');
        $raw->insert($watermarkPathForProcess,'bottom-left',5,5);
        $raw->encode($format_encoding);

        if(!is_dir($this->appPath . $freePath)){
            mkdir($this->appPath . $freePath);
        }

        $raw->save($outputFilepath);
        $raw->destroy();
        $picture->destroy();

        return route('picture.get', ['format' => $format, 'dir' => $freePath, 'hasName' => $md5, 'ext' => $format_encoding]);
    }

    private function delDir(string $dir) {
        if(is_dir($dir)){
            $scan = scandir($dir);
            for($i = 2 ; $i<count($scan); $i++){
                unlink($dir . '/' . $scan[$i]);
            }
            rmdir($dir);
        }
    }

    private function octectToMbytes($value) {
        return round($value/(1024*1024),0);
    }
}
