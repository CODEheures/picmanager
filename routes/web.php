<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/{format}/{dir}/{hashName}/{ext}', ['as' => 'picture.get', 'uses' => 'PictureController@get'])
    ->where(['format' => config('routes.wheres.format')])
    ->where(['dir' => config('routes.wheres.dir')])
    ->where(['hashName' => config('routes.wheres.hashName')])
    ->where(['ext' => config('routes.wheres.ext')]);



Route::group(['prefix' => 'private'] , function () {
    Route::post('/getmd5', ['uses' => 'PictureController@getMd5']);
    Route::delete('/cancelmd5/{csrf}', ['uses' => 'PictureController@cancelMd5']);
    Route::post('/savepicture', ['uses' => 'PictureController@save']);

    Route::delete('/{format}/{dir}/{hashName}/{ext}', ['uses' => 'PictureController@destroy'])
        ->where(['format' => config('routes.wheres.format')])
        ->where(['dir' => config('routes.wheres.dir')])
        ->where(['hashName' => config('routes.wheres.hashName')])
        ->where(['ext' => config('routes.wheres.ext')]);


    Route::get('/infos', ['uses' => 'PictureController@infos']);
});
