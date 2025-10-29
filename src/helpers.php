<?php

if(!function_exists('getCode')){
    function getCode(){
        return 'NF'.date('YmdHis').rand(1000,9999);
    }
}
