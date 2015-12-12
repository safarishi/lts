<?php

namespace App\Http\Controllers;

class RegionController extends Controller
{
    public function index()
    {
        // todo
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