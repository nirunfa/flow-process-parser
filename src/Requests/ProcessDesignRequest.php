<?php

namespace Nirunfa\FlowProcessParser\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Nirunfa\FlowProcessParser\Models\NCategory;
use Nirunfa\FlowProcessParser\Models\NGroup;
use Nirunfa\FlowProcessParser\Models\NProcessDesign;

class ProcessDesignRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules ()
    {
        $rules = [];
        switch ($this->method()) {
            case 'GET':
                $rules = [
                    'name' => 'max:50',
                    'group_id'=>['nullable','integer'],
                    'category_id'=>['nullable','integer'],
                    'status'=>['nullable',Rule::in([NProcessDesign::STATUS_DISABLE,NProcessDesign::STATUS_ENABLE])],
                    'description'=>[],
                    'define_key'=>[]
                ];
                break;
            case 'POST':
            case 'PUT':
                $rules = [
                    'name' => ['required',Rule::unique(NProcessDesign::class, 'name')->ignore($this->route()->process_design)],
                    'group_id'=>['nullable',function($attribute,$value,$fail){
                        if($value>0){
                            $group = NGroup::find($value);
                            if(!$group){
                                $fail('分组不存在');
                            }
                        }
                    }],
                    'category_id'=>['nullable',function($attribute,$value,$fail){
                        if($value>0){
                            $category = NCategory::find($value);
                            if(!$category){
                                $fail('分类不存在');
                            }
                        }
                    }],
                    'status'=>['required',Rule::in([NProcessDesign::STATUS_DISABLE,NProcessDesign::STATUS_ENABLE])],
                    'description'=>[],
                    'define_key'=>['required',Rule::unique(NProcessDesign::class, 'define_key')->ignore($this->route()->process_design)],
                ];
                break;
        }
        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages ()
    {
        return [
            'name.required' => '名称不能为空',
            'name.max' => '名称长度不能大于50',

        ];
    }
}
