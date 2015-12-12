<?php

namespace App\Http\Controllers;

use DB;

class RegionController extends Controller
{
    const REGION_URL = 'http://files2.mca.gov.cn/www/201511/20151127101349186.htm';

    public function index()
    {
        $contents = file_get_contents(self::REGION_URL);
        // 懒惰匹配，配汉字
        $pattern = '#<td class="xl71" x:num>(.*)</td>[\s\S]*?<td class="xl71" x:str>.*?([\x{4e00}-\x{9fa5}]+).*</td>#u';
        preg_match_all($pattern, $contents, $matches);
        $data = array_combine($matches[1], $matches[2]);

        $insertData = [];
        foreach ($data as $key => $value) {
            list($level, $parentCode) = $this->processCode($key);

            $arr['code']        = $key;
            $arr['name']        = $value;
            $arr['level']       = $level;
            $arr['parent_code'] = $parentCode;

            $insertData[] = $arr;
        }
        // insert success return true
        DB::connection('dev_mongodb')
            ->collection('region')
            ->insert($insertData);
    }

    /**
     * [processCode description]
     * @param  int   $code 代码
     * @return array       ['level', 'parent_code']
     */
    protected function processCode($code)
    {
        $codeSubStr    = substr($code, -4);
        $firstSegment  = substr($codeSubStr, 0, 2);
        $secondSegment = substr($codeSubStr, -2);

        if ($firstSegment === '00') {
            return [1, '0'];
        }

        if ($secondSegment === '00') {
            return [2, substr($code, 0, 2).'0000'];
        }

        return [3, substr($code, 0, 4).'00'];
    }

    public function show($flag)
    {
        if ($flag === 'base') {
            return $this->baseInfomation();
        }

        // todo
    }

    protected function baseInfomation()
    {
        // todo
    }

}