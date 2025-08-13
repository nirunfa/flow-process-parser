<?php

if(function_exists('getCode')){
    function getCode(){
        return date('YmdHis').rand(1000,9999);
    }
}
